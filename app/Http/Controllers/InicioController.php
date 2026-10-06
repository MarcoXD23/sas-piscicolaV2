<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;

class InicioController extends Controller
{
    public function __invoke(Request $request): View
    {
        $lagos = \App\Models\Lago::with('siembraActiva')->get();
        
        $lagosActivos = 0;
        $biomasaTotal = 0;
        $pecesVivos = 0;

        foreach ($lagos as $lago) {
            if ($lago->siembraActiva) {
                $lagosActivos++;
                $biomasaTotal += $lago->siembraActiva->biomasa_kg;
                $pecesVivos += $lago->siembraActiva->peces_vivos;
            }
        }

        return view('inicio', [
            'usuario' => $request->user()->load('roles', 'tenant'),
            'lagosActivos' => $lagosActivos,
            'biomasaTotal' => $biomasaTotal,
            'pecesVivos' => $pecesVivos,
        ]);
    }
}

