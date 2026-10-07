<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AvisoPesca;
use App\Models\Bitacora;
use App\Models\Lago;
use App\Models\Rol;
use App\Support\MomentoRegistro;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Aviso «listo para pescar»: el trabajador lo reporta en su turno y el administrador lo atiende.
 */
class AvisoPescaController extends Controller
{
    public function store(Request $request, Lago $lago): RedirectResponse
    {
        $datos = $request->validate([
            'siembra_id' => ['required', 'integer', Rule::exists('siembras', 'id')
                ->where('tenant_id', $request->user()->tenant_id)->where('lago_id', $lago->id)->whereNull('finalizada_at')],
            'nota' => ['nullable', 'string', 'max:500'],
        ], [
            'siembra_id.exists' => __('mensajes.aviso_pesca.siembra_no_activa'),
        ]);

        DB::transaction(function () use ($datos, $lago, $request) {
            // Bloquea el lago: dos reportes simultáneos no pueden dejar dos avisos pendientes
            Lago::whereKey($lago->id)->lockForUpdate()->first();
            if (AvisoPesca::query()->pendientes()->where('lago_id', $lago->id)->exists()) {
                throw ValidationException::withMessages(['nota' => __('mensajes.aviso_pesca.ya_hay_pendiente')]);
            }

            $aviso = AvisoPesca::create($datos + [
                'lago_id' => $lago->id,
                'user_id' => $request->user()->id,
                'estado' => AvisoPesca::PENDIENTE,
                'reportado_at' => MomentoRegistro::actual(),   // si se hizo sin conexión, la hora del teléfono
            ]);

            Bitacora::registrar($request->user(), 'aviso_pesca', $lago, [Rol::TRABAJADOR], [
                'especie' => $aviso->siembra->nombre_especie, 'nota' => (string) ($datos['nota'] ?? ''),
            ]);
        });

        return back()->with('estado', __('mensajes.aviso_pesca.reportado', ['lago' => $lago->nombre]));
    }

    public function atender(Request $request, AvisoPesca $aviso): RedirectResponse
    {
        DB::transaction(function () use ($aviso, $request) {
            $bloqueado = AvisoPesca::whereKey($aviso->id)->lockForUpdate()->first();
            if (! $bloqueado->estaPendiente()) {
                throw ValidationException::withMessages(['aviso' => __('mensajes.aviso_pesca.ya_atendido')]);
            }

            $bloqueado->update(['estado' => AvisoPesca::ATENDIDO, 'atendido_at' => now(), 'atendido_por' => $request->user()->id]);

            Bitacora::registrar($request->user(), 'aviso_atendido', $bloqueado->lago, [Rol::ADMINISTRADOR], [
                'especie' => $bloqueado->siembra->nombre_especie,
            ]);
        });

        return back()->with('estado', __('mensajes.aviso_pesca.atendido'));
    }
}
