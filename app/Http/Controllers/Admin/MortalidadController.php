<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Siembra;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MortalidadController extends Controller
{
    public function store(Request $request, Siembra $siembra): RedirectResponse
    {
        $datos = $request->validate([
            'fecha' => ['required', 'date', 'before_or_equal:today', 'after_or_equal:' . $siembra->fecha->format('Y-m-d')],
            'cantidad' => ['required', 'integer', 'min:1'],
        ]);

        DB::transaction(function () use ($datos, $siembra, $request) {
            $siembraBloqueada = Siembra::where('id', $siembra->id)->lockForUpdate()->first();

            if ($datos['cantidad'] > $siembraBloqueada->peces_vivos) {
                throw ValidationException::withMessages([
                    'cantidad' => 'La cantidad de mortalidad no puede ser mayor a los peces vivos (' . $siembraBloqueada->peces_vivos . ').'
                ]);
            }

            $siembraBloqueada->mortalidades()->create([
                'user_id' => $request->user()->id,
                'fecha' => $datos['fecha'],
                'cantidad' => $datos['cantidad'],
            ]);

            $siembraBloqueada->peces_vivos -= $datos['cantidad'];
            $siembraBloqueada->biomasa_kg = ($siembraBloqueada->peces_vivos * $siembraBloqueada->peso_actual_g) / 1000;
            $siembraBloqueada->save();
        });

        return back()->with('estado', 'Mortalidad registrada y peces descontados.');
    }
}
