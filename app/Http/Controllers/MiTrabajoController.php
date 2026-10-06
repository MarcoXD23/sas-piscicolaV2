<?php

namespace App\Http\Controllers;

use App\Models\HoraExtra;
use App\Models\Permiso;
use App\Models\Tarea;
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
                ->whereBetween('fecha', [today()->startOfMonth(), today()->endOfMonth()])->sum('horas'),
            'enTurno' => $usuario->turnos()->enCurso()->exists(),
        ]);
    }

    /** Las tareas se marcan como hechas solo durante un turno y solo las propias. */
    public function completar(Request $request, Tarea $tarea): RedirectResponse
    {
        $usuario = $request->user();
        abort_unless($tarea->asignada_a === $usuario->id, 403, 'Esta tarea no está asignada a ti.');
        abort_unless($usuario->turnos()->enCurso()->exists(), 403, 'Estás fuera de tu turno. Las tareas se marcan durante tu turno.');

        $datos = $request->validate(['nota_cierre' => ['nullable', 'string', 'max:1000']]);

        DB::transaction(function () use ($tarea, $datos) {
            $bloqueada = Tarea::whereKey($tarea->id)->lockForUpdate()->first();

            if ($bloqueada->estaCompletada()) {
                throw ValidationException::withMessages(['tarea' => 'Esta tarea ya estaba completada.']);
            }

            $bloqueada->update(['completada_at' => now(), 'nota_cierre' => $datos['nota_cierre'] ?? null]);
        });

        return redirect()->route('mi-trabajo.index')->with('estado', "Tarea «{$tarea->titulo}» completada.");
    }

    public function solicitarPermiso(Request $request): RedirectResponse
    {
        $usuario = $request->user();

        $datos = $request->validate([
            'fecha_inicio' => ['required', 'date', 'after_or_equal:today'],
            'fecha_fin' => ['required', 'date', 'after_or_equal:fecha_inicio'],
            'motivo' => ['required', 'string', 'max:255'],
        ], [
            'fecha_inicio.after_or_equal' => 'El permiso no puede empezar en una fecha pasada.',
            'fecha_fin.after_or_equal' => 'La fecha final debe ser igual o posterior a la inicial.',
            'motivo.required' => 'Escribe el motivo.',
            'required' => 'Este dato es obligatorio.',
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
                throw ValidationException::withMessages(['fecha_inicio' => 'Ya tienes un permiso pendiente o aprobado en esas fechas.']);
            }

            Permiso::create($datos + ['user_id' => $usuario->id, 'estado' => 'pendiente']);
        });

        return redirect()->route('mi-trabajo.index')->with('estado', 'Permiso solicitado. El propietario lo revisará.');
    }

    public function reportarHoras(Request $request): RedirectResponse
    {
        $datos = $request->validate([
            'fecha' => ['required', 'date', 'before_or_equal:today', 'after_or_equal:'.today()->subDays(self::DIAS_REPORTE_HORAS)->toDateString()],
            'horas' => ['required', 'numeric', 'min:0.5', 'max:12'],
            'motivo' => ['required', 'string', 'max:255'],
        ], [
            'fecha.before_or_equal' => 'No se reportan horas de días que aún no pasan.',
            'fecha.after_or_equal' => 'Solo se reportan horas de los últimos '.self::DIAS_REPORTE_HORAS.' días.',
            'horas.min' => 'El mínimo es media hora.',
            'horas.max' => 'El máximo es 12 horas por día.',
            'motivo.required' => 'Escribe en qué trabajaste.',
            'required' => 'Este dato es obligatorio.',
        ]);

        HoraExtra::create($datos + ['user_id' => $request->user()->id, 'estado' => 'pendiente']);

        return redirect()->route('mi-trabajo.index')->with('estado', 'Horas extra reportadas. El propietario las revisará.');
    }
}
