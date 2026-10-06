<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\HoraExtra;
use App\Models\Permiso;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Revisión de permisos y horas extra (exclusivo del propietario).
 */
class SolicitudController extends Controller
{
    public function index(): View
    {
        $permisos = Permiso::query()->with('usuario')->pendientes()->orderBy('fecha_inicio')->get();

        return view('admin.solicitudes.index', [
            'permisos' => $permisos,
            'turnosAfectados' => $permisos->mapWithKeys(fn (Permiso $permiso) => [$permiso->id => $permiso->turnosAfectados()->count()]),
            'horasExtras' => HoraExtra::query()->with('usuario')->pendientes()->orderBy('fecha')->get(),
            'revisadas' => Permiso::query()->with(['usuario', 'revisor'])->where('estado', '!=', 'pendiente')->latest('revisado_at')->limit(10)->get()
                ->concat(HoraExtra::query()->with(['usuario', 'revisor'])->where('estado', '!=', 'pendiente')->latest('revisado_at')->limit(10)->get())
                ->sortByDesc('revisado_at')->take(10)->values(),
        ]);
    }

    public function revisarPermiso(Request $request, Permiso $permiso): RedirectResponse
    {
        $this->revisar($request, $permiso);

        $mensaje = $permiso->estado === 'aprobado' ? "Permiso de {$permiso->usuario->name} aprobado." : "Permiso de {$permiso->usuario->name} rechazado.";
        $turnos = $permiso->estado === 'aprobado' ? $permiso->turnosAfectados()->count() : 0;

        return back()->with('estado', $turnos > 0
            ? $mensaje." Tiene {$turnos} turno(s) en esas fechas: reprográmalos en Turnos."
            : $mensaje);
    }

    public function revisarHoras(Request $request, HoraExtra $horaExtra): RedirectResponse
    {
        $this->revisar($request, $horaExtra);

        return back()->with('estado', "Horas extra de {$horaExtra->usuario->name} ".($horaExtra->estado === 'aprobado' ? 'aprobadas.' : 'rechazadas.'));
    }

    /** Aprueba o rechaza una solicitud pendiente, en transacción y una sola vez. */
    private function revisar(Request $request, Model $solicitud): void
    {
        $datos = $request->validate([
            'decision' => ['required', Rule::in(['aprobado', 'rechazado'])],
            'respuesta' => ['nullable', 'string', 'max:255', 'required_if:decision,rechazado'],
        ], [
            'decision.in' => 'Elige aprobar o rechazar.',
            'respuesta.required_if' => 'Escribe el motivo del rechazo.',
        ]);

        DB::transaction(function () use ($solicitud, $datos, $request) {
            $bloqueada = $solicitud->newQuery()->whereKey($solicitud->getKey())->lockForUpdate()->first();

            if (! $bloqueada->estaPendiente()) {
                throw ValidationException::withMessages(['decision' => 'Esta solicitud ya fue revisada.']);
            }

            $bloqueada->update([
                'estado' => $datos['decision'],
                'respuesta' => $datos['respuesta'] ?? null,
                'revisado_por' => $request->user()->id,
                'revisado_at' => now(),
            ]);
        });

        $solicitud->refresh();
    }
}
