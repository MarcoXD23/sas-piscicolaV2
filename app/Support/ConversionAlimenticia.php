<?php

namespace App\Support;

use App\Models\Cosecha;
use App\Models\Lago;
use App\Models\Mortalidad;
use App\Models\MovimientoAlimento;
use App\Models\Muestreo;
use App\Models\Siembra;
use App\Models\Traslado;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Conversión alimenticia (FCA) = kg de alimento suministrado / kg de biomasa ganada.
 *
 * - Alimento: salidas de bodega hacia el lago. En policultivo cada salida se reparte entre las siembras activas
 *   de ese día en proporción a su biomasa (estimado).
 * - Biomasa ganada = biomasa final − biomasa inicial + kg cosechados + biomasa trasladada a otro lago − biomasa recibida.
 *   Los peces muertos no se suman (FCA económico).
 * - Biomasa en una fecha = peces en esa fecha × peso promedio. El peso sale de la siembra, los muestreos, las cosechas y
 *   los traslados de salida, interpolado en línea recta entre esos puntos. Después del último no se inventa peso:
 *   el FCA se calcula hasta la última fecha con peso conocido.
 * - Si la ganancia es cero o negativa el FCA no se calcula (se devuelve null y el motivo).
 *
 * Las fechas son días del calendario de la finca (texto Y-m-d).
 */
class ConversionAlimenticia
{
    public const SIN_PESO = 'sin_peso';

    public const SIN_GANANCIA = 'sin_ganancia';

    public const SIN_DATOS = 'sin_datos';

    /** @var array<int, array<string, mixed>> Eventos de cada siembra (cache de la consulta). */
    private array $ciclos = [];

    /** @var array<int, array<string, float>> Alimento por día de cada lago. */
    private array $alimento = [];

    /** @var array<int, Collection<int, Siembra>> Siembras (activas o no) de cada lago. */
    private array $siembrasDelLago = [];

    /**
     * Ciclo actual de cada lago con siembras activas: cada siembra y el total del lago.
     *
     * @return Collection<int, array{lago: Lago, siembras: list<array<string, mixed>>, total: array<string, mixed>}>
     */
    public function porLago(): Collection
    {
        return Lago::query()->has('siembrasActivas')->with('siembrasActivas')->orderBy('nombre')->get()
            ->map(function (Lago $lago) {
                $filas = $lago->siembrasActivas->map(fn (Siembra $siembra) => ['siembra' => $siembra] + $this->deSiembra($siembra))->all();

                return ['lago' => $lago, 'siembras' => $filas, 'total' => $this->sumar($filas)];
            });
    }

    /**
     * Todas las siembras (activas y finalizadas) agrupadas por especie.
     *
     * @return Collection<string, array{especie: string, siembras: int} & array<string, mixed>>
     */
    public function porEspecie(): Collection
    {
        return Siembra::query()->get()->groupBy('especie')
            ->map(function (Collection $siembras, string $especie) {
                $filas = $siembras->map(fn (Siembra $siembra) => $this->deSiembra($siembra))->all();

                return ['especie' => $siembras->first()->nombre_especie, 'siembras' => $siembras->count()] + $this->sumar($filas);
            })
            ->sortBy('especie')->values();
    }

