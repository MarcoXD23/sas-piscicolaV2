<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Lago;
use App\Models\Siembra;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SiembraController extends Controller
{
    public function store(Request $request, Lago $lago): RedirectResponse
    {
        if ($lago->siembraActiva()->exists()) {
            return back()->withErrors(['lago' => 'Este lago ya tiene una siembra activa.']);
        }

        $datos = $request->validate([
            'especie' => ['required', 'string', Rule::in(Siembra::ESPECIES)],
            'fecha' => ['required', 'date', 'before_or_equal:today'],
            'cantidad_alevinos' => ['required', 'integer', 'min:1'],
            'peso_promedio_inicial_g' => ['required', 'numeric', 'min:0.1'],
        ]);

        $peces = (int) $datos['cantidad_alevinos'];
        $peso = (float) $datos['peso_promedio_inicial_g'];

        // En la siembra, el stock actual es igual al inicial
        $datos['peces_vivos'] = $peces;
        $datos['peso_actual_g'] = $peso;
        $datos['biomasa_kg'] = ($peces * $peso) / 1000;

        $lago->siembras()->create($datos);

        return redirect()->route('admin.lagos.index')
            ->with('estado', "Siembra registrada en el lago {$lago->nombre}.");
    }
}
