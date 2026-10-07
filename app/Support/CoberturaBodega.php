<?php

namespace App\Support;

use App\Models\MovimientoAlimento;
use App\Models\StockAlimento;
use Illuminate\Support\Collection;

/**
 * Estima para cuántos días alcanza cada concentrado según el consumo de los últimos días.
 */
class CoberturaBodega
{
    /** Días de consumo que se promedian. */
    public const VENTANA_DIAS = 7;

    /** Días de cobertura mínimos por defecto; cada finca puede cambiarlos en Ajustes (alerta_stock_dias). */
    public const DIAS_MINIMOS = 7;

    public const AGOTADO = 'agotado';

    public const BAJO = 'bajo';

    public const SUFICIENTE = 'suficiente';

    /**
     * @return Collection<int, array{stock: StockAlimento, consumo_diario_kg: float, dias: ?float, estado: string}>
     */
    public static function calcular(): Collection
    {
        $consumos = MovimientoAlimento::query()
            ->where('tipo_movimiento', 'salida')
            ->where('fecha', '>', Region::hoy()->subDays(self::VENTANA_DIAS)->toDateString())
            ->groupBy('tipo_concentrado')
            ->selectRaw('tipo_concentrado, SUM(cantidad_kg) AS total')
            ->pluck('total', 'tipo_concentrado');

        $minimo = self::diasMinimos();

        return StockAlimento::query()->orderBy('tipo_concentrado')->get()->map(function (StockAlimento $stock) use ($consumos, $minimo) {
            $diario = round((float) ($consumos[$stock->tipo_concentrado] ?? 0) / self::VENTANA_DIAS, 2);
            $cantidad = (float) $stock->cantidad_kg;
            $dias = $diario > 0 ? round($cantidad / $diario, 1) : null;

            return [
                'stock' => $stock,
                'consumo_diario_kg' => $diario,
                'dias' => $dias,
                'estado' => match (true) {
                    $cantidad <= 0 => self::AGOTADO,
                    $dias !== null && $dias < $minimo => self::BAJO,
                    default => self::SUFICIENTE,
                },
            ];
        });
    }

    /** Por debajo de estos días de cobertura el stock se considera bajo (umbral de la finca). */
    public static function diasMinimos(): int
    {
        return (int) (Region::tenant()?->alerta_stock_dias ?: self::DIAS_MINIMOS);
    }

    /** @return Collection<int, array{stock: StockAlimento, consumo_diario_kg: float, dias: ?float, estado: string}> */
    public static function alertas(): Collection
    {
        return self::calcular()->where('estado', '!=', self::SUFICIENTE)->values();
    }
}
