<?php

namespace App\Support;

/**
 * Formatos de presentación según la región de la finca.
 */
class Formato
{
    /** Dinero en la moneda del país de la finca (los montos se guardan en unidades enteras). */
    public static function dinero(int|float $valor): string
    {
        return Region::dinero($valor);
    }

    /** Número con los separadores decimal y de miles de la región de la finca. */
    public static function numero(int|float|string|null $valor, int $decimales = 0): string
    {
        [$decimal, $miles] = Region::separadores();

        return number_format((float) $valor, $decimales, $decimal, $miles);
    }
}
