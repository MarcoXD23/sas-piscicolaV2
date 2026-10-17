<?php

namespace App\Support;

use App\Models\Siembra;
use App\Models\Traslado;
use Carbon\CarbonImmutable;

/**
 * Conversión de toda la vida del pez (F5): un lote que recibió peces por traslado arrastra los días y el alimento que
 * esos peces tuvieron antes, en su lote de origen (y en el origen de ese, por toda la cadena). Se calcula, no se copia.
 *
 * - Cada salida de peces vivos de un lote (cosecha o traslado) se lleva una parte de lo acumulado y aún no repartido
 *   del lote (alimento y biomasa ganada de vida): peces que salen ÷ peces que había antes de esa salida. Las cosechas
 *   también se llevan su parte, para que nada se cuente dos veces. Los peces muertos no se llevan nada: su alimento
 *   queda en los que siguen (como el FCA del lote, que es económico).
 * - Lo acumulado de vida de un lote hasta una fecha = lo propio (ConversionAlimenticia::deSiembra, que ya resta la
 *   biomasa recibida) + la parte que trajo cada traslado recibido hasta esa fecha.
 * - FCA de vida = alimento de vida ÷ ganancia de vida. Solo es para mirar un lote: los totales por lago y por especie
 *   siguen con el FCA propio de cada lote (sumar los de vida contaría dos veces lo de los orígenes).
 * - Días de vida: un lote creado por traslado («aparte») cuenta desde la siembra original de su cadena; un lote que
 *   recibió peces sumados conserva sus propios días.
 */
final class ConversionVida
{
    /** @var array<int, array{alimento_kg: float, ganancia_kg: float}> Parte que se llevó cada traslado. */
    private array $porciones = [];

    /** @var array<int, true> Traslados en cálculo (evita vueltas si los peces regresaron a un lote de origen). */
    private array $enCurso = [];

    /** @var array<int, string> */
    private array $fechasVida = [];

    public function __construct(private readonly ConversionAlimenticia $conversion = new ConversionAlimenticia) {}

    /**
     * FCA de vida del lote hasta su última fecha con peso conocido.
     *
     * @return array{alimento_kg: float, ganancia_kg: float, fca: ?float, motivo: ?string, hasta: ?string,
     *               heredado_alimento_kg: float, heredado_ganancia_kg: float, arrastra: bool, fecha_vida: string, dias_vida: int}
     */
    public function deSiembra(Siembra $siembra): array
    {
        $propio = $this->conversion->deSiembra($siembra);
        $hasta = $propio['hasta'] ?? $siembra->fecha->toDateString();
        $heredado = $this->heredado($siembra, $hasta);
        $alimento = round($propio['alimento_kg'] + $heredado['alimento_kg'], 3);
        $ganancia = round($propio['ganancia_kg'] + $heredado['ganancia_kg'], 3);
        $motivo = $propio['motivo'] === ConversionAlimenticia::SIN_PESO && $heredado['traslados'] === 0 ? ConversionAlimenticia::SIN_PESO : null;
        if ($motivo === null && $ganancia <= 0) {
            $motivo = ConversionAlimenticia::SIN_GANANCIA;
        }

        $fechaVida = $this->fechaVida($siembra);
        $fin = $siembra->finalizada_at !== null ? Region::local($siembra->finalizada_at)->toDateString() : Region::hoy()->toDateString();

        return [
            'alimento_kg' => $alimento,
            'ganancia_kg' => $ganancia,
            'fca' => $motivo === null ? round($alimento / $ganancia, 2) : null,
            'motivo' => $motivo,
            'hasta' => $propio['hasta'],
            'heredado_alimento_kg' => round($heredado['alimento_kg'], 3),
            'heredado_ganancia_kg' => round($heredado['ganancia_kg'], 3),
            'arrastra' => $heredado['traslados'] > 0,
            'fecha_vida' => $fechaVida,
            'dias_vida' => $this->dias($fechaVida, $fin),
        ];
    }

    /** Día en que empezó la vida de los peces del lote: la siembra original de su cadena de traslados «aparte». */
    public function fechaVida(Siembra $siembra): string
    {
        return $this->fechasVida[$siembra->id] ??= $this->raiz($siembra)->fecha->toDateString();
    }

    /**
     * Lote donde empezó la vida de los peces: siguiendo los traslados que crearon cada lote «aparte» hasta la siembra.
     * Si la cadena diera la vuelta, se queda en el último lote visto.
     */
    public function raiz(Siembra $siembra): Siembra
    {
        $vistos = [];
        while ($siembra->vinoDeTraslado() && ! isset($vistos[$siembra->id])) {
            $vistos[$siembra->id] = true;
            $origen = $siembra->trasladosRecibidos()->with('siembraOrigen')->first()?->siembraOrigen;
            if ($origen === null) {
                break;
            }
            $siembra = $origen;
        }

        return $siembra;
    }

