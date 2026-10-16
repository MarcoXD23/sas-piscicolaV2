<?php

namespace App\Support;

use App\Models\Concentrado;
use App\Models\Lago;
use App\Models\MovimientoAlimento;
use App\Models\Siembra;
use App\Models\StockAlimento;
use App\Models\TablaRacion;
use Illuminate\Support\Collection;

/**
 * Sugiere la ración diaria de un lago a partir de la biomasa, el peso promedio de cada siembra activa
 * y la tabla de ración configurable por finca y especie (TablaRacion), con respaldo en las tablas por perfil.
 * También sugiere el concentrado del catálogo y calcula el cambio gradual de 3 días (¼, ½ y ¾).
 */
class CalculadoraRacion
{
    /**
     * Tablas de respaldo por perfil de alimentación: [peso promedio hasta (g), % de biomasa al día, tomas al día].
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

    /** Proporciones del cambio gradual durante 3 días: día => [proporción del nuevo, fracción legible]. */
    public const DIAS_CAMBIO_GRADUAL = [
        1 => ['nuevo' => 0.25, 'anterior' => 0.75, 'fraccion' => '1/4'],
        2 => ['nuevo' => 0.50, 'anterior' => 0.50, 'fraccion' => '1/2'],
        3 => ['nuevo' => 0.75, 'anterior' => 0.25, 'fraccion' => '3/4'],
    ];

    /** Si la última medición tiene alertas y es de las últimas horas, se sugiere reducir la ración. */
    public const HORAS_MEDICION_VIGENTE = 24;

    public const FACTOR_REDUCCION = 0.5;

    /**
     * Las siembras cuya especie no tiene tabla de ración ni tipo de alimentación van en sin_sugerencia (ración manual).
     *
     * @return array{
     *     filas: list<array<string, mixed>>,
     *     total_kg_dia: float,
     *     tomas: int,
     *     kg_por_toma: float,
     *     factor: float,
     *     motivos: list<string>,
     *     sin_sugerencia: list<string>,
     *     concentrado_sugerido: ?string,
     *     concentrado_etiqueta: ?string,
     *     cambio_gradual: ?array<string, mixed>
     * }
     */
    public static function paraLago(Lago $lago): array
    {
        $lago->loadMissing(['siembrasActivas.especie', 'ultimaMedicion']);
        $siembras = $lago->siembrasActivas;

        $motivos = [];
        $medicion = $lago->ultimaMedicion;
        if ($medicion && $medicion->medido_at->gte(now()->subHours(self::HORAS_MEDICION_VIGENTE))) {
            $motivos = $medicion->alertas($siembras->pluck('especie'));
        }
        $factor = $motivos === [] ? 1.0 : self::FACTOR_REDUCCION;

        $especieIds = $siembras->pluck('especie_id')->filter()->unique()->values();
        $tramosPorEspecie = $especieIds->isEmpty()
            ? collect()
            : TablaRacion::query()
                ->with('concentrado')
                ->whereIn('especie_id', $especieIds)
                ->orderBy('peso_hasta_g')
                ->get()
                ->groupBy('especie_id');

        $concentradosActivos = Concentrado::query()->activos()->orderBy('id')->get();
        $enBodega = StockAlimento::query()->where('cantidad_kg', '>', 0)->pluck('cantidad_kg', 'tipo_concentrado');

        [$conSugerencia, $sinSugerencia] = $siembras->partition(function (Siembra $siembra) use ($tramosPorEspecie) {
            return $tramosPorEspecie->has($siembra->especie_id) || $siembra->especie?->alimentacion !== null;
        });

        $filas = $conSugerencia->map(fn (Siembra $siembra) => self::fila(
            $siembra,
            $factor,
            $tramosPorEspecie->get($siembra->especie_id, collect()),
            $concentradosActivos,
            $enBodega,
        ))->values()->all();

        $total = round(array_sum(array_column($filas, 'kg_dia')), 1);
        $tomas = $filas === [] ? 0 : max(array_column($filas, 'tomas'));

        // Concentrado sugerido principal del lago (el de la siembra de mayor biomasa que tenga sugerencia)
        $filaPrincipal = collect($filas)->sortByDesc('biomasa_kg')->first(fn (array $f) => $f['concentrado'] !== null);
        $concentradoSugerido = $filaPrincipal['concentrado'] ?? null;
        $concentradoEtiqueta = $filaPrincipal['concentrado_etiqueta'] ?? null;

        return [
            'filas' => $filas,
            'total_kg_dia' => $total,
            'tomas' => $tomas,
            'kg_por_toma' => $tomas > 0 ? round($total / $tomas, 1) : 0.0,
            'factor' => $factor,
            'motivos' => $motivos,
            'sin_sugerencia' => $sinSugerencia->pluck('nombre_especie')->unique()->values()->all(),
            'concentrado_sugerido' => $concentradoSugerido,
            'concentrado_etiqueta' => $concentradoEtiqueta,
            'cambio_gradual' => $concentradoSugerido !== null && $total > 0
                ? self::detectarCambioGradual($lago, $total, $concentradoSugerido, $concentradoEtiqueta ?? $concentradoSugerido)
                : null,
        ];
    }

