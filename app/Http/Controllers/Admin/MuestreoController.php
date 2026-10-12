<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Bitacora;
use App\Models\Rol;
use App\Models\Siembra;
use App\Support\Formato;
use App\Support\MomentoRegistro;
use App\Support\Region;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Muestreo de una siembra (desde Lagos con conexión, o desde Registro rápido sin conexión: middleware RegistroSinConexion).
 * Siempre queda en el historial. El peso actual y la biomasa solo cambian si es el muestreo más reciente por fecha de la
 * siembra (con la misma fecha, gana el que llega después): uno atrasado que llega tarde no pisa a uno más nuevo.
 */
class MuestreoController extends Controller
{
    public function store(Request $request, Siembra $siembra): RedirectResponse
    {
        // «Hoy» es el día en que de verdad se registró (sin conexión, la hora real del teléfono ya corregida), en la zona
        // de la finca: un muestreo de las 23:50 enviado después de medianoche conserva su día.
        $hoy = Region::local(MomentoRegistro::actual())->toDateString();
        $datos = $request->validate([
            'fecha' => ['required', 'date', 'before_or_equal:'.$hoy, 'after_or_equal:'.$siembra->fecha->format('Y-m-d')],
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
            $fecha = Carbon::parse($datos['fecha'])->toDateString();
            // ¿Es el más reciente por fecha? (con la misma fecha, sí: gana el que llega después)
            $ultimaFecha = $siembraBloqueada->muestreos()->max('fecha');
            $esMasReciente = $ultimaFecha === null || $fecha >= Carbon::parse($ultimaFecha)->toDateString();

            $siembraBloqueada->muestreos()->create([
                'user_id' => $request->user()->id,
                'fecha' => $datos['fecha'],
                'peces_pesados' => $datos['peces_pesados'],
                'peso_total_g' => $datos['peso_total_g'],
                'peso_promedio_g' => $pesoPromedio,
            ]);

            if ($esMasReciente) {
                $siembraBloqueada->peso_actual_g = $pesoPromedio;
                $siembraBloqueada->biomasa_kg = ($siembraBloqueada->peces_vivos * $pesoPromedio) / 1000;
                $siembraBloqueada->save();
            }

            Bitacora::registrar($request->user(), 'muestreo', $siembraBloqueada->lago, [Rol::ADMINISTRADOR], [
                'especie' => $siembraBloqueada->nombre_especie, 'gramos' => round((float) $pesoPromedio, 2), 'peces' => (int) $datos['peces_pesados'], 'fecha' => $datos['fecha'],
            ]);

            return ['promedio' => $pesoPromedio, 'anterior' => $anterior, 'mas_reciente' => $esMasReciente];
        });

        if (! $resultado['mas_reciente']) {
            return back()->with('estado', __('mensajes.muestreo.guardado_en_historial', ['actual' => Formato::numero($resultado['anterior'], 2)]));
        }

        return back()->with('estado', __('mensajes.muestreo.registrado_con_promedio', [
            'promedio' => Formato::numero($resultado['promedio'], 2),
            'anterior' => Formato::numero($resultado['anterior'], 2),
            'diferencia' => ($resultado['promedio'] >= $resultado['anterior'] ? '+' : '−').Formato::numero(abs($resultado['promedio'] - $resultado['anterior']), 2),
        ]));
    }
}
