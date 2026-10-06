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

        $mensaje = __($permiso->estado === 'aprobado' ? 'mensajes.solicitud.permiso_aprobado' : 'mensajes.solicitud.permiso_rechazado', ['nombre' => $permiso->usuario->name]);
        $turnos = $permiso->estado === 'aprobado' ? $permiso->turnosAfectados()->count() : 0;

        return back()->with('estado', $turnos > 0
            ? $mensaje.' '.__('mensajes.solicitud.turnos_por_reprogramar', ['cantidad' => $turnos])
            : $mensaje);
    }

    public function revisarHoras(Request $request, HoraExtra $horaExtra): RedirectResponse
    {
        $this->revisar($request, $horaExtra);

        return back()->with('estado', __($horaExtra->estado === 'aprobado' ? 'mensajes.solicitud.horas_aprobadas' : 'mensajes.solicitud.horas_rechazadas', ['nombre' => $horaExtra->usuario->name]));
    }

    /** Aprueba o rechaza una solicitud pendiente, en transacción y una sola vez. */
    private function revisar(Request $request, Model $solicitud): void
    {
        $datos = $request->validate([
            'decision' => ['required', Rule::in(['aprobado', 'rechazado'])],
            'respuesta' => ['nullable', 'string', 'max:255', 'required_if:decision,rechazado'],
        ], [
            'decision.in' => __('mensajes.solicitud.elige_aprobar_rechazar'),
            'respuesta.required_if' => __('mensajes.solicitud.escribe_motivo_rechazo'),
        ]);

        DB::transaction(function () use ($solicitud, $datos, $request) {
            $bloqueada = $solicitud->newQuery()->whereKey($solicitud->getKey())->lockForUpdate()->first();

            if (! $bloqueada->estaPendiente()) {
                throw ValidationException::withMessages(['decision' => __('mensajes.solicitud.esta_solicitud_ya_fue_revisada')]);
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
