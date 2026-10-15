<?php

namespace App\Support\Ajustes;

use InvalidArgumentException;

/**
 * Catálogo de los ajustes de operación por finca: qué ajustes existen, su tipo, sus opciones y su valor por defecto.
 * - La finca piloto es un ejemplo, no el molde: el valor por defecto conserva cómo funciona la app hoy, y cada finca
 *   pone el suyo en Ajustes › Operación de la finca. Nunca van números de una finca fijos en el código.
 * - Solo se agregan los ajustes que usa alguna función (docs/diseno/dia-de-proceso.md, tema 1: no construir opciones
 *   que nadie ha pedido). Un ajuste nuevo se agrega aquí, con sus textos en lang/es/ajustes_finca.php; no hace falta
 *   migración.
 * - Tipos: por ahora solo «opcion» (una de una lista). Otros tipos se agregan cuando un ajuste los necesite.
 */
final class CatalogoAjustes
{
    /** Quién mide la calidad del agua (lo aplica la fase F4: pantallas y registro sin conexión). */
    public const AGUA_QUIEN_MIDE = 'agua.quien_mide';

    public const QUIEN_MIDE_AMBOS = 'ambos';

    public const QUIEN_MIDE_CELADOR = 'celador';

    public const QUIEN_MIDE_ADMINISTRADOR = 'administrador';

    /**
     * @return array<string, array{seccion: string, tipo: string, opciones: list<string>, defecto: string}>
     */
    public static function todos(): array
    {
        return [
            // Por defecto «ambos»: como hoy (el administrador desde Lagos y el celador desde Seguridad & Noche)
            self::AGUA_QUIEN_MIDE => [
                'seccion' => 'agua',
                'tipo' => 'opcion',
                'opciones' => [self::QUIEN_MIDE_AMBOS, self::QUIEN_MIDE_CELADOR, self::QUIEN_MIDE_ADMINISTRADOR],
                'defecto' => self::QUIEN_MIDE_AMBOS,
            ],
        ];
    }

    public static function existe(string $clave): bool
    {
        return array_key_exists($clave, self::todos());
    }

    /** @return array{seccion: string, tipo: string, opciones: list<string>, defecto: string} */
    public static function definicion(string $clave): array
    {
        return self::todos()[$clave] ?? throw new InvalidArgumentException("Ajuste desconocido: {$clave}");
    }

    /** @return array<string, list<string>> sección => claves, en el orden del catálogo */
    public static function porSeccion(): array
    {
        return collect(self::todos())->groupBy('seccion', preserveKeys: true)->map(fn ($ajustes) => $ajustes->keys()->all())->all();
    }

    /** Nombre del campo del formulario para una clave (los puntos no sirven en nombres de campo de Laravel). */
    public static function campo(string $clave): string
    {
        return str_replace('.', '__', $clave);
    }
}
