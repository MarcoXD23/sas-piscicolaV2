<?php

namespace App\Support\Proceso;

use App\Models\Jornada;
use App\Models\JornadaLago;
use App\Support\Ajustes\AjustesFinca;
use App\Support\Ajustes\CatalogoAjustes;
use App\Support\Region;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Ayuno y día sin alimentación (docs/diseno/dia-de-proceso.md, tema 5). No se guarda: se calcula de las jornadas.
 * - Ayuno: un lago de una jornada abierta está en ayuno desde N días antes de su fecha (ajuste proceso.dias_ayuno)
 *   hasta el día de la pesca. Si la jornada se cancela o se cierra, el ayuno se quita solo.
 * - Día sin alimentación: el día de una jornada abierta o cerrada que se creó con sin_alimentacion.
 * Las fechas son días del calendario de la finca (Region::hoy), nunca de UTC. Todo es falso si la finca no tiene
 * encendidas las jornadas.
 */
final class Ayuno
{
    public static function jornadasEncendidas(): bool
    {
        return AjustesFinca::valor(CatalogoAjustes::PROCESO_JORNADAS) === CatalogoAjustes::ENCENDIDA;
    }

    /**
     * Lagos en ayuno en una fecha (por defecto hoy en la finca), con la fecha de su pesca.
     *
     * @return array<int, CarbonImmutable> lago_id => fecha de la jornada
     */
    public static function lagos(?CarbonInterface $fecha = null): array
    {
        if (! self::jornadasEncendidas()) {
            return [];
        }

        $dia = CarbonImmutable::parse(($fecha ?? Region::hoy())->toDateString());
        $dias = (int) AjustesFinca::valor(CatalogoAjustes::PROCESO_DIAS_AYUNO);

        return JornadaLago::query()
            ->join('jornadas', 'jornadas.id', '=', 'jornada_lagos.jornada_id')
            ->where('jornadas.estado', Jornada::ABIERTA)
            ->whereBetween('jornadas.fecha', [$dia->toDateString(), $dia->addDays($dias)->toDateString()])
            ->orderBy('jornadas.fecha')
            ->get(['jornada_lagos.lago_id', 'jornadas.fecha'])
            ->unique('lago_id')
            ->mapWithKeys(fn ($fila) => [(int) $fila->lago_id => CarbonImmutable::parse($fila->fecha)])
            ->all();
    }

    /** Fecha de la pesca si el lago está en ayuno en esa fecha; null si no. */
    public static function delLago(int $lagoId, ?CarbonInterface $fecha = null): ?CarbonImmutable
    {
        return self::lagos($fecha)[$lagoId] ?? null;
    }

    /** Hoy (o la fecha dada) es día de proceso sin alimentación en la finca. */
    public static function diaSinAlimentacion(?CarbonInterface $fecha = null): bool
    {
        if (! self::jornadasEncendidas()) {
            return false;
        }

        return Jornada::query()->activas()->where('sin_alimentacion', true)
            ->whereDate('fecha', ($fecha ?? Region::hoy())->toDateString())->exists();
    }
}