    /**
     * FCA de una siembra entre dos fechas. Sin $desde, desde el día de la siembra (biomasa inicial);
     * con $desde, desde el final de ese día. Sin $hasta, hasta la última fecha con peso conocido.
     *
     * @return array{alimento_kg: float, ganancia_kg: float, fca: ?float, motivo: ?string, hasta: ?string}
     */
    public function deSiembra(Siembra $siembra, ?string $desde = null, ?string $hasta = null): array
    {
        $ciclo = $this->ciclo($siembra);
        $ultimoPeso = array_key_last($ciclo['pesos']);
        $hasta = $hasta === null ? $ultimoPeso : min($hasta, $ultimoPeso);
        $desde = $desde !== null && $desde < $ciclo['inicio'] ? null : $desde;

        if ($hasta === $ciclo['inicio'] && $desde === null) {
            return $this->resultado(0.0, 0.0, self::SIN_PESO, null);
        }
        if ($desde !== null && $hasta <= $desde) {
            return $this->resultado(0.0, 0.0, self::SIN_PESO, $hasta);
        }

        $base = $desde === null ? $ciclo['biomasa_inicial'] : $this->biomasa($ciclo, $desde);
        $entre = fn (string $fecha) => ($desde === null ? $fecha >= $ciclo['inicio'] : $fecha > $desde) && $fecha <= $hasta;

        $salidas = 0.0;
        foreach ($ciclo['salidas'] as [$fecha, , $kg]) {
            if ($entre($fecha)) {
                $salidas += $kg;
            }
        }
        $recibido = 0.0;
        foreach ($ciclo['recibidos'] as [$fecha, , $kg]) {
            if ($entre($fecha)) {
                $recibido += $kg;
            }
        }

        $ganancia = round($this->biomasa($ciclo, $hasta) - $base + $salidas - $recibido, 3);
        $alimento = round($this->alimentoDeSiembra($siembra, $desde ?? '0000-00-00', $hasta, $desde === null), 3);

        return $this->resultado($alimento, $ganancia, null, $hasta);
    }

    /**
     * Tendencia semanal de un lago (semanas de lunes a domingo, la última es la actual).
     *
     * @return list<array{semana: CarbonImmutable, alimento_kg: float, ganancia_kg: float, fca: ?float, motivo: ?string}>
     */
    public function semanal(Lago $lago, int $semanas = 12): array
    {
        $lunesActual = Region::hoy()->startOfWeek(CarbonImmutable::MONDAY);
        $siembras = $this->siembras($lago);
        $resultado = [];

        for ($i = $semanas - 1; $i >= 0; $i--) {
            $lunes = $lunesActual->subWeeks($i);
            $desde = $lunes->subDay()->toDateString();
            $hasta = $lunes->addDays(6)->toDateString();
            $alimento = 0.0;
            $ganancia = 0.0;
            $sinPeso = false;

            foreach ($siembras as $siembra) {
                $ciclo = $this->ciclo($siembra);
                if ($ciclo['inicio'] > $hasta || ($ciclo['fin'] !== null && $ciclo['fin'] <= $desde)) {
                    continue; // no estuvo activa esa semana
                }
                // Hace falta peso conocido hasta el final de la semana (o hasta que terminó la siembra)
                $necesario = $ciclo['fin'] !== null ? min($ciclo['fin'], $hasta) : $hasta;
                if (array_key_last($ciclo['pesos']) < $necesario) {
                    $sinPeso = true;

                    continue;
                }
                $parcial = $this->deSiembra($siembra, $desde, $hasta);
                $alimento += $parcial['alimento_kg'];
                $ganancia += $parcial['ganancia_kg'];
            }

            $fila = $sinPeso
                ? $this->resultado(round($this->alimentoDelLago($lago, $desde, $hasta), 3), 0.0, self::SIN_PESO, $hasta)
                : $this->resultado(round($alimento, 3), round($ganancia, 3), null, $hasta);
            $resultado[] = ['semana' => $lunes] + $fila;
        }

        return $resultado;
    }

    /** @param  list<array<string, mixed>>  $filas */
    private function sumar(array $filas): array
    {
        $alimento = round(array_sum(array_column($filas, 'alimento_kg')), 3);
        $ganancia = round(array_sum(array_column($filas, 'ganancia_kg')), 3);
        $conPeso = array_filter($filas, fn ($fila) => $fila['motivo'] !== self::SIN_PESO);

        if ($filas === []) {
            return $this->resultado(0.0, 0.0, self::SIN_DATOS, null);
        }

        return $this->resultado($alimento, $ganancia, $conPeso === [] ? self::SIN_PESO : null, null);
    }

