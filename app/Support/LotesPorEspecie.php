<?php

namespace App\Support;

use App\Models\Siembra;
use Illuminate\Support\Collection;

/**
 * Lotes (siembras activas) de un lago agrupados por especie, solo para mostrar: dos siembras de la misma especie, o un
 * lote creado por un traslado, se ven como una especie con su total y sus lotes como detalle.
 * Las operaciones (mortalidad, muestreo, cosecha, traslado, finalizar) y los cálculos (ración, conversión, alertas)
 * siguen siendo por lote: esto no los toca.
 */
final class LotesPorEspecie
{
    /**
     * @param  Collection<int, Siembra>  $siembras
     * @return Collection<int, array{especie: string, especie_id: int|null, foto: ?string, siembras: Collection<int, Siembra>, lotes: int,
     *                              peces: int, biomasa_kg: float, peso_promedio_g: float, peso_min_g: float, peso_max_g: float}>
     */
    public static function agrupar(Collection $siembras): Collection
    {
        return $siembras
            ->sortBy(fn (Siembra $s) => $s->fecha->toDateString().'|'.str_pad((string) $s->id, 12, '0', STR_PAD_LEFT))
            ->groupBy(fn (Siembra $s) => (string) ($s->especie_id ?? $s->nombre_especie))
            ->map(function (Collection $lotes) {
                $lotes = $lotes->values();
                $peces = (int) $lotes->sum('peces_vivos');
                $pesos = $lotes->map(fn (Siembra $s) => (float) $s->peso_actual_g);

                return [
                    'especie' => $lotes->first()->nombre_especie,
                    'especie_id' => $lotes->first()->especie_id,
                    'foto' => $lotes->first()->foto_especie,
                    'siembras' => $lotes,
                    'lotes' => $lotes->count(),
                    'peces' => $peces,
                    'biomasa_kg' => round((float) $lotes->sum('biomasa_kg'), 2),
                    // Peso promedio ponderado por los peces vivos de cada lote
                    'peso_promedio_g' => $peces > 0
                        ? round($lotes->sum(fn (Siembra $s) => $s->peces_vivos * (float) $s->peso_actual_g) / $peces, 2)
                        : round((float) $pesos->avg(), 2),
                    'peso_min_g' => (float) $pesos->min(),
                    'peso_max_g' => (float) $pesos->max(),
                ];
            })
            ->values();
    }

    /**
     * Especies sin repetir, con el número de lotes cuando hay más de uno: «Bagre rayado (2 lotes), Trucha arcoíris».
     *
     * @param  Collection<int, Siembra>  $siembras
     */
    public static function nombres(Collection $siembras): string
    {
        return self::agrupar($siembras)
            ->map(fn (array $grupo) => $grupo['lotes'] > 1 ? __('lagos.lotes.especie_con_lotes', ['especie' => $grupo['especie'], 'lotes' => $grupo['lotes']]) : $grupo['especie'])
            ->join(', ');
    }

    /**
     * Número de cada lote dentro de su especie en el lago (1, 2… por fecha), para nombrarlo: siembra_id => número.
     *
     * @param  Collection<int, Siembra>  $siembras
     * @return array<int, int>
     */
    public static function numeros(Collection $siembras): array
    {
        $numeros = [];
        foreach (self::agrupar($siembras) as $grupo) {
            foreach ($grupo['siembras'] as $indice => $siembra) {
                $numeros[$siembra->id] = $indice + 1;
            }
        }

        return $numeros;
    }

    /**
     * Etiqueta de cada lote para elegirlo en una lista: la especie sola si es su único lote; si no,
     * «Bagre rayado · lote 2 · creado por traslado desde L02 el 15/10/2026». siembra_id => texto.
     *
     * @param  Collection<int, Siembra>  $siembras
     * @return array<int, string>
     */
    public static function etiquetas(Collection $siembras): array
    {
        $etiquetas = [];
        foreach (self::agrupar($siembras) as $grupo) {
            foreach ($grupo['siembras'] as $indice => $siembra) {
                $etiquetas[$siembra->id] = $grupo['lotes'] > 1
                    ? __('lagos.lotes.etiqueta', ['especie' => $grupo['especie'], 'numero' => $indice + 1, 'origen' => mb_lcfirst($siembra->textoOrigen())])
                    : $grupo['especie'];
            }
        }

        return $etiquetas;
    }

    /** «319,23 g» o, con más de un lote de pesos distintos, «319,23 g · de 50 a 400 g». */
    public static function textoPeso(array $grupo): string
    {
        $promedio = Formato::numero($grupo['peso_promedio_g'], 2).' g';
        if ($grupo['lotes'] < 2 || $grupo['peso_min_g'] === $grupo['peso_max_g']) {
            return $promedio;
        }

        return __('lagos.lotes.peso_con_rango', [
            'promedio' => $promedio,
            'min' => Formato::sinCeros($grupo['peso_min_g']),
            'max' => Formato::sinCeros($grupo['peso_max_g']),
        ]);
    }
}
