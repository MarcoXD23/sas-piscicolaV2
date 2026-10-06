<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Rol;
use App\Models\Turno;
use App\Models\User;
use App\Support\Region;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class TurnoController extends Controller
{
    /** Máximo de días que se pueden programar de una vez. */
    private const MAX_DIAS_RANGO = 62;

    public function index(Request $request): View
    {
        // La semana se arma con los días de la finca (los turnos se agrupan por su día local)
        $semana = CarbonImmutable::parse($request->query('semana', Region::hoy()->toDateString()), Region::zona())->startOfWeek();
        $finSemana = $semana->addWeek();

        $turnos = Turno::query()
            ->with('user')
            ->queSeCruzanCon($semana, $finSemana)
            ->orderBy('inicia_at')
            ->get();

        return view('admin.turnos.index', [
            'semana' => $semana,
            'dias' => collect(CarbonPeriod::create($semana, $finSemana->subDay()))
                ->map(fn ($dia) => CarbonImmutable::instance($dia)),
            'turnosPorDia' => $turnos->groupBy(fn (Turno $turno) => $turno->inicia_at->toDateString()),
            'enCurso' => Turno::query()->with('user')->enCurso()->orderBy('termina_at')->get(),
            'personal' => $this->personalPorTurnos(),
        ]);
    }

    /**
     * Programa turnos rotativos: un turno por cada día elegido dentro del rango de fechas.
     */
    public function store(Request $request): RedirectResponse
    {
        $datos = $request->validate([
            'user_id' => ['required', 'integer'],
            'rol' => ['required', 'string', Rule::in(Turno::ROLES)],
            'desde' => ['required', 'date_format:Y-m-d', 'after_or_equal:'.Region::hoy()->toDateString()],
            'hasta' => ['required', 'date_format:Y-m-d', 'after_or_equal:desde'],
            'dias' => ['required', 'array', 'min:1'],
            'dias.*' => ['integer', 'between:1,7', 'distinct'],
            'hora_inicio' => ['required', 'date_format:H:i'],
            'hora_fin' => ['required', 'date_format:H:i', 'different:hora_inicio'],
        ], [
            'user_id.required' => __('mensajes.turno.elige_persona'),
            'rol.in' => __('mensajes.turno.turno_debe_ser_trabajador_celador'),
            'desde.after_or_equal' => __('mensajes.turno.no_pueden_programar_turnos_fechas'),
            'hasta.after_or_equal' => __('mensajes.turno.fecha_final_debe_ser_igual'),
            'dias.required' => __('mensajes.turno.elige_menos_dia_semana'),
            'hora_fin.different' => __('mensajes.turno.hora_salida_debe_ser_distinta'),
            'required' => __('mensajes.turno.este_dato_es_obligatorio'),
            'date_format' => __('mensajes.turno.formato_no_es_valido'),
        ]);

        // Las fechas y horas del formulario son de la zona de la finca (el cast de Turno las guarda en UTC)
        $desde = CarbonImmutable::parse($datos['desde'], Region::zona());
        $hasta = CarbonImmutable::parse($datos['hasta'], Region::zona());

        if ($desde->diffInDays($hasta) >= self::MAX_DIAS_RANGO) {
            throw ValidationException::withMessages(['hasta' => __('mensajes.turno.maximo_dias', ['dias' => self::MAX_DIAS_RANGO])]);
        }

        $persona = User::query()->with('roles')->find($datos['user_id']);
        if ($persona === null || ! $persona->estaAprobado() || ! $persona->tieneRol($datos['rol'])) {
            throw ValidationException::withMessages(['user_id' => __('mensajes.turno.sin_rol', ['rol' => $datos['rol']])]);
        }

        $nuevos = $this->generarTurnos($desde, $hasta, $datos['dias'], $datos['hora_inicio'], $datos['hora_fin']);
        if ($nuevos->isEmpty()) {
            throw ValidationException::withMessages(['dias' => __('mensajes.turno.no_hay_turnos_crear_ningun')]);
        }

        DB::transaction(function () use ($persona, $nuevos, $datos, $request) {
            // Bloquea a la persona para que dos programaciones simultáneas no creen turnos cruzados
            User::whereKey($persona->id)->lockForUpdate()->first();

            $existentes = Turno::query()
                ->where('user_id', $persona->id)
                ->queSeCruzanCon($nuevos->first()['inicia_at'], $nuevos->last()['termina_at'])
                ->get();

            $choques = $nuevos->filter(fn ($nuevo) => $existentes->contains(
                fn (Turno $turno) => $turno->inicia_at->lt($nuevo['termina_at']) && $turno->termina_at->gt($nuevo['inicia_at'])
            ));

            if ($choques->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'dias' => __('mensajes.turno.choque', [
                        'nombre' => $persona->name,
                        'fechas' => $choques->map(fn ($nuevo) => $nuevo['inicia_at']->format('d/m'))->join(', ', ' '.__('comun.y').' '),
                    ]),
                ]);
            }

            foreach ($nuevos as $nuevo) {
                Turno::create($nuevo + [
                    'user_id' => $persona->id,
                    'rol' => $datos['rol'],
                    'asignado_por' => $request->user()->id,
                ]);
            }
        });

        return redirect()->route('admin.turnos.index', ['semana' => $desde->toDateString()])
            ->with('estado', __('mensajes.turno.programados', ['cantidad' => $nuevos->count(), 'rol' => $datos['rol'], 'nombre' => $persona->name]));
    }

    public function destroy(Turno $turno): RedirectResponse
    {
        if ($turno->haEmpezado()) {
            return back()->withErrors(['turno' => __('mensajes.turno.turno_ya_empezo_no_puede')]);
        }

        $turno->delete();

        return back()->with('estado', __('mensajes.turno.turno_eliminado'));
    }

    /**
     * @param  array<int, int|string>  $dias  Días ISO (1 = lunes … 7 = domingo)
     * @return Collection<int, array{inicia_at: CarbonImmutable, termina_at: CarbonImmutable}>
     */
    private function generarTurnos(CarbonImmutable $desde, CarbonImmutable $hasta, array $dias, string $horaInicio, string $horaFin)
    {
        $dias = array_map('intval', $dias);

        return collect(CarbonPeriod::create($desde, $hasta))
            ->map(fn ($dia) => CarbonImmutable::instance($dia))
            ->filter(fn (CarbonImmutable $dia) => in_array($dia->dayOfWeekIso, $dias, true))
            ->map(function (CarbonImmutable $dia) use ($horaInicio, $horaFin) {
                $inicio = $dia->setTimeFromTimeString($horaInicio);
                $fin = $dia->setTimeFromTimeString($horaFin);

                // Si la salida es antes que la entrada, el turno termina al día siguiente (turno nocturno)
                return ['inicia_at' => $inicio, 'termina_at' => $fin->lte($inicio) ? $fin->addDay() : $fin];
            })
            ->filter(fn ($turno) => $turno['termina_at']->isFuture())
            ->values();
    }

    /** Personas aprobadas con rol de trabajador o celador. */
    private function personalPorTurnos()
    {
        return User::query()->with('roles')->deCampo()->orderBy('name')->get();
    }
}
