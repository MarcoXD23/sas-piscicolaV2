<?php

namespace App\Http\Controllers\Noche;

use App\Http\Controllers\Controller;
use App\Models\Aireacion;
use App\Models\Lago;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AireacionController extends Controller
{
    public function encender(Request $request, Lago $lago): RedirectResponse
    {
        $datos = $request->validate(['motivo' => ['nullable', 'string', 'max:255']]);

        DB::transaction(function () use ($lago, $datos, $request) {
            // Bloquea el lago para que dos encendidos simultáneos no pasen la verificación
            Lago::whereKey($lago->id)->lockForUpdate()->first();

            if ($lago->aireacionActiva()->exists()) {
                throw ValidationException::withMessages(['aireador' => __('mensajes.aireacion.ya_encendido', ['lago' => $lago->nombre])]);
            }

            Aireacion::create([
                'lago_id' => $lago->id,
                'user_id' => $request->user()->id,
                'encendido_at' => now(),
                'motivo' => $datos['motivo'] ?? null,
            ]);
        });

        return redirect()->route('noche.index')->with('estado', __('mensajes.aireacion.encendido', ['lago' => $lago->nombre]));
    }

    public function apagar(Request $request, Aireacion $aireacion): RedirectResponse
    {
        DB::transaction(function () use ($aireacion, $request) {
            $bloqueada = Aireacion::whereKey($aireacion->id)->lockForUpdate()->first();

            if (! $bloqueada->estaEncendido()) {
                throw ValidationException::withMessages(['aireador' => __('mensajes.aireacion.ese_aireador_ya_estaba_apagado')]);
            }

            $bloqueada->update(['apagado_at' => now(), 'apagado_por' => $request->user()->id]);
        });

        return redirect()->route('noche.index')->with('estado', __('mensajes.aireacion.apagado', ['lago' => $aireacion->lago->nombre]));
    }
}
