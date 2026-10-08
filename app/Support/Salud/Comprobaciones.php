<?php

namespace App\Support\Salud;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Comprobaciones del estado del sistema: base de datos, almacenamiento y que el programador de tareas haya corrido
 * en la última hora. Las usan /salud (solo «ok» o «falla»), piscicola:estado (detalle) y piscicola:vigilar (aviso).
 * Nunca exponen datos de las fincas.
 */
final class Comprobaciones
{
    public const NOMBRES = ['base', 'almacenamiento', 'programador'];

    /** @return array<string, bool> comprobación => ¿está bien? */
    public static function correr(): array
    {
        return [
            'base' => self::base(),
            'almacenamiento' => self::almacenamiento(),
            'programador' => self::programador(),
        ];
    }

    /** @param  array<string, bool>  $resultados */
    public static function todoBien(array $resultados): bool
    {
        return ! in_array(false, $resultados, true);
    }

    public static function base(): bool
    {
        try {
            return (int) DB::connection(config('salud.conexion'))->selectOne('SELECT 1 AS uno')->uno === 1;
        } catch (Throwable) {
            return false;
        }
    }

    /** Escribe, lee y borra un archivo de prueba. */
    public static function almacenamiento(): bool
    {
        try {
            $disco = Storage::disk(config('salud.disco'));
            $ruta = config('salud.carpeta').'/prueba-'.bin2hex(random_bytes(6)).'.txt';
            $contenido = (string) microtime(true);
            $disco->put($ruta, $contenido);
            $leido = $disco->get($ruta);
            $disco->delete($ruta);

            return $leido === $contenido;
        } catch (Throwable) {
            return false;
        }
    }

    public static function programador(): bool
    {
        $ultimo = self::ultimoLatido();

        return $ultimo !== null && $ultimo->greaterThanOrEqualTo(now()->subMinutes(config('salud.minutos_programador')));
    }

    /** Lo llama el programador de tareas cada minuto (routes/console.php). */
    public static function latido(): void
    {
        Storage::disk(config('salud.disco'))->put(config('salud.carpeta').'/programador.txt', (string) now()->getTimestamp());
    }

    public static function ultimoLatido(): ?CarbonImmutable
    {
        try {
            $disco = Storage::disk(config('salud.disco'));
            $ruta = config('salud.carpeta').'/programador.txt';

            return $disco->exists($ruta) ? CarbonImmutable::createFromTimestamp((int) trim((string) $disco->get($ruta))) : null;
        } catch (Throwable) {
            return null;
        }
    }
}
