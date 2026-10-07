<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Bitacora;
use App\Models\Lago;
use App\Models\MovimientoAlimento;
use App\Models\Rol;
use App\Models\StockAlimento;
use App\Support\Formato;
use App\Support\Region;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RacionController extends Controller
{
    public function store(Request $request, Lago $lago): RedirectResponse
    {
        $datos = $request->validate([
            'fecha' => ['required', 'date', 'before_or_equal:'.Region::hoy()->toDateString()],
            'tipo_concentrado' => ['required', 'string', 'max:255'],
            'cantidad_kg' => ['required', 'numeric', 'min:0.1'],
        ]);

        if (! $lago->siembrasActivas()->exists()) {
            throw ValidationException::withMessages(['cantidad_kg' => __('mensajes.racion.este_lago_no_tiene_peces')]);
        }

        DB::transaction(function () use ($datos, $lago, $request) {
            $stock = StockAlimento::where('tipo_concentrado', $datos['tipo_concentrado'])->lockForUpdate()->first();

            // El stock es solo lo recibido en bodega: sin recepción no se puede alimentar con ese concentrado
            if (! $stock || (float) $stock->cantidad_kg <= 0) {
                throw ValidationException::withMessages(['cantidad_kg' => __('mensajes.racion.sin_concentrado_recibido')]);
            }
            if ((float) $stock->cantidad_kg < (float) $datos['cantidad_kg']) {
                throw ValidationException::withMessages([
                    'cantidad_kg' => __('mensajes.racion.stock_insuficiente', ['kg' => Formato::numero($stock->cantidad_kg, 2), 'concentrado' => $stock->tipo_concentrado]),
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

            Bitacora::registrar($request->user(), 'racion', $lago, [Rol::ADMINISTRADOR, Rol::TRABAJADOR], [
                'kg' => (float) $datos['cantidad_kg'], 'concentrado' => $datos['tipo_concentrado'], 'fecha' => $datos['fecha'],
            ]);
        });

        return back()->with('estado', __('mensajes.racion.registrada', ['kg' => Formato::numero($datos['cantidad_kg'], 1), 'lago' => $lago->nombre]));
    }
}
