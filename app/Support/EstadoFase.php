<?php

namespace App\Support;

use App\Models\Lago;
use App\Models\ReglaFase;
use App\Models\Siembra;

/**
 * Comparación de lo real contra las reglas de la fase del lago: peso, supervivencia y densidad.
 * - Sin días ni «peso esperado hoy»: hasta F5 el lote solo conoce sus días desde la siembra, no los días en la fase
 *   (los traslados aún no los arrastran). F5 activa el atraso en días con los días en la fase.
 * - El peso se compara con el rango de la fase: por debajo del peso de entrada, en la fase o en el peso de salida.
 * - Lo que la regla no tiene queda en null y la pantalla dice «sin dato» (nunca un valor inventado).
 * - Si el lago no tiene fase, devuelve null. Nunca divide por cero (área vacía o 0, lote sin alevinos).
 */
final class EstadoFase
{
    public const PESO_BAJO_ENTRADA = 'bajo_entrada';

    public const PESO_EN_FASE = 'en_fase';

    public const PESO_SALIDA = 'salida';

    /** Densidad actual del lago en peces vivos por m², o null si el lago no tiene área válida (> 0). */
    public static function densidadLago(Lago $lago): ?float
    {
        $area = (float) ($lago->area_m2 ?? 0);
        if ($area <= 0) {
            return null;
        }

        return round((int) $lago->siembrasActivas->sum('peces_vivos') / $area, 2);
    }

    /**
     * Evalúa un lote (siembra activa) frente a la regla de la fase del lago para su especie.
     *
     * @return array{
     *     fase: string,
     *     tiene_regla: bool,
     *     peso: ?array{real_g: float, entrada_g: ?float, salida_g: ?float, estado: ?string},
     *     supervivencia: ?array{real: ?float, esperada: ?float, baja: ?bool},
     *     densidad: ?array{real: ?float, min: ?float, max: ?float, estado: ?string}
     * }|null
     */
    public static function paraSiembra(Lago $lago, Siembra $siembra): ?array
    {
        if ($lago->fase_id === null) {
            return null;
        }

        $fase = $lago->relationLoaded('fase') ? $lago->fase : $lago->fase()->first();
        if ($fase === null) {
            return null;
        }

        $reglas = $fase->relationLoaded('reglas') ? $fase->reglas : $fase->reglas()->get();
        /** @var ?ReglaFase $regla */
        $regla = $siembra->especie_id === null ? null : $reglas->firstWhere('especie_id', $siembra->especie_id);
        if ($regla === null) {
            return ['fase' => $fase->nombre, 'tiene_regla' => false, 'peso' => null, 'supervivencia' => null, 'densidad' => null];
        }

        return [
            'fase' => $fase->nombre,
            'tiene_regla' => true,
            'peso' => self::peso($siembra, $regla),
            'supervivencia' => self::supervivencia($siembra, $regla),
            'densidad' => self::densidad($lago, $regla),
        ];
    }

    /** Peso promedio de ahora (el del último muestreo o, sin muestreos, el de la siembra) contra el rango de la fase. */
    private static function peso(Siembra $siembra, ReglaFase $regla): array
    {
        $real = round((float) ($siembra->peso_actual_g ?? $siembra->peso_promedio_inicial_g ?? 0), 2);
        $entrada = $regla->peso_entrada_g;
        $salida = $regla->peso_salida_g;

        $estado = match (true) {
            $entrada === null && $salida === null => null,
            $salida !== null && $real >= $salida => self::PESO_SALIDA,
            $entrada !== null && $real < $entrada => self::PESO_BAJO_ENTRADA,
            default => self::PESO_EN_FASE,
        };

        return ['real_g' => $real, 'entrada_g' => $entrada, 'salida_g' => $salida, 'estado' => $estado];
    }

    /** Supervivencia del lote (vivos ÷ sembrados) contra la esperada de la fase; sin esperada, no se juzga. */
    private static function supervivencia(Siembra $siembra, ReglaFase $regla): array
    {
        $sembrados = (int) $siembra->cantidad_alevinos;
        $real = $sembrados > 0 ? round(min(100.0, max(0, (int) $siembra->peces_vivos) / $sembrados * 100), 1) : null;
        $esperada = $regla->supervivencia_porcentaje;

        return ['real' => $real, 'esperada' => $esperada, 'baja' => $real !== null && $esperada !== null ? $real < $esperada : null];
    }

    /** Densidad del lago contra el rango de referencia de la fase; sin área o sin rango, no se juzga. */
    private static function densidad(Lago $lago, ReglaFase $regla): array
    {
        $real = self::densidadLago($lago);
        $min = $regla->densidad_min_m2;
        $max = $regla->densidad_max_m2;

        $estado = match (true) {
            $real === null || ($min === null && $max === null) => null,
            $max !== null && $real > $max => 'alta',
            $min !== null && $real < $min => 'baja',
            default => 'en_rango',
        };

        return ['real' => $real, 'min' => $min, 'max' => $max, 'estado' => $estado];
    }
}
