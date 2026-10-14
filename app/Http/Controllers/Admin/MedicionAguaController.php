<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Bitacora;
use App\Models\Lago;
use App\Models\MedicionAgua;
use App\Models\Rol;
use App\Support\Formato;
use App\Support\MomentoRegistro;
use App\Support\Region;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Medición del agua: desde el detalle del lago (administrador) o desde Seguridad & Noche (celador, también sin
 * conexión). Cada medición queda en la bitácora, con la hora del registro y, si llegó tarde, la nota de sin conexión
 * y de la corrección del reloj del teléfono.
 */
class MedicionAguaController extends Controller
{
    private const PARAMETROS = ['temperatura_c', 'oxigeno_mg_l', 'ph', 'amonio_mg_l', 'nitritos_mg_l', 'transparencia_cm'];

    public function store(Request $request, Lago $lago): RedirectResponse
    {
        // Desde Seguridad & Noche no se envía la hora: se usa la del registro (ahora, o la del teléfono si fue sin conexión)
        // La hora del formulario es de la zona de la finca; el cast de MedicionAgua la guarda en UTC
        $request->mergeIfMissing(['medido_at' => MomentoRegistro::actual()->setTimezone(Region::zona())->format('Y-m-d H:i:s')]);
        $alMenosUno = 'required_without_all:'.implode(',', self::PARAMETROS);

        $datos = $request->validate([
            // No puede ser posterior al momento real del registro (sin conexión, la hora del teléfono ya corregida)
            'medido_at' => ['required', 'date', 'before_or_equal:'.Region::ahoraDelRegistro()->format('Y-m-d H:i:s')],
            'temperatura_c' => ['nullable', 'numeric', 'between:0,45', $alMenosUno],
            'oxigeno_mg_l' => ['nullable', 'numeric', 'between:0,30'],
            'ph' => ['nullable', 'numeric', 'between:0,14'],
            'amonio_mg_l' => ['nullable', 'numeric', 'between:0,100'],
            'nitritos_mg_l' => ['nullable', 'numeric', 'between:0,100'],
            'transparencia_cm' => ['nullable', 'integer', 'between:0,500'],
            'observaciones' => ['nullable', 'string', 'max:500'],
        ], [
            'temperatura_c.required_without_all' => __('mensajes.medicion_agua.registra_menos_parametro'),
            'medido_at.before_or_equal' => MomentoRegistro::sinConexionActual()
                ? __('mensajes.sin_conexion.hora_posterior_al_registro', ['hora' => Region::ahoraDelRegistro()->formatoHora('d/m/Y H:i')])
                : __('mensajes.medicion_agua.medicion_no_puede_tener_fecha'),
            'between' => __('mensajes.medicion_agua.valor_esta_fuera_rango_posible'),
        ]);

        [$medicion, $alertas] = DB::transaction(function () use ($datos, $lago, $request) {
            $medicion = $lago->mediciones()->create($datos + ['user_id' => $request->user()->id]);
            $alertas = $medicion->alertas($lago->siembrasActivas()->get()->pluck('especie'));

            Bitacora::registrar($request->user(), 'medicion', $lago, [Rol::ADMINISTRADOR, Rol::CELADOR], [
                'valores' => $this->valores($medicion, count($alertas)),
            ]);

            return [$medicion, $alertas];
        });

        // Se registra desde el detalle del lago o desde Seguridad & Noche: vuelve a la misma pantalla
        return back()->with('estado', $alertas === []
            ? __('mensajes.medicion_agua.medicion_registrada_todos_parametros_estan')
            : __('mensajes.medicion_agua.registrada_con_alertas', ['cantidad' => count($alertas)]));
    }

    /** «Temperatura 26,5 °C, Oxígeno 5,1 mg/L · 2 alertas»: solo los parámetros medidos. */
    private function valores(MedicionAgua $medicion, int $alertas): string
    {
        $medidos = collect(self::PARAMETROS)
            ->filter(fn (string $parametro) => $medicion->{$parametro} !== null)
            ->map(fn (string $parametro) => __('bitacora.valores_medicion.'.$parametro, [
                'valor' => Formato::sinCeros((float) $medicion->{$parametro}),
            ]))
            ->implode(', ');

        return $alertas > 0 ? $medidos.' · '.trans_choice('bitacora.alertas_medicion', $alertas, ['cantidad' => $alertas]) : $medidos;
    }
}
