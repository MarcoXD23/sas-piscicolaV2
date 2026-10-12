<?php

namespace App\Support;

/**
 * Unidades de peso (g, kg, lb) con aritmética ENTERA, para que el servidor y el teléfono (resources/js/unidades-peso.js)
 * conviertan y redondeen exactamente igual: nada de flotantes (1,005 se redondea distinto en PHP y en JavaScript).
 * - Los pesos se manejan en centésimas de gramo (enteros). Cada unidad es una fracción exacta de centésimas de gramo:
 *   1 g = 100, 1 kg = 100.000, 1 lb = 453,59237 g = 45.359.237 / 1.000 centésimas.
 * - Redondeo: mitad hacia arriba (todos los valores son positivos).
 * - Un valor escrito es válido si es un número con hasta 6 cifras enteras y 3 decimales (coma o punto). Así las cuentas
 *   nunca desbordan un entero de 64 bits. Los casos compartidos con la prueba de JavaScript están en
 *   tests/fixtures/unidades-peso.json.
 * Por ahora lo usa solo el muestreo; sirve igual para ración o cosecha.
 */
final class UnidadesPeso
{
    public const UNIDADES = ['g', 'kg', 'lb'];

    public const GRAMOS = 'g';

    /** Centésimas de gramo por unidad, como fracción exacta [numerador, denominador]. */
    public const FACTORES = [
        'g' => [100, 1],
        'kg' => [100000, 1],
        'lb' => [45359237, 1000],
    ];

    /** Hasta 6 cifras enteras y 3 decimales, con coma o punto. */
    private const FORMATO = '/^\d{1,6}([.,]\d{1,3})?$/';

    /** @return array<string, string> código => texto */
    public static function opciones(): array
    {
        return collect(self::UNIDADES)->mapWithKeys(fn (string $unidad) => [$unidad => __('comun.unidades_peso.'.$unidad)])->all();
    }

    public static function valida(?string $unidad): bool
    {
        return in_array($unidad, self::UNIDADES, true);
    }

    /** ¿El texto es un número válido de tamaño razonable (antes de convertir)? */
    public static function numeroValido(mixed $valor): bool
    {
        return (is_string($valor) || is_int($valor) || is_float($valor)) && preg_match(self::FORMATO, (string) $valor) === 1;
    }

    /** Valor escrito en la unidad → centésimas de gramo (entero), o null si el número no es válido. */
    public static function aCentesimas(mixed $valor, string $unidad): ?int
    {
        if (! self::numeroValido($valor) || ! self::valida($unidad)) {
            return null;
        }
        [$entero, $decimales] = array_pad(explode('.', str_replace(',', '.', (string) $valor)), 2, '');
        // valor = mantisa / 10^decimales
        $mantisa = (int) ($entero.$decimales);
        [$numerador, $denominador] = self::FACTORES[$unidad];

        return self::dividirRedondeando($mantisa * $numerador, (10 ** strlen($decimales)) * $denominador);
    }

    /** Centésimas de gramo → milésimas de la unidad (para mostrar, por ejemplo 0,138 lb). */
    public static function aMilesimas(int $centesimas, string $unidad): int
    {
        [$numerador, $denominador] = self::FACTORES[$unidad];

        // unidad = centésimas / (numerador / denominador); milésimas = × 1000
        return self::dividirRedondeando($centesimas * $denominador * 1000, $numerador);
    }

    /** Promedio por pez en centésimas de gramo. */
    public static function promedio(int $centesimas, int $peces): int
    {
        return self::dividirRedondeando($centesimas, $peces);
    }

    /** Gramos con 2 decimales (para guardar en las columnas decimal). */
    public static function gramos(int $centesimas): float
    {
        return $centesimas / 100;
    }

    /** Gramos (columna decimal) → centésimas, sin pasar por el redondeo de flotantes. */
    public static function centesimasDeGramos(float|string $gramos): int
    {
        return (int) round((float) $gramos * 100);
    }

    /** Texto «62,53 g» o, en otra unidad, «62,53 g (0,138 lb)». */
    public static function texto(int $centesimas, string $unidad): string
    {
        $gramos = Formato::numero($centesimas / 100, 2).' g';

        return $unidad === self::GRAMOS ? $gramos : $gramos.' ('.Formato::numero(self::aMilesimas($centesimas, $unidad) / 1000, 3).' '.$unidad.')';
    }

    /** Mayor valor de la unidad (con 3 decimales) que no pasa de esas centésimas de gramo. */
    public static function maximoEnUnidad(int $centesimas, string $unidad): string
    {
        [$numerador, $denominador] = self::FACTORES[$unidad];
        $milesimas = intdiv($centesimas * $denominador * 1000, $numerador);

        return Formato::numero($milesimas / 1000, $unidad === self::GRAMOS ? 1 : 3);
    }

    /** a ÷ b redondeado a la mitad hacia arriba, con enteros positivos. */
    private static function dividirRedondeando(int $a, int $b): int
    {
        return intdiv(2 * $a + $b, 2 * $b);
    }
}
