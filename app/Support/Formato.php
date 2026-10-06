<?php

namespace App\Support;

/**
 * Formatos de presentación en español de Colombia.
 */
class Formato
{
    /** Pesos colombianos sin decimales: $ 1.234.567 */
    public static function pesos(int|float $valor): string
    {
        return '$ '.number_format((float) $valor, 0, ',', '.');
    }

    public static function numero(int|float|string|null $valor, int $decimales = 0): string
    {
        return number_format((float) $valor, $decimales, ',', '.');
    }
}
