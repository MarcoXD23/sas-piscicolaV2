<?php

namespace App\Http\Controllers;

use App\Models\Especie;
use Illuminate\View\View;

/**
 * Guía de especies: las especies del catálogo de ESTA finca (nunca una lista fija), con su ficha.
 */
class EspecieController extends Controller
{
    public function index(): View
    {
        return view('especies.index', [
            'especies' => Especie::query()->activas()->orderBy('nombre')->get()->map(fn (Especie $especie) => $especie->ficha()),
        ]);
    }

    public function show(Especie $especie): View
    {
        return view('especies.show', ['especie' => $especie->ficha()]);
    }
}
