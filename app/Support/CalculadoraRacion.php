<?php

namespace App\Support;

use App\Models\Lago;
use App\Models\MedicionAgua;
use App\Models\Siembra;

/**
 * Sugiere la ración diaria de un lago a partir de la biomasa y el peso promedio de cada siembra activa.
 * Las tablas son de referencia: cada finca debe ajustarlas con su asistente técnico y la etiqueta del alimento.
 */
class CalculadoraRacion
{
    /**
     * Tablas por perfil de alimentación: [peso promedio hasta (g), % de biomasa al día, tomas al día].
     */
    private const TABLAS = [
        'calido' => [
            [5, 8.0, 5], [20, 6.0, 4], [50, 4.0, 4], [100, 3.0, 3],
            [200, 2.5, 3], [300, 2.0, 3], [500, 1.5, 2], [PHP_INT_MAX, 1.2, 2],
        ],
        // Aprovecha detritos y restos: recibe la mitad de la ración de un pez de agua cálida
        'detritivoro' => [
            [5, 4.0, 4], [20, 3.0, 3], [50, 2.0, 3], [100, 1.5, 2],
            [200, 1.2, 2], [300, 1.0, 2], [500, 0.8, 2], [PHP_INT_MAX, 0.6, 2],
        ],
        'frio' => [
            [5, 5.0, 6], [20, 3.5, 5], [50, 2.5, 4], [100, 2.0, 3], [200, 1.6, 3], [PHP_INT_MAX, 1.2, 2],
        ],
    ];

    /** Si la última medición tiene alertas y es de las últimas horas, se sugiere reducir la ración. */
    public const HORAS_MEDICION_VIGENTE = 24;

    public const FACTOR_REDUCCION = 0.5;

    /**
     * @return array{filas: list<array<string, mixed>>, total_kg_dia: float, tomas: int, kg_por_toma: float, factor: float, motivos: list<string>}
     */
    public static function paraLago(Lago $lago): array
    {
        $lago->loadMissing(['siembrasActivas', 'ultimaMedicion']);
        $siembras = $lago->siembrasActivas;

        $motivos = [];
        $medicion = $lago->ultimaMedicion;
        if ($medicion && $medicion->medido_at->gte(now()->subHours(self::HORAS_MEDICION_VIGENTE))) {
            $motivos = $medicion->alertas($siembras->pluck('especie'));
        }
        $factor = $motivos === [] ? 1.0 : self::FACTOR_REDUCCION;

        $filas = $siembras->map(fn (Siembra $siembra) => self::fila($siembra, $factor))->values()->all();
        $total = round(array_sum(array_column($filas, 'kg_dia')), 1);
        $tomas = $filas === [] ? 0 : max(array_column($filas, 'tomas'));

        return [
            'filas' => $filas,
            'total_kg_dia' => $total,
            'tomas' => $tomas,
            'kg_por_toma' => $tomas > 0 ? round($total / $tomas, 1) : 0.0,
            'factor' => $factor,
            'motivos' => $motivos,
        ];
    }

    /** @return array{0: float, 1: int} [% de biomasa al día, tomas al día] */
    public static function porcentajeYTomas(string $especie, float $pesoPromedioG): array
    {
        $perfil = CatalogoEspecies::ficha($especie)['alimentacion'] ?? 'calido';

        foreach (self::TABLAS[$perfil] as [$hasta, $porcentaje, $tomas]) {
            if ($pesoPromedioG <= $hasta) {
                return [$porcentaje, $tomas];
            }
        }

        return [0.0, 0]; // inalcanzable: la última fila cubre cualquier peso
    }

    /** Etapa productiva según el peso promedio, para elegir la proteína del alimento. */
    public static function etapa(float $pesoPromedioG): string
    {
        return match (true) {
            $pesoPromedioG < 20 => 'iniciacion',
            $pesoPromedioG < 150 => 'levante',
            default => 'engorde',
        };
    }

    /** @return array<string, mixed> */
    private static function fila(Siembra $siembra, float $factor): array
    {
        $peso = (float) $siembra->peso_actual_g;
        [$porcentaje, $tomas] = self::porcentajeYTomas($siembra->especie, $peso);
        $etapa = self::etapa($peso);

        return [
            'siembra_id' => $siembra->id,
            'especie' => $siembra->nombre_especie,
            'biomasa_kg' => (float) $siembra->biomasa_kg,
            'porcentaje' => $porcentaje,
            'kg_dia' => round((float) $siembra->biomasa_kg * $porcentaje / 100 * $factor, 1),
            'tomas' => $tomas,
            'etapa' => $etapa,
            'proteina' => CatalogoEspecies::ficha($siembra->especie)['proteina'][$etapa] ?? null,
        ];
    }
}
