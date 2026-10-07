<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\CalculoPesaje;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Peso por defecto de UNA canasta y de UNA canastilla vacías de la finca.
 * Los cambia el administrador (o el propietario que también es administrador); el propietario los ve en Ajustes.
 * En cada cosecha se pueden corregir para ese día sin cambiar estos valores.
 */
class AjustesPesajeController extends Controller
{
    public function edit(Request $request): View
    {
        return view('admin.ajustes-pesaje.edit', ['finca' => $request->user()->tenant]);
    }

    public function update(Request $request): RedirectResponse
    {
        $limites = ['required', 'numeric', 'min:'.CalculoPesaje::MIN_PESO_VACIA_KG, 'max:'.CalculoPesaje::MAX_PESO_VACIA_KG];
        $datos = $request->validate(['peso_canasta_kg' => $limites, 'peso_canastilla_kg' => $limites], [
            'peso_canasta_kg.*' => __('mensajes.cosecha.peso_vacia', ['tipo' => __('cosechas.nombres.canasta')]),
            'peso_canastilla_kg.*' => __('mensajes.cosecha.peso_vacia', ['tipo' => __('cosechas.nombres.canastilla')]),
        ]);

        $request->user()->tenant->update([
            'peso_canasta_kg' => round((float) $datos['peso_canasta_kg'], 2),
            'peso_canastilla_kg' => round((float) $datos['peso_canastilla_kg'], 2),
        ]);

        return back()->with('estado', __('cosechas.ajustes.guardado'));
    }
}
