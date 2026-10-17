<?php

namespace App\Support\Ventas;

use App\Models\PrecioEspecie;
use App\Models\Tercero;
use App\Models\User;
use App\Models\Venta;
use App\Support\Ajustes\AjustesFinca;
use App\Support\Ajustes\CatalogoAjustes;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Cupo de pescado del trabajador (docs/diseno/dia-de-proceso.md, tema 8). Opcional: con ventas.cupo_kg en 0 no hay
 * cupo y todos pagan el precio general. Es igual para fijos (con cuenta) y jornaleros; los particulares no tienen cupo.
 * - El período (semanal de lunes a domingo, quincenal del 1 al 15 y del 16 a fin de mes, o mensual) se cuenta con las
 *   fechas de la finca: la venta guarda el día de la jornada, que ya es día del calendario de la finca, nunca de UTC.
 * - Lo usado es la suma de kg_cupo de las ventas de esa persona en el período.
 * - Una venta de una especie que entra en el cupo se parte: lo que cabe en el cupo al precio de trabajador y el resto
 *   (el exceso) al precio general.
 */
final class Cupo
{
    public const SEMANAL = 'semanal';

    public const QUINCENAL = 'quincenal';

    public const MENSUAL = 'mensual';

    public const PERIODOS = [self::SEMANAL, self::QUINCENAL, self::MENSUAL];

    public static function kg(): int
    {
        return (int) AjustesFinca::valor(CatalogoAjustes::VENTAS_CUPO_KG);
    }

    /**
     * Primer y último día del período del cupo que contiene la fecha (días del calendario de la finca).
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public static function periodo(CarbonInterface $fecha): array
    {
        $dia = CarbonImmutable::parse($fecha->toDateString());

        return match (AjustesFinca::valor(CatalogoAjustes::VENTAS_CUPO_PERIODO)) {
            self::QUINCENAL => $dia->day <= 15
                ? [$dia->startOfMonth(), $dia->startOfMonth()->addDays(14)]
                : [$dia->startOfMonth()->addDays(15), $dia->endOfMonth()->startOfDay()],
            self::MENSUAL => [$dia->startOfMonth(), $dia->endOfMonth()->startOfDay()],
            default => [$dia->startOfWeek(CarbonInterface::MONDAY), $dia->startOfWeek(CarbonInterface::MONDAY)->addDays(6)],
        };
    }

    /** Kg ya comprados dentro del cupo por esa persona en el período de la fecha. */
    public static function usado(?User $fijo, ?Tercero $jornalero, CarbonInterface $fecha): float
    {
        if ($fijo === null && $jornalero === null) {
            return 0.0;
        }
        [$desde, $hasta] = self::periodo($fecha);

        return round((float) Venta::query()
            ->when($fijo, fn ($q) => $q->where('comprador_id', $fijo->id))
            ->when($jornalero, fn ($q) => $q->where('tercero_id', $jornalero->id))
            ->whereBetween('fecha', [$desde->toDateString(), $hasta->toDateString()])
            ->sum('kg_cupo'), 2);
    }

    /**
     * Parte una venta entre cupo y exceso con los precios de la especie.
     *
     * @return array{kg_cupo: float, precio_cupo: ?int, kg_exceso: float, precio_general: int, total: int, quedaba: ?float}
     */
    public static function repartir(float $kg, PrecioEspecie $precio, ?User $fijo, ?Tercero $jornalero, CarbonInterface $fecha): array
    {
        $kg = round($kg, 2);
        $cupo = self::kg();
        $aplica = $precio->entra_en_cupo && $cupo > 0 && ($fijo !== null || $jornalero !== null);
        $quedaba = $aplica ? max(0.0, round($cupo - self::usado($fijo, $jornalero, $fecha), 2)) : null;
        $kgCupo = $aplica ? min($kg, $quedaba) : 0.0;
        $kgExceso = round($kg - $kgCupo, 2);
        $total = (int) round($kgCupo * (int) $precio->precio_cupo) + (int) round($kgExceso * $precio->precio_general);

        return [
            'kg_cupo' => $kgCupo,
            'precio_cupo' => $aplica ? (int) $precio->precio_cupo : null,
            'kg_exceso' => $kgExceso,
            'precio_general' => $precio->precio_general,
            'total' => $total,
            'quedaba' => $quedaba,
        ];
    }
}
