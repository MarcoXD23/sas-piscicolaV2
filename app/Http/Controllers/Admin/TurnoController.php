<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Rol;
use App\Models\Turno;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
        $semana = CarbonImmutable::parse($request->query('semana', now()->toDateString()))->startOfWeek();
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
            'desde' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'hasta' => ['required', 'date_format:Y-m-d', 'after_or_equal:desde'],
            'dias' => ['required', 'array', 'min:1'],
            'dias.*' => ['integer', 'between:1,7', 'distinct'],
            'hora_inicio' => ['required', 'date_format:H:i'],
            'hora_fin' => ['required', 'date_format:H:i', 'different:hora_inicio'],
        ], [
            'user_id.required' => 'Elige la persona.',
            'rol.in' => 'El turno debe ser de trabajador o de celador.',
            'desde.after_or_equal' => 'No se pueden programar turnos en fechas pasadas.',
            'hasta.after_or_equal' => 'La fecha final debe ser igual o posterior a la inicial.',
            'dias.required' => 'Elige al menos un día de la semana.',
            'hora_fin.different' => 'La hora de salida debe ser distinta a la de entrada.',
            'required' => 'Este dato es obligatorio.',
            'date_format' => 'El formato no es válido.',
        ]);

        $desde = CarbonImmutable::parse($datos['desde']);
        $hasta = CarbonImmutable::parse($datos['hasta']);

        if ($desde->diffInDays($hasta) >= self::MAX_DIAS_RANGO) {
            throw ValidationException::withMessages(['hasta' => 'Programa como máximo '.self::MAX_DIAS_RANGO.' días a la vez.']);
        }

        $persona = User::query()->with('roles')->find($datos['user_id']);
        if ($persona === null || ! $persona->estaAprobado() || ! $persona->tieneRol($datos['rol'])) {
            throw ValidationException::withMessages(['user_id' => "Esa persona no tiene el rol de {$datos['rol']}."]);
        }

        $nuevos = $this->generarTurnos($desde, $hasta, $datos['dias'], $datos['hora_inicio'], $datos['hora_fin']);
        if ($nuevos->isEmpty()) {
            throw ValidationException::withMessages(['dias' => 'No hay turnos por crear: ningún día elegido cae dentro de las fechas (o ya pasaron).']);
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
                    'dias' => 'Se cruza con turnos que '.$persona->name.' ya tiene el '
                        .$choques->map(fn ($nuevo) => $nuevo['inicia_at']->format('d/m'))->join(', ', ' y ').'.',
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
            ->with('estado', "Se programaron {$nuevos->count()} turnos de {$datos['rol']} para {$persona->name}.");
    }

    public function destroy(Turno $turno): RedirectResponse
    {
        if ($turno->haEmpezado()) {
            return back()->withErrors(['turno' => 'Un turno que ya empezó no se puede eliminar: queda en el historial.']);
        }

        $turno->delete();

        return back()->with('estado', 'Turno eliminado.');
    }

    /**
     * @param  array<int, int|string>  $dias  Días ISO (1 = lunes … 7 = domingo)
     * @return \Illuminate\Support\Collection<int, array{inicia_at: CarbonImmutable, termina_at: CarbonImmutable}>
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
