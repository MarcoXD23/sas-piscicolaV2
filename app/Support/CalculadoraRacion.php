<?php

namespace App\Support;

use App\Models\Concentrado;
use App\Models\Lago;
use App\Models\MovimientoAlimento;
use App\Models\ReglaFase;
use App\Models\Siembra;
use App\Models\StockAlimento;
use App\Models\TablaRacion;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

/**
 * Sugiere la ración diaria de un lago a partir de la biomasa y el peso promedio de cada siembra activa, con la tabla
 * de ración de la finca por especie (TablaRacion, Ajustes › Concentrados y ración). Una especie sin tabla no tiene
 * sugerencia (ración manual).
 * - Concentrado sugerido: el de la regla de la fase del lago para la especie (Ajustes › Fases); si la fase no tiene uno,
 *   el del tramo de peso (fijo en el tramo, o el del catálogo con su misma proteína, primero el que hay en bodega).
 * - Cambio gradual de 3 días (¼, ½ y ¾) cuando el lago venía comiendo otro concentrado.
 * - paraLagos() calcula varios lagos con las mismas consultas (el paquete de Registro rápido, la lista de lagos).
 */
class CalculadoraRacion
{
    /** Proporciones del cambio gradual durante 3 días: día => [proporción del nuevo, fracción legible]. */
    public const DIAS_CAMBIO_GRADUAL = [
        1 => ['nuevo' => 0.25, 'anterior' => 0.75, 'fraccion' => '1/4'],
        2 => ['nuevo' => 0.50, 'anterior' => 0.50, 'fraccion' => '1/2'],
        3 => ['nuevo' => 0.75, 'anterior' => 0.25, 'fraccion' => '3/4'],
    ];

    /** Días hacia atrás en que se busca el concentrado que venía comiendo el lago (cambio gradual). */
    private const DIAS_CAMBIO_ATRAS = 7;

    /** Si la última medición tiene alertas y es de las últimas horas, se sugiere reducir la ración. */
    public const HORAS_MEDICION_VIGENTE = 24;

    public const FACTOR_REDUCCION = 0.5;

