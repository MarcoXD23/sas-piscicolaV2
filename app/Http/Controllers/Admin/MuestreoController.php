<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Siembra;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MuestreoController extends Controller
{
    public function store(Request $request, Siembra $siembra): RedirectResponse
    {
        $datos = $request->validate([
            'fecha' => ['required', 'date', 'before_or_equal:today', 'after_or_equal:' . $siembra->fecha->format('Y-m-d')],
            'peces_pesados' => ['required', 'integer', 'min:1'],
            'peso_total_g' => ['required', 'numeric', 'min:0.1'],
        ]);

        DB::transaction(function () use ($datos, $siembra, $request) {
            $pesoPromedio = $datos['peso_total_g'] / $datos['peces_pesados'];

            $siembra->muestreos()->create([
                'user_id' => $request->user()->id,
                'fecha' => $datos['fecha'],
                'peces_pesados' => $datos['peces_pesados'],
                'peso_total_g' => $datos['peso_total_g'],
                'peso_promedio_g' => $pesoPromedio,
            ]);

            // Actualizar la siembra bloqueando el registro para evitar condiciones de carrera
            $siembraBloqueada = Siembra::where('id', $siembra->id)->lockForUpdate()->first();
            $siembraBloqueada->peso_actual_g = $pesoPromedio;
            $siembraBloqueada->biomasa_kg = ($siembraBloqueada->peces_vivos * $pesoPromedio) / 1000;
            $siembraBloqueada->save();
        });

        return back()->with('estado', 'Muestreo registrado y biomasa recalculada.');
    }
}
