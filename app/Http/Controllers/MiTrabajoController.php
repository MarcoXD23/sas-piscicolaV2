<?php

namespace App\Http\Controllers;

use App\Models\HoraExtra;
use App\Models\Liquidacion;
use App\Models\Permiso;
use App\Models\Tarea;
use App\Models\Venta;
use App\Support\Region;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Autogestión del personal de campo (trabajador y celador): sus tareas, permisos y horas extra.
 */
class MiTrabajoController extends Controller
{
    /** Días hacia atrás en que se pueden reportar horas extra. */
    public const DIAS_REPORTE_HORAS = 30;

    public function index(Request $request): View
    {
        $usuario = $request->user();

        return view('mi-trabajo.index', [
            'pendientes' => Tarea::query()->with('lago')->where('asignada_a', $usuario->id)->pendientes()->orderBy('fecha')->get(),
            'completadas' => Tarea::query()->with('lago')->where('asignada_a', $usuario->id)->whereNotNull('completada_at')
                ->latest('completada_at')->limit(10)->get(),
            'permisos' => Permiso::query()->where('user_id', $usuario->id)->latest('fecha_inicio')->limit(10)->get(),
            'horasExtras' => HoraExtra::query()->where('user_id', $usuario->id)->latest('fecha')->limit(10)->get(),
            'horasAprobadasMes' => (float) HoraExtra::query()->where('user_id', $usuario->id)->aprobadas()
                ->whereBetween('fecha', [Region::hoy()->startOfMonth()->toDateString(), Region::hoy()->endOfMonth()->toDateString()])->sum('horas'),
            'enTurno' => $usuario->turnos()->enCurso()->exists(),
            'liquidaciones' => Liquidacion::query()->where('user_id', $usuario->id)->latest('semana_inicio')->limit(8)->get(),
            // Pescado que la persona compró a crédito y aún debe
            'creditosPescado' => Venta::query()->where('comprador_id', $usuario->id)->conSaldo()->orderBy('fecha')->get(),
        ]);
    }

    /** Las tareas se marcan como hechas solo durante un turno y solo las propias. */
    public function completar(Request $request, Tarea $tarea): RedirectResponse
    {
        $usuario = $request->user();
        abort_unless($tarea->asignada_a === $usuario->id, 403, __('mensajes.mi_trabajo.esta_tarea_no_esta_asignada'));
        abort_unless($usuario->turnos()->enCurso()->exists(), 403, __('mensajes.mi_trabajo.estas_fuera_tu_turno_tareas'));

        $datos = $request->validate(['nota_cierre' => ['nullable', 'string', 'max:1000']]);

        DB::transaction(function () use ($tarea, $datos) {
            $bloqueada = Tarea::whereKey($tarea->id)->lockForUpdate()->first();

            if ($bloqueada->estaCompletada()) {
                throw ValidationException::withMessages(['tarea' => __('mensajes.mi_trabajo.esta_tarea_ya_estaba_completada')]);
            }

            $bloqueada->update(['completada_at' => now(), 'nota_cierre' => $datos['nota_cierre'] ?? null]);
        });

        return redirect()->route('mi-trabajo.index')->with('estado', __('mensajes.mi_trabajo.tarea_completada', ['titulo' => $tarea->titulo]));
    }

    public function solicitarPermiso(Request $request): RedirectResponse
    {
        $usuario = $request->user();

        $datos = $request->validate([
            'fecha_inicio' => ['required', 'date', 'after_or_equal:'.Region::hoy()->toDateString()],
            'fecha_fin' => ['required', 'date', 'after_or_equal:fecha_inicio'],
            'motivo' => ['required', 'string', 'max:255'],
        ], [
            'fecha_inicio.after_or_equal' => __('mensajes.mi_trabajo.permiso_no_puede_empezar_fecha'),
            'fecha_fin.after_or_equal' => __('mensajes.mi_trabajo.fecha_final_debe_ser_igual'),
            'motivo.required' => __('mensajes.mi_trabajo.escribe_motivo'),
            'required' => __('mensajes.mi_trabajo.este_dato_es_obligatorio'),
        ]);

        DB::transaction(function () use ($usuario, $datos) {
            // Bloquea a la persona para no crear dos permisos cruzados al mismo tiempo
            $usuario->newQuery()->whereKey($usuario->id)->lockForUpdate()->first();

            $cruzado = Permiso::query()
                ->where('user_id', $usuario->id)
                ->whereIn('estado', ['pendiente', 'aprobado'])
                ->where('fecha_inicio', '<=', $datos['fecha_fin'])
                ->where('fecha_fin', '>=', $datos['fecha_inicio'])
                ->exists();

            if ($cruzado) {
                throw ValidationException::withMessages(['fecha_inicio' => __('mensajes.mi_trabajo.ya_tienes_permiso_pendiente_aprobado')]);
            }

            Permiso::create($datos + ['user_id' => $usuario->id, 'estado' => 'pendiente']);
        });

        return redirect()->route('mi-trabajo.index')->with('estado', __('mensajes.mi_trabajo.permiso_solicitado_propietario_revisara'));
    }

    public function reportarHoras(Request $request): RedirectResponse
    {
        $datos = $request->validate([
            'fecha' => ['required', 'date', 'before_or_equal:'.Region::hoy()->toDateString(), 'after_or_equal:'.Region::hoy()->subDays(self::DIAS_REPORTE_HORAS)->toDateString()],
            'horas' => ['required', 'numeric', 'min:0.5', 'max:12'],
            'motivo' => ['required', 'string', 'max:255'],
        ], [
            'fecha.before_or_equal' => __('mensajes.mi_trabajo.no_reportan_horas_dias_aun'),
            'fecha.after_or_equal' => __('mensajes.mi_trabajo.horas_ultimos_dias', ['dias' => self::DIAS_REPORTE_HORAS]),
            'horas.min' => __('mensajes.mi_trabajo.minimo_es_media_hora'),
            'horas.max' => __('mensajes.mi_trabajo.maximo_es_12_horas_dia'),
            'motivo.required' => __('mensajes.mi_trabajo.escribe_trabajaste'),
            'required' => __('mensajes.mi_trabajo.este_dato_es_obligatorio'),
        ]);

        HoraExtra::create($datos + ['user_id' => $request->user()->id, 'estado' => 'pendiente']);

        return redirect()->route('mi-trabajo.index')->with('estado', __('mensajes.mi_trabajo.horas_extra_reportadas_propietario_revisara'));
    }
}