    /**
     * Calcula las proporciones de los 3 días de cambio gradual (¼, ½ y ¾) entre el concentrado anterior y el nuevo.
     *
     * @return array{
     *     dia: int,
     *     fraccion: string,
     *     anterior: string,
     *     nuevo: string,
     *     kg_anterior: float,
     *     kg_nuevo: float,
     *     dias: list<array{dia: int, fraccion: string, kg_nuevo: float, kg_anterior: float}>
     * }
     */
    public static function planCambioGradual(float $totalKgDia, string $anterior, string $nuevo, int $diaActual = 1): array
    {
        $dia = max(1, min(3, $diaActual));
        $regla = self::DIAS_CAMBIO_GRADUAL[$dia];
        $kgNuevo = round($totalKgDia * $regla['nuevo'], 1);
        $kgAnterior = round(max(0.0, $totalKgDia - $kgNuevo), 1);

        $dias = [];
        foreach (self::DIAS_CAMBIO_GRADUAL as $numeroDia => $proporcion) {
            $nuevoDia = round($totalKgDia * $proporcion['nuevo'], 1);
            $dias[] = [
                'dia' => $numeroDia,
                'fraccion' => $proporcion['fraccion'],
                'kg_nuevo' => $nuevoDia,
                'kg_anterior' => round(max(0.0, $totalKgDia - $nuevoDia), 1),
            ];
        }

        return [
            'dia' => $dia,
            'fraccion' => $regla['fraccion'],
            'anterior' => $anterior,
            'nuevo' => $nuevo,
            'kg_anterior' => $kgAnterior,
            'kg_nuevo' => $kgNuevo,
            'dias' => $dias,
        ];
    }

