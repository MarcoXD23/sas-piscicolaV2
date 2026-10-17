<?php

namespace App\Support\Proceso;

use App\Models\Jornada;
use App\Models\JornadaLago;
use App\Models\PesajeEntero;
use App\Models\Siembra;
use Illuminate\Support\Collection;

/**
 * Reparto del entero de una especie entre las siembras revueltas de un lago (docs/diseno/dia-de-proceso.md,
 * temas 3 y 4). La cosecha no es selectiva: se reparte según la biomasa de cada siembra y el administrador puede
 * ajustarlo al cerrar. Los peces de cada siembra salen de kg ÷ peso promedio (el peso promedio no cambia).
 * Las cuentas van en centésimas de kg (enteros), como CalculoPesaje: el reparto suma exacto el total.
 */
final class RepartoCosecha
{
    /**
     * Reparte $kg entre las siembras según su biomasa (peces vivos × peso promedio). Si ninguna tiene biomasa, por
     * partes iguales. Lo que sobra al redondear va a las siembras con mayor residuo.
     *
     * @param  Collection<int, Siembra>  $siembras
     * @return array<int, float> siembra_id => kg
     */
    public static function porBiomasa(float $kg, Collection $siembras): array
    {
        if ($siembras->isEmpty()) {
            return [];
        }

        return self::proporcional($kg, $siembras->mapWithKeys(fn (Siembra $s) => [$s->id => (float) $s->peces_vivos * (float) $s->peso_actual_g])->all());
    }

    /**
     * Reparte $kg en proporción a los pesos dados (si todos son cero, por partes iguales). En centésimas: suma exacto el
     * total; lo que sobra al redondear va a los de mayor residuo. Lo usan el reparto según la biomasa y el del limpio.
     *
     * @param  array<int, float>  $pesos  clave => peso
     * @return array<int, float> clave => kg
     */
    public static function proporcional(float $kg, array $pesos): array
    {
        if ($pesos === []) {
            return [];
        }

        $total = (int) round($kg * 100);
        $pesos = array_map(fn (float $peso) => max(0.0, $peso), $pesos);
        $suma = array_sum($pesos);
        if ($suma <= 0) {
            $pesos = array_map(fn () => 1.0, $pesos);
            $suma = (float) count($pesos);
        }

        $partes = [];
        $residuos = [];
        foreach ($pesos as $id => $peso) {
            $exacto = $total * $peso / $suma;
            $partes[$id] = (int) floor($exacto);
            $residuos[$id] = $exacto - $partes[$id];
        }
        arsort($residuos);
        $falta = $total - array_sum($partes);
        foreach (array_keys($residuos) as $id) {
            if ($falta <= 0) {
                break;
            }
            $partes[$id]++;
            $falta--;
        }

        return array_map(fn (int $centesimas) => $centesimas / 100, $partes);
    }

    /**
     * Peces que salen con $kg de una siembra: kg ÷ peso promedio (el medido al pescar, si se tomó la muestra; si no, el
     * del registro), al menos 1 si hay kg. Null si no hay peso.
     */
    public static function peces(float $kg, Siembra $siembra, ?float $pesoMedido = null): ?int
    {
        if ($kg <= 0) {
            return 0;
        }
        $peso = $pesoMedido ?? (float) $siembra->peso_actual_g;
        if ($peso <= 0) {
            return null;
        }

        return max(1, (int) round($kg * 1000 / $peso));
    }

    /**
     * Entero de cada especie en un lago de la jornada, menos la «reproducción» (esos peces vuelven al lago: no se
     * cosechan). Puede quedar en cero o negativo si se registró más reproducción que entero: el cierre lo rechaza.
     *
     * @return array<int, float> especie_id => kg
     */
    public static function totalesPorEspecie(JornadaLago $jornadaLago): array
    {
        $centesimas = [];
        foreach ($jornadaLago->pesajes as $pesaje) {
            /** @var PesajeEntero $pesaje */
            $centesimas[$pesaje->especie_id] = ($centesimas[$pesaje->especie_id] ?? 0) + (int) round($pesaje->entero_kg * 100);
        }
        foreach ($jornadaLago->reproducciones as $reproduccion) {
            $centesimas[$reproduccion->especie_id] = ($centesimas[$reproduccion->especie_id] ?? 0) - (int) round($reproduccion->kg * 100);
        }

        return array_map(fn (int $c) => $c / 100, $centesimas);
    }

    /**
     * Siembras del lago que cuentan para el cierre: activas y sembradas a más tardar el día de la jornada.
     *
     * @param  Collection<int, Siembra>  $activas  siembras activas del lago
     * @return Collection<int, Siembra>
     */
    public static function siembrasDelDia(Jornada $jornada, Collection $activas): Collection
    {
        return $activas->filter(fn (Siembra $s) => $s->fecha->toDateString() <= $jornada->fecha->toDateString())->sortBy('id')->values();
    }

    /**
     * Propuesta de cierre de un lago: por cada siembra, su especie, los kg que le tocan según la biomasa y los peces.
     * Una especie pesada sin ninguna siembra activa en el lago va en «sin_siembra» (no se puede cerrar así).
     *
     * @param  Collection<int, Siembra>  $siembras  las de siembrasDelDia()
     *                                              Una especie cuya reproducción iguala o supera su entero va en «sin_entero» (tampoco se puede cerrar así).
     * @return array{filas: list<array{siembra: Siembra, kg: float, peces: ?int}>, sin_siembra: list<int>, sin_entero: list<int>, totales: array<int, float>}
     */
    public static function propuestaLago(JornadaLago $jornadaLago, Collection $siembras): array
    {
        $totales = self::totalesPorEspecie($jornadaLago);
        $kg = [];
        $sinSiembra = [];
        $sinEntero = array_keys(array_filter($totales, fn (float $total) => $total <= 0));
        $totales = array_filter($totales, fn (float $total) => $total > 0);
        foreach ($totales as $especieId => $total) {
            $deLaEspecie = $siembras->where('especie_id', $especieId)->values();
            if ($deLaEspecie->isEmpty()) {
                $sinSiembra[] = $especieId;

                continue;
            }
            $kg += self::porBiomasa($total, $deLaEspecie);
        }

        return [
            'filas' => $siembras->map(fn (Siembra $s) => [
                'siembra' => $s,
                'kg' => $kg[$s->id] ?? 0.0,
                'peces' => self::peces($kg[$s->id] ?? 0.0, $s),
            ])->all(),
            'sin_siembra' => $sinSiembra,
            'sin_entero' => $sinEntero,
            'totales' => $totales,
        ];
    }
}
