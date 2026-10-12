<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Bitacora;
use App\Models\Lago;
use App\Models\Rol;
use App\Models\Siembra;
use App\Models\Traslado;
use App\Support\Formato;
use App\Support\Region;
use App\Support\ReglasPolicultivo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Traslada peces de una siembra a otro lago. Si la cantidad es parcial es un desdoble.
 */
class TrasladoController extends Controller
{
    public function store(Request $request, Siembra $siembra): RedirectResponse
    {
        $datos = $request->validate([
            'lago_destino_id' => ['required', 'integer', Rule::exists('lagos', 'id')->where('tenant_id', $request->user()->tenant_id)],
            'fecha' => ['required', 'date', 'before_or_equal:'.Region::hoy()->toDateString(), 'after_or_equal:'.$siembra->fecha->format('Y-m-d')],
            'cantidad' => ['required', 'integer', 'min:1', 'max:10000000'],
            'peso_promedio_g' => ['required', 'numeric', 'min:0.1', 'max:20000'],
            'observaciones' => ['nullable', 'string', 'max:500'],
        ], [
            'lago_destino_id.required' => __('mensajes.traslado.elige_lago_destino'),
            'lago_destino_id.exists' => __('mensajes.traslado.lago_destino_no_existe'),
            'fecha.after_or_equal' => __('mensajes.traslado.fecha_no_puede_ser_anterior'),
            'fecha.before_or_equal' => __('mensajes.traslado.fecha_no_puede_ser_futura'),
        ]);

        if ((int) $datos['lago_destino_id'] === $siembra->lago_id) {
            throw ValidationException::withMessages(['lago_destino_id' => __('mensajes.traslado.lago_destino_debe_ser_distinto')]);
        }

        $traslado = DB::transaction(function () use ($siembra, $datos, $request) {
            // Bloquea ambos lagos en orden fijo (evita bloqueos cruzados) y la siembra de origen
            Lago::whereKey([$siembra->lago_id, $datos['lago_destino_id']])->orderBy('id')->lockForUpdate()->get();
            $origen = Siembra::whereKey($siembra->id)->lockForUpdate()->first();
            $lagoDestino = Lago::findOrFail($datos['lago_destino_id']);

            if (! $origen->estaActiva()) {
                throw ValidationException::withMessages(['cantidad' => __('mensajes.traslado.esta_siembra_ya_esta_finalizada')]);
            }

            $cantidad = (int) $datos['cantidad'];
            if ($cantidad > $origen->peces_vivos) {
                throw ValidationException::withMessages(['cantidad' => __('mensajes.comun.solo_hay_peces', ['cantidad' => Formato::numero($origen->peces_vivos)])]);
            }

            $peso = (float) $datos['peso_promedio_g'];
            $activasDestino = $lagoDestino->siembrasActivas()->lockForUpdate()->get();
            // Se suman al lote MÁS ANTIGUO de la misma especie en el destino; si no hay, se crea un lote nuevo
            $destino = $activasDestino->where('especie_id', $origen->especie_id)->sortBy(fn (Siembra $s) => $s->fecha->toDateString().'|'.$s->id)->first();

            if ($destino) {
                // Se suman a la siembra de la misma especie con peso promedio ponderado
                $total = $destino->peces_vivos + $cantidad;
                $destino->peso_actual_g = (($destino->peces_vivos * $destino->peso_actual_g) + ($cantidad * $peso)) / $total;
                $destino->peces_vivos = $total;
                $destino->biomasa_kg = ($total * $destino->peso_actual_g) / 1000;
                $destino->save();
            } else {
                ReglasPolicultivo::verificarClima($activasDestino, $origen->especie, $request->boolean(ReglasPolicultivo::CAMPO_CONFIRMACION));

                $destino = $lagoDestino->siembras()->create([
                    'especie_id' => $origen->especie_id,
                    // Conserva el origen de los alevinos para la trazabilidad
                    'proveedor_alevinos' => $origen->proveedor_alevinos,
                    'lote_alevinos' => $origen->lote_alevinos,
                    'fecha' => $datos['fecha'],
                    'cantidad_alevinos' => $cantidad,
                    'peso_promedio_inicial_g' => $peso,
                    'peces_vivos' => $cantidad,
                    'peso_actual_g' => $peso,
                    'biomasa_kg' => ($cantidad * $peso) / 1000,
                    // El lote nace de este traslado (si se suma a uno existente, ese conserva su origen)
                    'origen' => Siembra::ORIGEN_TRASLADO,
                    'lago_origen_id' => $origen->lago_id,
                ]);
            }

            $origen->peces_vivos -= $cantidad;
            $origen->biomasa_kg = ($origen->peces_vivos * $origen->peso_actual_g) / 1000;
            if ($origen->peces_vivos === 0) {
                // Traslado total: la siembra de origen queda cerrada y el lago libre
                $origen->finalizada_at = now();
            }
            $origen->save();

            Bitacora::registrar($request->user(), 'traslado', $origen->lago, [Rol::ADMINISTRADOR], [
                'peces' => $cantidad, 'especie' => $origen->nombre_especie, 'destino' => $lagoDestino->nombre, 'fecha' => $datos['fecha'],
            ]);

            return Traslado::create([
                'siembra_origen_id' => $origen->id,
                'siembra_destino_id' => $destino->id,
                'lago_origen_id' => $origen->lago_id,
                'lago_destino_id' => $lagoDestino->id,
                'user_id' => $request->user()->id,
                'fecha' => $datos['fecha'],
                'cantidad' => $cantidad,
                'peso_promedio_g' => $peso,
                'biomasa_kg' => ($cantidad * $peso) / 1000,
                'observaciones' => $datos['observaciones'] ?? null,
            ]);
        });

        return redirect()->route('admin.lagos.show', $siembra->lago_id)
            ->with('estado', __('mensajes.traslado.registrado', ['cantidad' => Formato::numero($traslado->cantidad), 'lago' => $traslado->lagoDestino->nombre]));
    }
}
