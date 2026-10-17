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

    /** Número de lote de cada siembra: lo escribe la finca o lo genera la app (lo aplica la fase F5; ver NumeroLote). */
    public const LOTES_NUMERACION = 'lotes.numeracion';

    public const NUMERACION_MANUAL = 'manual';

    public const NUMERACION_AUTOMATICA = 'automatica';

    /** Verificación en dos pasos obligatoria para propietario y administrador (DosPasosObligatoria). */
    public const SEGURIDAD_DOS_PASOS = 'seguridad.dos_pasos_obligatoria';

    /** La finca procesa con jornadas (lo aplica la fase F6a). Apagada: menú y rutas de Jornadas no existen. */
    public const PROCESO_JORNADAS = 'proceso.jornadas';

    /** Días de ayuno antes de la pesca: el lago de una jornada no se alimenta desde N días antes (tema 5). */
    public const PROCESO_DIAS_AYUNO = 'proceso.dias_ayuno';

    /** El día de la jornada no se alimenta en la finca (los fijos ayudan en el proceso; tema 5). */
    public const PROCESO_SIN_ALIMENTACION = 'proceso.sin_alimentacion';

    /** «Reproducción»: kg de peces pequeños separados antes del proceso, que vuelven al lago (F6b). */
    public const PROCESO_REPRODUCCION = 'proceso.reproduccion';

    /** Puntos por debajo de la referencia desde los que el rendimiento de una especie avisa (tema 12). */
    public const PROCESO_UMBRAL_RENDIMIENTO = 'proceso.umbral_rendimiento';

    /** Jornadas con limpio que hacen falta para que el promedio real reemplace la referencia del catálogo. */
    public const PROCESO_JORNADAS_PROMEDIO = 'proceso.jornadas_promedio';

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
            // Por defecto manual: la finca escribe su número de lote (o lo deja vacío), como hoy
            self::LOTES_NUMERACION => [
                'seccion' => 'lotes',
                'tipo' => self::TIPO_OPCION,
                'opciones' => [self::NUMERACION_MANUAL, self::NUMERACION_AUTOMATICA],
                'defecto' => self::NUMERACION_MANUAL,
            ],
            // Por defecto apagada: ninguna finca cambia hasta que el propietario la enciende (la cosecha manual sigue igual)
            self::PROCESO_JORNADAS => [
                'seccion' => 'proceso',
                'tipo' => self::TIPO_OPCION,
                'opciones' => [self::APAGADA, self::ENCENDIDA],
                'defecto' => self::APAGADA,
            ],
            // Por defecto 1 (el día anterior, como en la finca piloto). Solo actúa con las jornadas encendidas
            self::PROCESO_DIAS_AYUNO => [
                'seccion' => 'proceso',
                'tipo' => self::TIPO_ENTERO,
                'min' => 0,
                'max' => 7,
                'defecto' => 1,
            ],
            // Por defecto encendida (finca piloto). Solo actúa con las jornadas encendidas
            self::PROCESO_SIN_ALIMENTACION => [
                'seccion' => 'proceso',
                'tipo' => self::TIPO_OPCION,
                'opciones' => [self::APAGADA, self::ENCENDIDA],
                'defecto' => self::ENCENDIDA,
            ],
            // Por defecto apagada (la finca piloto no la usa): sin ella no se ve nada de reproducción
            self::PROCESO_REPRODUCCION => [
                'seccion' => 'proceso',
                'tipo' => self::TIPO_OPCION,
                'opciones' => [self::APAGADA, self::ENCENDIDA],
                'defecto' => self::APAGADA,
            ],
            // Por defecto 5 puntos por debajo de la referencia
            self::PROCESO_UMBRAL_RENDIMIENTO => [
                'seccion' => 'proceso',
                'tipo' => self::TIPO_ENTERO,
                'min' => 1,
                'max' => 50,
                'defecto' => 5,
            ],
            // Por defecto 5 jornadas: el promedio real (ponderado por el entero) de las últimas 5 con limpio
            self::PROCESO_JORNADAS_PROMEDIO => [
                'seccion' => 'proceso',
                'tipo' => self::TIPO_ENTERO,
                'min' => 1,
                'max' => 50,
                'defecto' => 5,
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
