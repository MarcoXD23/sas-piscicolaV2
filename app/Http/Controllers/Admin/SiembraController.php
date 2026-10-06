<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Lago;
use App\Models\Siembra;
use App\Support\CatalogoEspecies;
use App\Support\ReglasPolicultivo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SiembraController extends Controller
{
    public function store(Request $request, Lago $lago): RedirectResponse
    {
        $datos = $request->validate([
            'especie' => ['required', 'string', Rule::in(CatalogoEspecies::claves())],
            'fecha' => ['required', 'date', 'before_or_equal:today'],
            'cantidad_alevinos' => ['required', 'integer', 'min:1'],
            'peso_promedio_inicial_g' => ['required', 'numeric', 'min:0.1'],
        ]);

        DB::transaction(function () use ($lago, $datos) {
            // Bloquea el lago para que dos siembras simultáneas no se salten las reglas de policultivo
            Lago::whereKey($lago->id)->lockForUpdate()->first();
            $activas = $lago->siembrasActivas()->get();

            if ($activas->contains('especie', $datos['especie'])) {
                throw ValidationException::withMessages([
                    'especie' => 'Este lago ya tiene una siembra activa de '.CatalogoEspecies::nombre($datos['especie']).'.',
                ]);
            }

            ReglasPolicultivo::verificarClima($activas, $datos['especie'], 'especie');

            $peces = (int) $datos['cantidad_alevinos'];
            $peso = (float) $datos['peso_promedio_inicial_g'];

            // En la siembra, el stock actual es igual al inicial
            $lago->siembras()->create($datos + [
                'peces_vivos' => $peces,
                'peso_actual_g' => $peso,
                'biomasa_kg' => ($peces * $peso) / 1000,
            ]);
        });

        return redirect()->route('admin.lagos.index')
            ->with('estado', CatalogoEspecies::nombre($datos['especie'])." sembrada en el lago {$lago->nombre}.");
    }

    /**
     * Cierra el ciclo de una siembra (el registro se conserva como histórico) y libera su lugar en el lago.
     */
    public function finalizar(Siembra $siembra): RedirectResponse
    {
        DB::transaction(function () use ($siembra) {
            $bloqueada = Siembra::whereKey($siembra->id)->lockForUpdate()->first();

            if (! $bloqueada->estaActiva()) {
                throw ValidationException::withMessages(['siembra' => 'Esta siembra ya estaba finalizada.']);
            }

            $bloqueada->update(['finalizada_at' => now()]);
        });

        return redirect()->route('admin.lagos.index')
            ->with('estado', "Siembra de {$siembra->nombre_especie} finalizada en el lago {$siembra->lago->nombre}.");
    }
}
