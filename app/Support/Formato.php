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

    /** Número con hasta 2 decimales y sin ceros de más (2; 35,5; 1.234,25), con los separadores de la finca. */
    public static function sinCeros(int|float|string|null $valor): string
    {
        [$decimal] = Region::separadores();
        $texto = self::numero($valor, 2);

        return str_contains($texto, $decimal) ? rtrim(rtrim($texto, '0'), $decimal) : $texto;
    }

    /** Número con los separadores decimal y de miles de la región de la finca. */
    public static function numero(int|float|string|null $valor, int $decimales = 0): string
    {
        [$decimal, $miles] = Region::separadores();

        return number_format((float) $valor, $decimales, $decimal, $miles);
    }
}