    /** @return array{alimento_kg: float, ganancia_kg: float, fca: ?float, motivo: ?string, hasta: ?string} */
    private function resultado(float $alimento, float $ganancia, ?string $motivo, ?string $hasta): array
    {
        if ($motivo === null && $ganancia <= 0) {
            $motivo = self::SIN_GANANCIA;
        }

        return [
            'alimento_kg' => $alimento,
            'ganancia_kg' => $ganancia,
            'fca' => $motivo === null ? round($alimento / $ganancia, 2) : null,
            'motivo' => $motivo,
            'hasta' => $hasta,
        ];
    }

    /** Biomasa (kg) al final del día $fecha. */
    private function biomasa(array $ciclo, string $fecha): float
    {
        $peces = $ciclo['peces_iniciales'];
        foreach ($ciclo['movimientos'] as [$dia, $cambio]) {
            if ($dia <= $fecha) {
                $peces += $cambio;
            }
        }

        return max($peces, 0) * $this->peso($ciclo, $fecha) / 1000;
    }

    /** Peso promedio (g) en una fecha, interpolado entre los puntos conocidos. */
    private function peso(array $ciclo, string $fecha): float
    {
        $anterior = null;
        foreach ($ciclo['pesos'] as $dia => $gramos) {
            if ($dia === $fecha) {
                return $gramos;
            }
            if ($dia > $fecha) {
                if ($anterior === null) {
                    return $gramos;
                }
                [$diaAnterior, $gramosAnterior] = $anterior;
                $total = $this->dias($diaAnterior, $dia);
                $avance = $this->dias($diaAnterior, $fecha);

                return $gramosAnterior + ($gramos - $gramosAnterior) * $avance / $total;
            }
            $anterior = [$dia, $gramos];
        }

        return $anterior[1];
    }

    private function dias(string $desde, string $hasta): int
    {
        return (int) CarbonImmutable::parse($desde, 'UTC')->diffInDays(CarbonImmutable::parse($hasta, 'UTC'));
    }

    /**
     * Alimento que le corresponde a la siembra: salidas del lago entre las fechas, repartidas en policultivo
     * según la biomasa de cada siembra activa ese día.
     */
    private function alimentoDeSiembra(Siembra $siembra, string $desde, string $hasta, bool $incluirDesde): float
    {
        $total = 0.0;
        $propio = $this->ciclo($siembra);

        foreach ($this->alimentoPorDia($siembra->lago_id) as $dia => $kg) {
            $dentro = ($incluirDesde ? $dia >= max($desde, $propio['inicio']) : $dia > $desde) && $dia <= $hasta && $dia >= $propio['inicio'];
            if (! $dentro) {
                continue;
            }

            $activas = $this->siembras($siembra->lago)->filter(function (Siembra $otra) use ($dia) {
                $ciclo = $this->ciclo($otra);

                return $ciclo['inicio'] <= $dia && ($ciclo['fin'] === null || $ciclo['fin'] >= $dia);
            });

            if ($activas->count() <= 1) {
                $total += $kg;

                continue;
            }

            $biomasas = $activas->mapWithKeys(fn (Siembra $otra) => [$otra->id => $this->biomasa($this->ciclo($otra), $dia)]);
            $suma = $biomasas->sum();
            $total += $suma > 0 ? $kg * $biomasas[$siembra->id] / $suma : $kg / $activas->count();
        }

        return $total;
    }

    private function alimentoDelLago(Lago $lago, string $desde, string $hasta): float
    {
        return array_sum(array_filter($this->alimentoPorDia($lago->id), fn ($dia) => $dia > $desde && $dia <= $hasta, ARRAY_FILTER_USE_KEY));
    }

