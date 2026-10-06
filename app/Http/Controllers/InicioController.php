<?php

namespace App\Http\Controllers;

use App\Models\Lago;
use App\Models\Siembra;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InicioController extends Controller
{
    public function __invoke(Request $request): View
    {
        return view('inicio', [
            'usuario' => $request->user()->load('roles', 'tenant'),
            'lagosActivos' => Lago::has('siembrasActivas')->count(),
            'biomasaTotal' => (float) Siembra::activas()->sum('biomasa_kg'),
            'pecesVivos' => (int) Siembra::activas()->sum('peces_vivos'),
        ]);
    }
}
