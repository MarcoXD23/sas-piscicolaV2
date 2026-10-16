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
 * - Tipos: «opcion» (una de una lista) y «entero» (un número entre min y max). Otros tipos se agregan cuando un
 *   ajuste los necesite.
 */
final class CatalogoAjustes
{
    /** Quién mide la calidad del agua (lo aplica la fase F4: pantallas y registro sin conexión). */
    public const AGUA_QUIEN_MIDE = 'agua.quien_mide';

    public const QUIEN_MIDE_AMBOS = 'ambos';

    public const QUIEN_MIDE_CELADOR = 'celador';

    public const QUIEN_MIDE_ADMINISTRADOR = 'administrador';

    /** Días sin muestreo después de los cuales un lago con peces avisa «muestreo vencido» (lo aplica la fase F3). */
    public const MUESTREO_PLAZO_DIAS = 'muestreo.plazo_dias';

    /** Verificación en dos pasos obligatoria para propietario y administrador (DosPasosObligatoria). */
    public const SEGURIDAD_DOS_PASOS = 'seguridad.dos_pasos_obligatoria';

    public const APAGADA = 'apagada';

    public const ENCENDIDA = 'encendida';

    public const TIPO_OPCION = 'opcion';

    public const TIPO_ENTERO = 'entero';

    /**
     * @return array<string, array{seccion: string, tipo: string, opciones?: list<string>, min?: int, max?: int, defecto: string|int}>
     */
    public static function todos(): array
    {
        return [
            // Por defecto «ambos»: como hoy (el administrador desde Lagos y el celador desde Seguridad & Noche)
            self::AGUA_QUIEN_MIDE => [
                'seccion' => 'agua',
                'tipo' => self::TIPO_OPCION,
                'opciones' => [self::QUIEN_MIDE_AMBOS, self::QUIEN_MIDE_CELADOR, self::QUIEN_MIDE_ADMINISTRADOR],
                'defecto' => self::QUIEN_MIDE_AMBOS,
            ],
            // Por defecto 30 días (diseño, tema 15.3: muestreo cada 30 días)
            self::MUESTREO_PLAZO_DIAS => [
                'seccion' => 'muestreo',
                'tipo' => self::TIPO_ENTERO,
                'min' => 1,
                'max' => 180,
                'defecto' => 30,
            ],
            // Por defecto apagada: como hoy (los dos pasos son opcionales para todos)
            self::SEGURIDAD_DOS_PASOS => [
                'seccion' => 'seguridad',
                'tipo' => self::TIPO_OPCION,
                'opciones' => [self::APAGADA, self::ENCENDIDA],
                'defecto' => self::APAGADA,
            ],
        ];
    }

    public static function existe(string $clave): bool
    {
        return array_key_exists($clave, self::todos());
    }

    /** @return array{seccion: string, tipo: string, opciones?: list<string>, min?: int, max?: int, defecto: string|int} */
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
