<?php

namespace App\Support\Proceso;

use App\Models\Especie;
use App\Models\Jornada;
use App\Models\JornadaLago;
use App\Models\JornadaRendimiento;
use App\Models\PesajeLimpio;
use App\Support\Ajustes\AjustesFinca;
use App\Support\Ajustes\CatalogoAjustes;

/**
 * Rendimiento de entero a limpio (docs/diseno/dia-de-proceso.md, tema 12, a). Va por especie: el entero se pesa por
 * especie y el limpio por presentación, que lleva su especie.
 * - Rendimiento = limpio ÷ (entero − reproducción), en %. Sin limpio queda sin dato. No lleva tolerancia de cuadre.
 * - Referencia: el promedio real de las últimas N jornadas cerradas con limpio de la especie, ponderado por el entero,
 *   cuando ya hay N (ajuste proceso.jornadas_promedio); antes, la que la finca escribió en el catálogo de especies.
 * - Bajo: el rendimiento está los puntos del ajuste proceso.umbral_rendimiento (o más) por debajo de la referencia.
 */
final class Rendimiento
{
    /**
     * Referencia de rendimiento de una especie, en %, y de dónde sale. Null si no hay promedio ni valor en el catálogo.
     *
     * @return ?array{valor: float, fuente: string}
     */
    public static function referencia(int $especieId): ?array
    {
        $jornadas = (int) AjustesFinca::valor(CatalogoAjustes::PROCESO_JORNADAS_PROMEDIO);
        $ultimas = JornadaRendimiento::query()
            ->join('jornadas', 'jornadas.id', '=', 'jornada_rendimientos.jornada_id')
            ->where('jornadas.estado', Jornada::CERRADA)
            ->where('jornada_rendimientos.especie_id', $especieId)
            ->whereNotNull('jornada_rendimientos.limpio_kg')
            ->orderByDesc('jornadas.fecha')->orderByDesc('jornada_rendimientos.id')
            ->limit($jornadas)
            ->get(['jornada_rendimientos.entero_kg', 'jornada_rendimientos.reproduccion_kg', 'jornada_rendimientos.limpio_kg']);

        if ($ultimas->count() >= $jornadas) {
            $base = $ultimas->sum(fn ($fila) => (float) $fila->entero_kg - (float) $fila->reproduccion_kg);
            if ($base > 0) {
                return ['valor' => round($ultimas->sum(fn ($fila) => (float) $fila->limpio_kg) * 100 / $base, 2), 'fuente' => JornadaRendimiento::PROMEDIO];
            }
        }

        $catalogo = Especie::query()->whereKey($especieId)->value('rendimiento_referencia');

        return $catalogo !== null ? ['valor' => (float) $catalogo, 'fuente' => JornadaRendimiento::CATALOGO] : null;
    }

    /**
     * Rendimiento de cada especie de la jornada con lo pesado hasta ahora (para la vista previa y para el cierre).
     * Necesita cargados lagos.pesajes, lagos.reproducciones y pesajesLimpio.presentacion.
     *
     * @return array{especies: array<int, array{especie_id: int, entero: float, reproduccion: float, base: float, limpio: ?float, rendimiento: ?float, referencia: ?float, fuente: ?string, bajo: bool}>, limpio_sin_entero: list<int>, hay_limpio: bool}
     */
    public static function deJornada(Jornada $jornada): array
    {
        $entero = [];
        $reproduccion = [];
        foreach ($jornada->lagos as $jornadaLago) {
            /** @var JornadaLago $jornadaLago */
            foreach ($jornadaLago->pesajes as $pesaje) {
                $entero[$pesaje->especie_id] = ($entero[$pesaje->especie_id] ?? 0) + (int) round($pesaje->entero_kg * 100);
            }
            foreach ($jornadaLago->reproducciones as $fila) {
                $reproduccion[$fila->especie_id] = ($reproduccion[$fila->especie_id] ?? 0) + (int) round($fila->kg * 100);
            }
        }
        $limpio = [];
        foreach ($jornada->pesajesLimpio as $pesaje) {
            /** @var PesajeLimpio $pesaje */
            $especieId = (int) $pesaje->presentacion->especie_id;
            $limpio[$especieId] = ($limpio[$especieId] ?? 0) + (int) round($pesaje->limpio_kg * 100);
        }

        $umbral = (int) AjustesFinca::valor(CatalogoAjustes::PROCESO_UMBRAL_RENDIMIENTO);
        $especies = [];
        foreach ($entero as $especieId => $centesimas) {
            $base = $centesimas - ($reproduccion[$especieId] ?? 0);
            $kgLimpio = isset($limpio[$especieId]) ? $limpio[$especieId] / 100 : null;
            $rendimiento = $kgLimpio !== null && $base > 0 ? round($limpio[$especieId] * 100 / $base, 2) : null;
            $referencia = self::referencia($especieId);
            $especies[$especieId] = [
                'especie_id' => $especieId,
                'entero' => $centesimas / 100,
                'reproduccion' => ($reproduccion[$especieId] ?? 0) / 100,
                'base' => $base / 100,
                'limpio' => $kgLimpio,
                'rendimiento' => $rendimiento,
                'referencia' => $referencia['valor'] ?? null,
                'fuente' => $referencia['fuente'] ?? null,
                'bajo' => $rendimiento !== null && $referencia !== null && $rendimiento <= $referencia['valor'] - $umbral,
            ];
        }

        return [
            'especies' => $especies,
            'limpio_sin_entero' => array_values(array_diff(array_keys($limpio), array_keys($entero))),
            'hay_limpio' => $limpio !== [],
        ];
    }
}