    /**
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
        return self::paraLagos(collect([$lago]))[$lago->id];
    }

    /**
     * Ración de varios lagos con las consultas hechas una sola vez (no una por lago).
     *
     * @param  Collection<int, Lago>  $lagos
     * @return array<int, array<string, mixed>> id del lago => lo mismo que paraLago()
     */
    public static function paraLagos(Collection $lagos): array
    {
        if ($lagos->isEmpty()) {
            return [];
        }
        $lagos = (new EloquentCollection($lagos->all()))->loadMissing(['siembrasActivas.especie', 'ultimaMedicion']);
        $contexto = self::contexto($lagos);

        return $lagos->mapWithKeys(fn (Lago $lago) => [$lago->id => self::calcular($lago, $contexto)])->all();
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
     * Lo que comparten todos los lagos: tramos, catálogo, bodega, concentrados de las fases y raciones recientes.
     *
     * @param  EloquentCollection<int, Lago>  $lagos
     * @return array<string, Collection>
     */
    private static function contexto(EloquentCollection $lagos): array
    {
        $especieIds = $lagos->flatMap(fn (Lago $lago) => $lago->siembrasActivas->pluck('especie_id'))->filter()->unique()->values();
        $faseIds = $lagos->pluck('fase_id')->filter()->unique()->values();
        $stocks = StockAlimento::query()->where('cantidad_kg', '>', 0)->whereNotNull('concentrado_id')->get(['concentrado_id', 'tipo_concentrado', 'cantidad_kg']);

        return [
            'tramos' => $especieIds->isEmpty() ? collect() : TablaRacion::query()->whereIn('especie_id', $especieIds)
                ->orderBy('peso_hasta_g')->get()->groupBy('especie_id'),
            // Todo el catálogo (también los inactivos, para nombrar lo que el lago venía comiendo)
            'concentrados' => Concentrado::query()->orderBy('id')->get()->keyBy('id'),
            // Kilos en bodega y el texto con que la bodega guarda cada concentrado (el que eligen los formularios)
            'kg_en_bodega' => $stocks->groupBy('concentrado_id')->map(fn (Collection $filas) => (float) $filas->sum('cantidad_kg')),
            'texto_en_bodega' => $stocks->keyBy('concentrado_id')->map(fn (StockAlimento $stock) => $stock->tipo_concentrado),
            'concentrado_fase' => $faseIds->isEmpty() ? collect() : ReglaFase::query()->whereIn('fase_id', $faseIds)->whereNotNull('concentrado_id')
                ->get(['fase_id', 'especie_id', 'concentrado_id'])->mapWithKeys(fn (ReglaFase $regla) => [$regla->fase_id.'|'.$regla->especie_id => $regla->concentrado_id]),
            'recientes' => MovimientoAlimento::query()
                ->whereIn('lago_id', $lagos->modelKeys())
                ->where('tipo_movimiento', 'salida')
                ->where('fecha', '>=', Region::hoy()->subDays(self::DIAS_CAMBIO_ATRAS)->toDateString())
                ->orderByDesc('fecha')->orderByDesc('id')
                ->get(['lago_id', 'fecha', 'tipo_concentrado', 'concentrado_id'])
                ->groupBy('lago_id'),
        ];
    }

    /** @param  array<string, Collection>  $contexto */
    private static function calcular(Lago $lago, array $contexto): array
    {
        $siembras = $lago->siembrasActivas;

        $motivos = [];
        $medicion = $lago->ultimaMedicion;
        if ($medicion && $medicion->medido_at->gte(now()->subHours(self::HORAS_MEDICION_VIGENTE))) {
            $motivos = $medicion->alertas($siembras->pluck('especie'));
        }
        $factor = $motivos === [] ? 1.0 : self::FACTOR_REDUCCION;

        [$conTabla, $sinTabla] = $siembras->partition(fn (Siembra $siembra) => $contexto['tramos']->has($siembra->especie_id));
        $filas = $conTabla->map(fn (Siembra $siembra) => self::fila($lago, $siembra, $factor, $contexto))->values()->all();

        $total = round(array_sum(array_column($filas, 'kg_dia')), 1);
        $tomas = $filas === [] ? 0 : max(array_column($filas, 'tomas'));

        // Concentrado sugerido del lago: el del lote de mayor biomasa que tenga uno
        $principal = collect($filas)->sortByDesc('biomasa_kg')->first(fn (array $fila) => $fila['concentrado_id'] !== null);
        $sugerido = $principal !== null ? $contexto['concentrados'][$principal['concentrado_id']] : null;

        return [
            'filas' => $filas,
            'total_kg_dia' => $total,
            'tomas' => $tomas,
            'kg_por_toma' => $tomas > 0 ? round($total / $tomas, 1) : 0.0,
            'factor' => $factor,
            'motivos' => $motivos,
            'sin_sugerencia' => $sinTabla->pluck('nombre_especie')->unique()->values()->all(),
            // El texto con que está en bodega (el que se elige en los formularios y en Registro rápido) o su nombre
            'concentrado_sugerido' => $sugerido !== null ? ($contexto['texto_en_bodega'][$sugerido->id] ?? $sugerido->nombre) : null,
            'concentrado_etiqueta' => $sugerido?->etiqueta(),
            'cambio_gradual' => $sugerido !== null && $total > 0
                ? self::cambioGradual($contexto['recientes']->get($lago->id, collect()), $sugerido, $total, $contexto['concentrados'])
                : null,
        ];
    }

    /**
     * @param  array<string, Collection>  $contexto
     * @return array<string, mixed>
     */
    private static function fila(Lago $lago, Siembra $siembra, float $factor, array $contexto): array
    {
        $peso = (float) $siembra->peso_actual_g;
        $etapa = self::etapa($peso);
        $tramos = $contexto['tramos']->get($siembra->especie_id);
        $tramo = $tramos->first(fn (TablaRacion $t) => $peso <= $t->peso_hasta_g) ?? $tramos->last();
        $porcentaje = (float) $tramo->porcentaje_biomasa;

        // Proteína: la del tramo de la finca o, si no tiene, la de referencia de la especie para su etapa
        $proteina = $tramo->proteina_porcentaje !== null
            ? Formato::numero($tramo->proteina_porcentaje, fmod($tramo->proteina_porcentaje, 1.0) === 0.0 ? 0 : 1).' %'
            : ($siembra->especie?->referencia()['proteina'][$etapa] ?? null);
        $concentrado = self::elegirConcentrado($lago, $siembra, $tramo, $contexto);

        return [
            'siembra_id' => $siembra->id,
            'especie' => $siembra->nombre_especie,
            'biomasa_kg' => (float) $siembra->biomasa_kg,
            'porcentaje' => $porcentaje,
            'kg_dia' => round((float) $siembra->biomasa_kg * $porcentaje / 100 * $factor, 1),
            'tomas' => (int) $tramo->tomas_dia,
            'etapa' => $etapa,
            'proteina' => $proteina,
            'pellet' => $tramo->pellet,
            'concentrado_id' => $concentrado?->id,
            'concentrado' => $concentrado?->nombre,
            'concentrado_corto' => $concentrado?->nombre_corto,
            'concentrado_etiqueta' => $concentrado?->etiqueta(),
        ];
    }

    /**
     * El de la fase del lago para la especie; si no, el fijo del tramo; si no, uno del catálogo con la misma proteína
     * del tramo (primero el que hay en bodega). Solo concentrados activos.
     *
     * @param  array<string, Collection>  $contexto
     */
    private static function elegirConcentrado(Lago $lago, Siembra $siembra, TablaRacion $tramo, array $contexto): ?Concentrado
    {
        $activo = fn (?int $id) => $id !== null && ($contexto['concentrados'][$id] ?? null)?->activo ? $contexto['concentrados'][$id] : null;

        $deFase = $lago->fase_id !== null ? $activo($contexto['concentrado_fase'][$lago->fase_id.'|'.$siembra->especie_id] ?? null) : null;
        if ($deFase !== null) {
            return $deFase;
        }
        if (($delTramo = $activo($tramo->concentrado_id)) !== null) {
            return $delTramo;
        }
        if ($tramo->proteina_porcentaje === null) {
            return null;
        }

        $candidatos = $contexto['concentrados']->filter(fn (Concentrado $c) => $c->activo && $c->proteina_porcentaje !== null
            && abs($c->proteina_porcentaje - $tramo->proteina_porcentaje) < 0.01);

        return $candidatos->first(fn (Concentrado $c) => ($contexto['kg_en_bodega'][$c->id] ?? 0) > 0) ?? $candidatos->first();
    }

    /**
     * Si el lago venía comiendo otro concentrado en los últimos días y ahora le toca el sugerido, dice en qué día del
     * cambio gradual (1: ¼, 2: ½, 3: ¾) va, hasta completar los 3 días. Compara por el concentrado del catálogo (un
     * nombre viejo sigue siendo el mismo concentrado) y, en los registros sin catálogo, por el texto.
     *
     * @param  Collection<int, MovimientoAlimento>  $recientes  raciones de los últimos días del lago, de la más nueva a la más vieja
     * @param  Collection<int, Concentrado>  $concentrados
     */
    private static function cambioGradual(Collection $recientes, Concentrado $sugerido, float $totalKgDia, Collection $concentrados): ?array
    {
        $esElSugerido = fn (MovimientoAlimento $m) => $m->concentrado_id !== null
            ? $m->concentrado_id === $sugerido->id
            : in_array(mb_strtolower($m->tipo_concentrado), array_filter([mb_strtolower($sugerido->nombre), mb_strtolower((string) $sugerido->nombre_corto)]), true);

        $anterior = $recientes->first(fn (MovimientoAlimento $m) => ! $esElSugerido($m));
        if ($anterior === null) {
            return null;
        }

        // Días distintos en que ya comió el concentrado nuevo
        $diasConNuevo = $recientes->filter($esElSugerido)->map(fn (MovimientoAlimento $m) => $m->fecha->toDateString())->unique()->count();
        if ($diasConNuevo >= 3) {
            return null;
        }

        $textoAnterior = $anterior->concentrado_id !== null && isset($concentrados[$anterior->concentrado_id])
            ? $concentrados[$anterior->concentrado_id]->etiqueta()
            : $anterior->tipo_concentrado;

        return self::planCambioGradual($totalKgDia, $textoAnterior, $sugerido->etiqueta(), $diasConNuevo + 1);
    }
}
