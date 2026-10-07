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

class MuestreoController extends Controller
{
    public function store(Request $request, Siembra $siembra): RedirectResponse
    {
        $datos = $request->validate([
            'fecha' => ['required', 'date', 'before_or_equal:'.Region::hoy()->toDateString(), 'after_or_equal:'.$siembra->fecha->format('Y-m-d')],
            'peces_pesados' => ['required', 'integer', 'min:1', 'max:100000'],
            'peso_total_g' => ['required', 'numeric', 'min:0.1', 'max:999999'],
        ]);

        $resultado = DB::transaction(function () use ($datos, $siembra, $request) {
            // Bloquea la siembra antes de registrar para evitar condiciones de carrera
            $siembraBloqueada = Siembra::where('id', $siembra->id)->lockForUpdate()->first();

            if (! $siembraBloqueada->estaActiva()) {
                throw ValidationException::withMessages(['peces_pesados' => __('mensajes.muestreo.esta_siembra_ya_esta_finalizada')]);
            }

            // El promedio lo calcula el servidor (nunca se confía en el que muestra el navegador)
            $pesoPromedio = round((float) $datos['peso_total_g'] / (int) $datos['peces_pesados'], 2);
            $anterior = (float) $siembraBloqueada->peso_actual_g;

            $siembraBloqueada->muestreos()->create([
                'user_id' => $request->user()->id,
                'fecha' => $datos['fecha'],
                'peces_pesados' => $datos['peces_pesados'],
                'peso_total_g' => $datos['peso_total_g'],
                'peso_promedio_g' => $pesoPromedio,
            ]);

            $siembraBloqueada->peso_actual_g = $pesoPromedio;
            $siembraBloqueada->biomasa_kg = ($siembraBloqueada->peces_vivos * $pesoPromedio) / 1000;
            $siembraBloqueada->save();

            Bitacora::registrar($request->user(), 'muestreo', $siembraBloqueada->lago, [Rol::ADMINISTRADOR], [
                'especie' => $siembraBloqueada->nombre_especie, 'gramos' => round((float) $pesoPromedio, 2), 'peces' => (int) $datos['peces_pesados'], 'fecha' => $datos['fecha'],
            ]);

            return ['promedio' => $pesoPromedio, 'anterior' => $anterior];
        });

        return back()->with('estado', __('mensajes.muestreo.registrado_con_promedio', [
            'promedio' => Formato::numero($resultado['promedio'], 2),
            'anterior' => Formato::numero($resultado['anterior'], 2),
            'diferencia' => ($resultado['promedio'] >= $resultado['anterior'] ? '+' : '−').Formato::numero(abs($resultado['promedio'] - $resultado['anterior']), 2),
        ]));
    }
}
