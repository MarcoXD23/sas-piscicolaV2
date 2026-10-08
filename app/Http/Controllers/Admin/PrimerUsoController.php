<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\PrimerUso;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Oculta la lista de primer uso para toda la finca (propietario o administrador). No vuelve a aparecer.
 */
class PrimerUsoController extends Controller
{
    public function ocultar(Request $request): RedirectResponse
    {
        PrimerUso::marcar($request->user()->tenant, 'primer_uso_oculto_at');

        return redirect()->route('inicio')->with('estado', __('primer_uso.ocultada'));
    }
}