    /** Días de vida que tenían los peces de un traslado al salir de su lote de origen. */
    public function diasDeVidaAlTraslado(Traslado $traslado): ?int
    {
        $origen = $traslado->siembraOrigen;

        return $origen === null ? null : $this->dias($this->fechaVida($origen), $traslado->fecha->toDateString());
    }

    /**
     * Alimento y biomasa ganada de vida acumulados por el lote hasta el final del día $hasta (lo propio y lo heredado).
     *
     * @return array{alimento_kg: float, ganancia_kg: float}
     */
    public function acumulado(Siembra $siembra, string $hasta): array
    {
        $inicio = $siembra->fecha->toDateString();
        $propio = $hasta > $inicio ? $this->conversion->deSiembra($siembra, null, $hasta) : ['alimento_kg' => 0.0, 'ganancia_kg' => 0.0];
        $heredado = $this->heredado($siembra, $hasta);

        return [
            'alimento_kg' => $propio['alimento_kg'] + $heredado['alimento_kg'],
            'ganancia_kg' => $propio['ganancia_kg'] + $heredado['ganancia_kg'],
        ];
    }

    /**
     * Parte de lo acumulado de vida del lote de origen que se llevó un traslado.
     *
     * @return array{alimento_kg: float, ganancia_kg: float}
     */
    public function porcion(Traslado $traslado): array
    {
        if (isset($this->porciones[$traslado->id])) {
            return $this->porciones[$traslado->id];
        }
        $cero = ['alimento_kg' => 0.0, 'ganancia_kg' => 0.0];
        $origen = $traslado->siembraOrigen;
        if ($origen === null || isset($this->enCurso[$traslado->id])) {
            return $cero;
        }
        $this->enCurso[$traslado->id] = true;

        try {
            // Reparte lo acumulado del origen entre sus salidas, en orden, hasta llegar a este traslado
            $repartidoAlimento = 0.0;
            $repartidoGanancia = 0.0;
            $partidas = $this->conversion->partidas($origen);
            foreach ($partidas as $posicion => $partida) {
                $acumulado = $this->acumulado($origen, $partida['fecha']);
                // Peces que había antes de esta salida: los del final del día más los que salieron ese día desde esta
                $mismoDia = array_filter(array_slice($partidas, $posicion), fn (array $otra) => $otra['fecha'] === $partida['fecha']);
                $antes = $this->conversion->pecesAlFinalDel($origen, $partida['fecha']) + array_sum(array_column($mismoDia, 'peces'));
                $fraccion = $antes > 0 ? min(1.0, $partida['peces'] / $antes) : 0.0;

                $alimento = $fraccion * max(0.0, $acumulado['alimento_kg'] - $repartidoAlimento);
                $ganancia = $fraccion * ($acumulado['ganancia_kg'] - $repartidoGanancia);
                $repartidoAlimento += $alimento;
                $repartidoGanancia += $ganancia;

                if ($partida['tipo'] === 'traslado' && $partida['id'] === $traslado->id) {
                    return $this->porciones[$traslado->id] = ['alimento_kg' => $alimento, 'ganancia_kg' => $ganancia];
                }
            }

            return $this->porciones[$traslado->id] = $cero;
        } finally {
            unset($this->enCurso[$traslado->id]);
        }
    }

    /** @return array{alimento_kg: float, ganancia_kg: float, traslados: int} Lo que trajeron los traslados recibidos hasta $hasta. */
    private function heredado(Siembra $siembra, string $hasta): array
    {
        $alimento = 0.0;
        $ganancia = 0.0;
        $traslados = 0;
        foreach ($siembra->trasladosRecibidos as $traslado) {
            if ($traslado->fecha->toDateString() > $hasta) {
                continue;
            }
            $porcion = $this->porcion($traslado);
            $alimento += $porcion['alimento_kg'];
            $ganancia += $porcion['ganancia_kg'];
            $traslados++;
        }

        return ['alimento_kg' => $alimento, 'ganancia_kg' => $ganancia, 'traslados' => $traslados];
    }

    private function dias(string $desde, string $hasta): int
    {
        return max(0, (int) CarbonImmutable::parse($desde, 'UTC')->diffInDays(CarbonImmutable::parse($hasta, 'UTC'), false));
    }
}