    /** @return array<string, float> día => kg */
    private function alimentoPorDia(int $lagoId): array
    {
        return $this->alimento[$lagoId] ??= MovimientoAlimento::query()
            ->where('lago_id', $lagoId)->where('tipo_movimiento', 'salida')
            ->groupBy('fecha')->orderBy('fecha')
            ->selectRaw('fecha, SUM(cantidad_kg) AS total')
            ->get()
            ->mapWithKeys(fn ($fila) => [CarbonImmutable::parse($fila->getRawOriginal('fecha'))->toDateString() => (float) $fila->total])
            ->all();
    }

    /** @return Collection<int, Siembra> */
    private function siembras(Lago $lago): Collection
    {
        return $this->siembrasDelLago[$lago->id] ??= Siembra::query()->where('lago_id', $lago->id)->get();
    }

    /** Eventos de la siembra ordenados por fecha. */
    private function ciclo(Siembra $siembra): array
    {
        if (isset($this->ciclos[$siembra->id])) {
            return $this->ciclos[$siembra->id];
        }

        $inicio = $siembra->fecha->toDateString();
        $pesos = [$inicio => (float) $siembra->peso_promedio_inicial_g];
        $movimientos = [];
        $salidas = [];
        $recibidos = [];

        foreach (Muestreo::query()->where('siembra_id', $siembra->id)->orderBy('fecha')->orderBy('id')->get() as $muestreo) {
            $pesos[$muestreo->fecha->toDateString()] = (float) $muestreo->peso_promedio_g;
        }
        foreach (Mortalidad::query()->where('siembra_id', $siembra->id)->get() as $mortalidad) {
            $movimientos[] = [$mortalidad->fecha->toDateString(), -(int) $mortalidad->cantidad];
        }
        foreach (Cosecha::query()->where('siembra_id', $siembra->id)->get() as $cosecha) {
            $dia = $cosecha->fecha->toDateString();
            $movimientos[] = [$dia, -(int) $cosecha->peces];
            $salidas[] = [$dia, (int) $cosecha->peces, (float) $cosecha->kg];
            $pesos[$dia] = (float) $cosecha->peso_promedio_g;
        }
        foreach (Traslado::query()->where('siembra_origen_id', $siembra->id)->get() as $traslado) {
            $dia = $traslado->fecha->toDateString();
            $movimientos[] = [$dia, -(int) $traslado->cantidad];
            $salidas[] = [$dia, (int) $traslado->cantidad, (float) $traslado->biomasa_kg];
            $pesos[$dia] = (float) $traslado->peso_promedio_g;
        }

        // Traslados que llegan: el que creó la siembra ya son sus alevinos iniciales; los demás se suman a ella
        $llegadas = Traslado::query()->where('siembra_destino_id', $siembra->id)->orderBy('id')->get();
        $creadora = $llegadas->first(fn (Traslado $t) => $t->fecha->toDateString() === $inicio
            && (int) $t->cantidad === (int) $siembra->cantidad_alevinos
            && abs((float) $t->peso_promedio_g - (float) $siembra->peso_promedio_inicial_g) < 0.01);
        foreach ($llegadas as $traslado) {
            if ($creadora !== null && $traslado->is($creadora)) {
                continue;
            }
            $dia = $traslado->fecha->toDateString();
            $movimientos[] = [$dia, (int) $traslado->cantidad];
            $recibidos[] = [$dia, (int) $traslado->cantidad, (float) $traslado->biomasa_kg];
        }

        ksort($pesos);

        return $this->ciclos[$siembra->id] = [
            'inicio' => $inicio,
            'fin' => $siembra->finalizada_at ? Region::local($siembra->finalizada_at)->toDateString() : null,
            'peces_iniciales' => (int) $siembra->cantidad_alevinos,
            'biomasa_inicial' => (int) $siembra->cantidad_alevinos * (float) $siembra->peso_promedio_inicial_g / 1000,
            'pesos' => $pesos,
            'movimientos' => $movimientos,
            'salidas' => $salidas,
            'recibidos' => $recibidos,
        ];
    }
}
