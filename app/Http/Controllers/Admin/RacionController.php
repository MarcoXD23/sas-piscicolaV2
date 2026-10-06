<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Lago;
use App\Models\MovimientoAlimento;
use App\Models\StockAlimento;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RacionController extends Controller
{
    public function store(Request $request, Lago $lago): RedirectResponse
    {
        $datos = $request->validate([
            'fecha' => ['required', 'date', 'before_or_equal:today'],
            'tipo_concentrado' => ['required', 'string', 'exists:stocks_alimento,tipo_concentrado'],
            'cantidad_kg' => ['required', 'numeric', 'min:0.1'],
        ]);

        DB::transaction(function () use ($datos, $lago, $request) {
            $stock = StockAlimento::where('tipo_concentrado', $datos['tipo_concentrado'])->lockForUpdate()->first();

            if (! $stock || $stock->cantidad_kg < $datos['cantidad_kg']) {
                throw ValidationException::withMessages([
                    'cantidad_kg' => 'No hay suficiente stock de este alimento en bodega. Disponible: ' . ($stock ? $stock->cantidad_kg : 0) . ' kg.'
                ]);
            }

            MovimientoAlimento::create([
                'user_id' => $request->user()->id,
                'lago_id' => $lago->id,
                'fecha' => $datos['fecha'],
                'tipo_movimiento' => 'salida',
                'tipo_concentrado' => $datos['tipo_concentrado'],
                'cantidad_kg' => $datos['cantidad_kg'],
            ]);

            $stock->cantidad_kg -= $datos['cantidad_kg'];
            $stock->save();
        });

        return back()->with('estado', "Ración de {$datos['cantidad_kg']} kg registrada en el lago {$lago->nombre}.");
    }
}
