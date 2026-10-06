<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MovimientoAlimento;
use App\Models\StockAlimento;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class BodegaController extends Controller
{
    public function index(): View
    {
        $stocks = StockAlimento::orderBy('tipo_concentrado')->get();
        return view('admin.bodega.index', ['stocks' => $stocks]);
    }

    public function store(Request $request): RedirectResponse
    {
        $datos = $request->validate([
            'fecha' => ['required', 'date', 'before_or_equal:today'],
            'proveedor' => ['required', 'string', 'max:255'],
            'tipo_concentrado' => ['required', 'string', 'max:255'],
            'bultos' => ['required', 'integer', 'min:1'],
            'peso_bulto_kg' => ['required', 'numeric', 'min:1'],
            'lote' => ['required', 'string', 'max:255'],
        ]);

        DB::transaction(function () use ($datos, $request) {
            $cantidadTotal = $datos['bultos'] * $datos['peso_bulto_kg'];

            MovimientoAlimento::create([
                'user_id' => $request->user()->id,
                'fecha' => $datos['fecha'],
                'tipo_movimiento' => 'entrada',
                'tipo_concentrado' => $datos['tipo_concentrado'],
                'cantidad_kg' => $cantidadTotal,
                'proveedor' => $datos['proveedor'],
                'lote' => $datos['lote'],
                'bultos' => $datos['bultos'],
                'peso_bulto_kg' => $datos['peso_bulto_kg'],
            ]);

            $stock = StockAlimento::where('tipo_concentrado', $datos['tipo_concentrado'])->lockForUpdate()->first();

            if ($stock) {
                $stock->cantidad_kg += $cantidadTotal;
                $stock->save();
            } else {
                StockAlimento::create([
                    'tipo_concentrado' => $datos['tipo_concentrado'],
                    'cantidad_kg' => $cantidadTotal,
                ]);
            }
        });

        return redirect()->route('admin.bodega.index')->with('estado', 'Recepción de alimento registrada.');
    }
}
