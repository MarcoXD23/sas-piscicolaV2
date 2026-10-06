<?php

namespace App\Support;

use App\Models\Lago;
use App\Models\Mortalidad;
use App\Models\MovimientoAlimento;
use App\Models\Siembra;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;

/**
 * Cifras de producción de la finca para los dashboards y reportes.
 */
class Indicadores
{
    public const DIAS_SERIE = 30;

    /** @return array{biomasa_kg: float, peces_vivos: int, lagos_activos: int} */
    public static function produccionActual(): array
    {
        return [
            'biomasa_kg' => (float) Siembra::activas()->sum('biomasa_kg'),
            'peces_vivos' => (int) Siembra::activas()->sum('peces_vivos'),
            'lagos_activos' => Lago::has('siembrasActivas')->count(),
        ];
    }

    /**
     * Mortalidad de los últimos días: peces muertos y porcentaje sobre la población
     * (vivos de hoy más los que murieron en el período).
     *
     * @return array{muertos: int, porcentaje: float}
     */
    public static function mortalidadReciente(int $dias = self::DIAS_SERIE): array
    {
        $muertos = (int) Mortalidad::where('fecha', '>', today()->subDays($dias))->sum('cantidad');
        $poblacion = (int) Siembra::activas()->sum('peces_vivos') + $muertos;

        return [
            'muertos' => $muertos,
            'porcentaje' => $poblacion > 0 ? round($muertos / $poblacion * 100, 1) : 0.0,
        ];
    }

    public static function alimentoReciente(int $dias = self::DIAS_SERIE): float
    {
        return (float) MovimientoAlimento::where('tipo_movimiento', 'salida')->where('fecha', '>', today()->subDays($dias))->sum('cantidad_kg');
    }

    /** @return Collection<int, array{etiqueta: string, valor: float}> Biomasa de cada lago con siembras activas, de mayor a menor */
    public static function biomasaPorLago(): Collection
    {
        return Lago::query()->withSum('siembrasActivas as biomasa', 'biomasa_kg')->get()
            ->filter(fn (Lago $lago) => (float) $lago->biomasa > 0)
            ->map(fn (Lago $lago) => ['etiqueta' => $lago->nombre, 'valor' => round((float) $lago->biomasa, 2)])
            ->sortByDesc('valor')
            ->values();
    }

    /** @return Collection<int, array{fecha: CarbonImmutable, valor: float}> Peces muertos por día */
    public static function mortalidadDiaria(int $dias = self::DIAS_SERIE): Collection
    {
        $totales = Mortalidad::where('fecha', '>', today()->subDays($dias))
            ->groupBy('fecha')->selectRaw('fecha, SUM(cantidad) AS total')->pluck('total', 'fecha');

        return self::rellenarDias($totales, $dias);
    }

    /** @return Collection<int, array{fecha: CarbonImmutable, valor: float}> Kg de alimento suministrado por día */
    public static function alimentoDiario(int $dias = self::DIAS_SERIE): Collection
    {
        $totales = MovimientoAlimento::where('tipo_movimiento', 'salida')->where('fecha', '>', today()->subDays($dias))
            ->groupBy('fecha')->selectRaw('fecha, SUM(cantidad_kg) AS total')->pluck('total', 'fecha');

        return self::rellenarDias($totales, $dias);
    }

    /**
     * Una entrada por cada día del período (los días sin registros valen 0), del más antiguo a hoy.
     *
     * @param  Collection<string, mixed>  $totales  fecha => total
     */
    private static function rellenarDias(Collection $totales, int $dias): Collection
    {
        $porFecha = $totales->mapWithKeys(fn ($total, $fecha) => [CarbonImmutable::parse($fecha)->toDateString() => (float) $total]);

        return collect(CarbonPeriod::create(today()->subDays($dias - 1), today()))
            ->map(fn ($dia) => ['fecha' => CarbonImmutable::instance($dia), 'valor' => $porFecha[$dia->toDateString()] ?? 0.0])
            ->values();
    }
}
