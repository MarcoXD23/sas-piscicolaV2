<?php

namespace App\Http\Controllers;

use App\Models\Especie;
use App\Models\Rol;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Guía de especies: las especies del catálogo de ESTA finca (nunca una lista fija), con su ficha.
 */
class EspecieController extends Controller
{
    public function index(): View
    {
        return view('especies.index', [
            'especies' => Especie::query()->activas()->with('fotos')->orderBy('nombre')->get()->map(fn (Especie $especie) => $especie->ficha()),
        ]);
    }

    public function show(Request $request, Especie $especie): View
    {
        return view('especies.show', [
            'especie' => $especie->ficha(),
            'fotos' => $especie->fotos,
            // El original solo lo descargan el propietario y el administrador
            'veOriginal' => $request->user()->tieneRol(Rol::PROPIETARIO, Rol::ADMINISTRADOR),
        ]);
    }
}
