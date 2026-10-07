<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Bitacora;
use App\Models\Rol;
use App\Models\Siembra;
use App\Support\Formato;
use App\Support\Region;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MortalidadController extends Controller
{
    public function store(Request $request, Siembra $siembra): RedirectResponse
    {
        $datos = $request->validate([
            'fecha' => ['required', 'date', 'before_or_equal:'.Region::hoy()->toDateString(), 'after_or_equal:'.$siembra->fecha->format('Y-m-d')],
            'cantidad' => ['required', 'integer', 'min:1', 'max:10000000'],
        ]);

        DB::transaction(function () use ($datos, $siembra, $request) {
            $siembraBloqueada = Siembra::where('id', $siembra->id)->lockForUpdate()->first();

            if (! $siembraBloqueada->estaActiva()) {
                throw ValidationException::withMessages(['cantidad' => __('mensajes.mortalidad.esta_siembra_ya_esta_finalizada')]);
            }

            if ($datos['cantidad'] > $siembraBloqueada->peces_vivos) {
                throw ValidationException::withMessages([
                    'cantidad' => __('mensajes.mortalidad.mayor_a_vivos', ['cantidad' => Formato::numero($siembraBloqueada->peces_vivos)]),
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

            Bitacora::registrar($request->user(), 'mortalidad', $siembraBloqueada->lago, [Rol::ADMINISTRADOR, Rol::TRABAJADOR], [
                'peces' => (int) $datos['cantidad'], 'especie' => $siembraBloqueada->nombre_especie, 'fecha' => $datos['fecha'],
            ]);
        });

        return back()->with('estado', __('mensajes.mortalidad.mortalidad_registrada_peces_descontados'));
    }
}