    /**
     * @param  string  $perfil  Tipo de alimentación de la especie (calido, frio o detritivoro)
     * @return array{0: float, 1: int} [% de biomasa al día, tomas al día]
     */
    public static function porcentajeYTomas(string $perfil, float $pesoPromedioG): array
    {
        foreach (self::TABLAS[$perfil] as [$hasta, $porcentaje, $tomas]) {
            if ($pesoPromedioG <= $hasta) {
                return [$porcentaje, $tomas];
            }
        }

        return [0.0, 0];
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

    /**
     * @param  Collection<int, TablaRacion>  $tramos
     * @param  Collection<int, Concentrado>  $concentradosActivos
     * @param  Collection<string, float>  $enBodega
     * @return array<string, mixed>
     */
    private static function fila(
        Siembra $siembra,
        float $factor,
        Collection $tramos,
        Collection $concentradosActivos,
        Collection $enBodega,
    ): array {
        $peso = (float) $siembra->peso_actual_g;
        $etapa = self::etapa($peso);

        $tramo = $tramos->first(fn (TablaRacion $t) => $peso <= $t->peso_hasta_g) ?? $tramos->last();

        if ($tramo !== null) {
            $porcentaje = (float) $tramo->porcentaje_biomasa;
            $tomas = (int) $tramo->tomas_dia;
            $textoProteina = $tramo->proteina_porcentaje !== null
                ? rtrim(rtrim(Formato::numero($tramo->proteina_porcentaje, 1), '0'), ',.').' %'
                : null;
            $proteinaReferencia = $siembra->especie?->referencia()['proteina'][$etapa] ?? null;
            $proteina = ($textoProteina !== null && ($proteinaReferencia === null || ! str_contains($proteinaReferencia, $textoProteina)))
                ? $textoProteina
                : ($proteinaReferencia ?? $textoProteina);
            $pellet = $tramo->pellet;
            $concentrado = self::elegirConcentrado($tramo, $concentradosActivos, $enBodega);
        } else {
            [$porcentaje, $tomas] = self::porcentajeYTomas($siembra->especie->alimentacion, $peso);
            $proteina = $siembra->especie?->referencia()['proteina'][$etapa] ?? null;
            $pellet = null;
            $concentrado = null;
        }

        return [
            'siembra_id' => $siembra->id,
            'especie' => $siembra->nombre_especie,
            'biomasa_kg' => (float) $siembra->biomasa_kg,
            'porcentaje' => $porcentaje,
            'kg_dia' => round((float) $siembra->biomasa_kg * $porcentaje / 100 * $factor, 1),
            'tomas' => $tomas,
            'etapa' => $etapa,
            'proteina' => $proteina,
            'pellet' => $pellet,
            'concentrado' => $concentrado?->nombre,
            'concentrado_corto' => $concentrado?->nombre_corto,
            'concentrado_etiqueta' => $concentrado?->etiqueta(),
        ];
    }

    /**
     * @param  Collection<int, Concentrado>  $concentradosActivos
     * @param  Collection<string, float>  $enBodega
     */
    private static function elegirConcentrado(
        TablaRacion $tramo,
        Collection $concentradosActivos,
        Collection $enBodega,
    ): ?Concentrado {
        if ($tramo->concentrado !== null && $tramo->concentrado->activo) {
            return $tramo->concentrado;
        }

        if ($tramo->proteina_porcentaje === null || $concentradosActivos->isEmpty()) {
            return null;
        }

        $candidatos = $concentradosActivos->filter(
            fn (Concentrado $c) => $c->proteina_porcentaje !== null
                && abs($c->proteina_porcentaje - $tramo->proteina_porcentaje) < 0.01
        );

        return $candidatos->first(fn (Concentrado $c) => ($enBodega[$c->nombre] ?? 0) > 0)
            ?? $candidatos->first();
    }

    /**
     * Si el lago venía comiendo otro concentrado en los últimos días y hoy la tabla sugiere uno nuevo,
     * avisa en qué día del cambio gradual (1: ¼, 2: ½, 3: ¾) va hasta completar los 3 días.
     *
     * @return ?array<string, mixed>
     */
    private static function detectarCambioGradual(
        Lago $lago,
        float $totalKgDia,
        string $concentradoSugerido,
        string $etiquetaSugerido,
    ): ?array {
        $desde = Region::hoy()->subDays(7)->toDateString();

        $recientes = MovimientoAlimento::query()
            ->where('lago_id', $lago->id)
            ->where('tipo_movimiento', 'salida')
            ->where('fecha', '>=', $desde)
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->get(['fecha', 'tipo_concentrado']);

        if ($recientes->isEmpty()) {
            return null;
        }

        $anteriorRegistro = $recientes->first(
            fn (MovimientoAlimento $m) => mb_strtolower($m->tipo_concentrado) !== mb_strtolower($concentradoSugerido)
        );

        if ($anteriorRegistro === null) {
            return null;
        }

        // Días distintos en que ya se suministró el concentrado nuevo desde la última vez que solo comió el anterior
        $diasConNuevo = $recientes
            ->filter(fn (MovimientoAlimento $m) => mb_strtolower($m->tipo_concentrado) === mb_strtolower($concentradoSugerido))
            ->pluck('fecha')
            ->map(fn ($fecha) => $fecha->toDateString())
            ->unique()
            ->count();

        if ($diasConNuevo >= 3) {
            return null;
        }

        $anteriorConcentrado = Concentrado::resolver($anteriorRegistro->tipo_concentrado);
        $etiquetaAnterior = $anteriorConcentrado ? $anteriorConcentrado->etiqueta() : $anteriorRegistro->tipo_concentrado;

        return self::planCambioGradual($totalKgDia, $etiquetaAnterior, $etiquetaSugerido, $diasConNuevo + 1);
    }
}
