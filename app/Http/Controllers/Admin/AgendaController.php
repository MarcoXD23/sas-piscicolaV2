<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EventoAgenda;
use App\Models\Lago;
use App\Models\Permiso;
use App\Models\Tratamiento;
use App\Support\Region;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Agenda operativa de la finca (exclusiva del propietario).
 */
class AgendaController extends Controller
{
    public function index(Request $request): View
    {
        $mes = $this->mes($request->query('mes'));
        $desde = $mes->startOfWeek();
        $hasta = $mes->endOfMonth()->endOfWeek();

        return view('admin.agenda.index', [
            'mes' => $mes,
            'dias' => collect(CarbonPeriod::create($desde, $hasta->startOfDay()))->map(fn ($dia) => CarbonImmutable::instance($dia)),
            'porDia' => $this->elementosPorDia($desde, $hasta),
            'tipos' => EventoAgenda::opciones(),
            'lagos' => Lago::query()->orderBy('nombre')->pluck('nombre', 'id')->all(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $evento = EventoAgenda::create($this->datosEvento($request) + ['creado_por' => $request->user()->id]);

        return redirect()->route('admin.agenda.index', ['mes' => $evento->inicia_at->format('Y-m')])
            ->with('estado', "«{$evento->titulo}» agendado para el {$evento->inicia_at->format('d/m/Y')}.");
    }

    public function update(Request $request, EventoAgenda $evento): RedirectResponse
    {
        if ($evento->estaCompletado()) {
            return back()->withErrors(['evento' => __('mensajes.agenda.evento_ya_hecho_no_edita')]);
        }

        $evento->update($this->datosEvento($request));

        return redirect()->route('admin.agenda.index', ['mes' => $evento->inicia_at->format('Y-m')])
            ->with('estado', "«{$evento->titulo}» actualizado para el {$evento->inicia_at->format('d/m/Y')}.");
    }

    /**
     * Valida el formulario del evento (el mismo para crear y editar) y lo convierte en atributos.
     *
     * @return array<string, mixed>
     */
    private function datosEvento(Request $request): array
    {
        $datos = $request->validate([
            'titulo' => ['required', 'string', 'max:255'],
            'tipo' => ['required', 'string', Rule::in(EventoAgenda::TIPOS)],
            'fecha' => ['required', 'date_format:Y-m-d'],
            'hora' => ['nullable', 'date_format:H:i'],
            'hora_fin' => ['nullable', 'date_format:H:i'],
            'lago_id' => ['nullable', 'integer', Rule::exists('lagos', 'id')->where('tenant_id', $request->user()->tenant_id)],
            'recordar_dias_antes' => ['required', 'integer', 'between:0,60'],
            'descripcion' => ['nullable', 'string', 'max:1000'],
        ], [
            'titulo.required' => __('mensajes.agenda.escribe_titulo'),
            'tipo.in' => __('mensajes.agenda.elige_tipo_valido'),
            'recordar_dias_antes.between' => __('mensajes.agenda.aviso_puede_ser_0_60'),
        ]);

        if (! empty($datos['hora_fin'])) {
            if (empty($datos['hora'])) {
                throw ValidationException::withMessages(['hora' => __('mensajes.agenda.si_pones_hora_final_pon')]);
            }
            if ($datos['hora_fin'] <= $datos['hora']) {
                throw ValidationException::withMessages(['hora_fin' => __('mensajes.agenda.hora_final_debe_ser_posterior')]);
            }
        }

        // La fecha y la hora del formulario son de la zona de la finca (el cast las guarda en UTC)
        $fecha = CarbonImmutable::parse($datos['fecha'], Region::zona());

        return [
            'lago_id' => $datos['lago_id'] ?? null,
            'titulo' => $datos['titulo'],
            'tipo' => $datos['tipo'],
            'todo_el_dia' => empty($datos['hora']),
            'inicia_at' => empty($datos['hora']) ? $fecha->startOfDay() : $fecha->setTimeFromTimeString($datos['hora']),
            'termina_at' => empty($datos['hora_fin']) ? null : $fecha->setTimeFromTimeString($datos['hora_fin']),
            'recordar_dias_antes' => $datos['recordar_dias_antes'],
            'descripcion' => $datos['descripcion'] ?? null,
        ];
    }

    public function completar(EventoAgenda $evento): RedirectResponse
    {
        if (! $evento->estaCompletado()) {
            $evento->update(['completado_at' => now()]);
        }

        return back()->with('estado', "«{$evento->titulo}» marcado como hecho.");
    }

    public function destroy(EventoAgenda $evento): RedirectResponse
    {
        if ($evento->estaCompletado()) {
            return back()->withErrors(['evento' => __('mensajes.agenda.evento_ya_hecho_no_elimina')]);
        }

        $evento->delete();

        return back()->with('estado', __('mensajes.agenda.evento_eliminado'));
    }

    private function mes(?string $valor): CarbonImmutable
    {
        if ($valor !== null && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $valor)) {
            return CarbonImmutable::createFromFormat('!Y-m', $valor, Region::zona())->startOfMonth();
        }

        return Region::hoy()->startOfMonth();
    }

    /**
     * Eventos de la agenda más los elementos que vienen de otros módulos (solo lectura):
     * fin del retiro sanitario de los tratamientos y permisos aprobados del personal.
     *
     * @return Collection<string, Collection<int, array{hora: ?string, titulo: string, color: string, evento: ?EventoAgenda}>>
     */
    private function elementosPorDia(CarbonImmutable $desde, CarbonImmutable $hasta): Collection
    {
        $elementos = collect();

        EventoAgenda::query()->with('lago')->whereBetween('inicia_at', [$desde, $hasta])->orderBy('inicia_at')->get()
            ->each(fn (EventoAgenda $evento) => $elementos->push([
                'fecha' => $evento->inicia_at->toDateString(),
                'hora' => $evento->todo_el_dia ? null : $evento->inicia_at->format('H:i'),
                'titulo' => $evento->titulo.($evento->lago ? " · {$evento->lago->nombre}" : ''),
                'color' => $evento->tipo,
                'evento' => $evento,
            ]));

        Tratamiento::query()->with('lago')->where('dias_retiro', '>', 0)
            ->whereRaw('fecha_fin + dias_retiro BETWEEN ? AND ?', [$desde->toDateString(), $hasta->toDateString()])
            ->get()
            ->each(fn (Tratamiento $tratamiento) => $elementos->push([
                'fecha' => $tratamiento->finRetiro()->toDateString(),
                'hora' => null,
                'titulo' => __('mensajes.agenda.fin_retiro', ['lago' => $tratamiento->lago->nombre]),
                'color' => 'retiro',
                'evento' => null,
            ]));

        Permiso::query()->with('usuario')->aprobadas()
            ->where('fecha_inicio', '<=', $hasta->toDateString())
            ->where('fecha_fin', '>=', $desde->toDateString())
            ->get()
            ->each(function (Permiso $permiso) use ($elementos, $desde, $hasta) {
                // Días de calendario: se comparan como texto Y-m-d para no cambiar de día por la zona
                $inicio = max($permiso->fecha_inicio->toDateString(), $desde->toDateString());
                $fin = min($permiso->fecha_fin->toDateString(), $hasta->toDateString());

                foreach (CarbonPeriod::create($inicio, $fin) as $dia) {
                    $elementos->push(['fecha' => $dia->toDateString(), 'hora' => null,
                        'titulo' => __('mensajes.agenda.permiso_de', ['nombre' => $permiso->usuario->name]), 'color' => 'permiso', 'evento' => null]);
                }
            });

        return $elementos->groupBy('fecha');
    }
}
