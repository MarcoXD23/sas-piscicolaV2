<?php

namespace App\Support;

use App\Models\Lago;
use App\Models\ReglaFase;
use App\Models\Siembra;

/**
 * Comparación de lo real contra las reglas de la fase del lago (F2: días, peso promedio, supervivencia y densidad).
 * - Si el lago no tiene fase, o la especie no tiene regla en esa fase, devuelve null sin fallar.
 * - Nunca divide por cero si area_m2 es null o 0, si cantidad_alevinos es 0 o si dias_esperados es 0.
 * - Si un lote aún no tiene muestreos registrados, evalúa su peso actual (que inicia en peso_promedio_inicial_g).
 */
final class EstadoFase
{
    /**
     * Densidad actual del lago en peces vivos por m², o null si el lago no tiene área válida (> 0).
     */
    public static function densidadLago(Lago $lago): ?float
    {
        $area = (float) ($lago->area_m2 ?? 0);
        if ($area <= 0) {
            return null;
        }

        $peces = (int) $lago->siembrasActivas->sum('peces_vivos');

        return round($peces / $area, 2);
    }

    /**
     * Evalúa un lote (siembra activa) frente a la regla de la fase del lago para su especie_id.
     *
     * @return array{
     *     fase: string,
     *     tiene_regla: bool,
     *     dias: ?array{real: int, esperado: int, atraso: int},
     *     peso: ?array{real_g: float, entrada_g: ?float, salida_g: float, esperado_hoy_g: float, diferencia_g: float, atrasado: bool},
     *     supervivencia: ?array{real: float, esperada: float, diferencia: float, baja: bool},
     *     densidad: ?array{real: float, min: ?float, max: ?float, estado: string}
     * }|null
     */
    public static function paraSiembra(Lago $lago, Siembra $siembra): ?array
    {
        if ($lago->fase_id === null || $siembra->especie_id === null) {
            return null;
        }

        $fase = $lago->relationLoaded('fase') ? $lago->fase : $lago->fase()->first();
        if ($fase === null) {
            return null;
        }

        $reglas = $fase->relationLoaded('reglas')
            ? $fase->reglas
            : $fase->reglas()->get();

        /** @var ?ReglaFase $regla */
        $regla = $reglas->firstWhere('especie_id', $siembra->especie_id);
        if ($regla === null) {
            return [
                'fase' => $fase->nombre,
                'tiene_regla' => false,
                'dias' => null,
                'peso' => null,
                'supervivencia' => null,
                'densidad' => null,
            ];
        }

        // 1. Días en la fase
        $diasReales = max(0, (int) $siembra->dias_cultivo);
        $diasEsperados = $regla->dias_esperados !== null && $regla->dias_esperados > 0 ? (int) $regla->dias_esperados : null;
        $atrasoDias = $diasEsperados !== null ? max(0, $diasReales - $diasEsperados) : 0;

        // 2. Peso promedio (usa peso_actual_g o, si no estuviera, peso_promedio_inicial_g)
        $pesoReal = (float) ($siembra->peso_actual_g ?? $siembra->peso_promedio_inicial_g ?? 0);
        $pesoEntrada = $regla->peso_entrada_g !== null ? (float) $regla->peso_entrada_g : null;
        $pesoSalida = $regla->peso_salida_g !== null ? (float) $regla->peso_salida_g : null;

        $peso = null;
        if ($pesoSalida !== null) {
            if ($pesoEntrada !== null && $diasEsperados !== null && $diasEsperados > 0) {
                $progreso = min(1.0, $diasReales / $diasEsperados);
                $pesoEsperadoHoy = round($pesoEntrada + ($pesoSalida - $pesoEntrada) * $progreso, 2);
            } else {
                $pesoEsperadoHoy = round($pesoSalida, 2);
            }
            $diferenciaPeso = round($pesoReal - $pesoEsperadoHoy, 2);
            $peso = [
                'real_g' => round($pesoReal, 2),
                'entrada_g' => $pesoEntrada,
                'salida_g' => $pesoSalida,
                'esperado_hoy_g' => $pesoEsperadoHoy,
                'diferencia_g' => $diferenciaPeso,
                'atrasado' => $pesoReal < $pesoEsperadoHoy,
            ];
        }

        // 3. Supervivencia real vs. esperada (evitando división por cero si cantidad_alevinos <= 0)
        $inicial = (int) $siembra->cantidad_alevinos;
        $vivos = max(0, (int) $siembra->peces_vivos);
        $supervivenciaReal = $inicial > 0 ? round(min(100.0, ($vivos / $inicial) * 100), 1) : null;
        $supervivenciaEsperada = $regla->supervivencia_porcentaje !== null ? (float) $regla->supervivencia_porcentaje : null;
        $supervivencia = null;
        if ($supervivenciaReal !== null && $supervivenciaEsperada !== null) {
            $diferenciaSupervivencia = round($supervivenciaReal - $supervivenciaEsperada, 1);
            $supervivencia = [
                'real' => $supervivenciaReal,
                'esperada' => $supervivenciaEsperada,
                'diferencia' => $diferenciaSupervivencia,
                'baja' => $diferenciaSupervivencia < 0,
            ];
        }

        // 4. Densidad del lago frente al rango de referencia de la regla
        $densidadReal = self::densidadLago($lago);
        $densidadMin = $regla->densidad_min_m2 !== null ? (float) $regla->densidad_min_m2 : null;
        $densidadMax = $regla->densidad_max_m2 !== null ? (float) $regla->densidad_max_m2 : null;
        $densidad = null;
        if ($densidadReal !== null && ($densidadMin !== null || $densidadMax !== null)) {
            if ($densidadMax !== null && $densidadReal > $densidadMax) {
                $estadoDensidad = 'alta';
            } elseif ($densidadMin !== null && $densidadReal < $densidadMin) {
                $estadoDensidad = 'baja';
            } else {
                $estadoDensidad = 'en_rango';
            }
            $densidad = [
                'real' => $densidadReal,
                'min' => $densidadMin,
                'max' => $densidadMax,
                'estado' => $estadoDensidad,
            ];
        }

        return [
            'fase' => $fase->nombre,
            'tiene_regla' => true,
            'dias' => $diasEsperados !== null ? [
                'real' => $diasReales,
                'esperado' => $diasEsperados,
                'atraso' => $atrasoDias,
            ] : null,
            'peso' => $peso,
            'supervivencia' => $supervivencia,
            'densidad' => $densidad,
        ];
    }
}
