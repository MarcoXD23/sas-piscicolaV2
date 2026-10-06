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
                throw ValidationException::withMessages(['aireador' => "El aireador del lago {$lago->nombre} ya está encendido."]);
            }

            Aireacion::create([
                'lago_id' => $lago->id,
                'user_id' => $request->user()->id,
                'encendido_at' => now(),
                'motivo' => $datos['motivo'] ?? null,
            ]);
        });

        return redirect()->route('noche.index')->with('estado', "Aireador del lago {$lago->nombre} encendido.");
    }

    public function apagar(Request $request, Aireacion $aireacion): RedirectResponse
    {
        DB::transaction(function () use ($aireacion, $request) {
            $bloqueada = Aireacion::whereKey($aireacion->id)->lockForUpdate()->first();

            if (! $bloqueada->estaEncendido()) {
                throw ValidationException::withMessages(['aireador' => 'Ese aireador ya estaba apagado.']);
            }

            $bloqueada->update(['apagado_at' => now(), 'apagado_por' => $request->user()->id]);
        });

        return redirect()->route('noche.index')->with('estado', "Aireador del lago {$aireacion->lago->nombre} apagado.");
    }
}
