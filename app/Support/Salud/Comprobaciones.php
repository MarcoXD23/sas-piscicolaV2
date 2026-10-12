<?php

namespace App\Support\Salud;

use App\Console\Commands\Respaldar as ComandoRespaldar;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Comprobaciones del estado del sistema: base de datos, almacenamiento, programador de tareas, copia de seguridad,
 * espacio en disco y cola de trabajos, con los umbrales de config/salud.php. Las usan /salud (solo «ok» o «falla»),
 * piscicola:estado (detalle) y piscicola:vigilar (aviso). Nunca exponen datos de las fincas.
 */
final class Comprobaciones
{
    public const NOMBRES = ['base', 'almacenamiento', 'programador', 'copia', 'disco', 'cola'];

    /**
     * Mide el disco: fn (): ?array{libre: float, total: float} en bytes. Null = el disco real de storage.
     * Solo lo cambian las pruebas.
     */
    public static ?Closure $medirDisco = null;

    /** @return array<string, bool> comprobación => ¿está bien? */
    public static function correr(): array
    {
        return [
            'base' => self::base(),
            'almacenamiento' => self::almacenamiento(),
            'programador' => self::programador(),
            'copia' => self::copia(),
            'disco' => self::disco(),
            'cola' => self::cola(),
        ];
    }

    /** @param  array<string, bool>  $resultados */
    public static function todoBien(array $resultados): bool
    {
        return ! in_array(false, $resultados, true);
    }

    /** Nombre de la comprobación con su umbral, para la pantalla y el correo. */
    public static function etiqueta(string $nombre): string
    {
        return __('salud.comprobaciones.'.$nombre, [
            'minutos' => $nombre === 'cola' ? config('salud.minutos_cola') : config('salud.minutos_programador'),
            'horas' => config('salud.horas_copia'),
            'porcentaje' => rtrim(rtrim(number_format((float) config('salud.disco_porcentaje_minimo'), 1, ',', ''), '0'), ','),
            'gb' => rtrim(rtrim(number_format((float) config('salud.disco_gb_minimo'), 1, ',', ''), '0'), ','),
        ]);
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

        return $ultimo !== null && $ultimo->greaterThanOrEqualTo(now()->subMinutes((int) config('salud.minutos_programador')));
    }

    /**
     * La copia más reciente no tiene más de horas_copia. Sin RESPALDOS_CARPETA, falla solo si exigir_copia
     * (producción); con la carpeta configurada, falla si no hay copias.
     */
    public static function copia(): bool
    {
        if (blank(config('respaldos.carpeta'))) {
            return ! config('salud.exigir_copia');
        }
        $ultima = self::ultimaCopia();

        return $ultima !== null && $ultima['fecha']->greaterThanOrEqualTo(now()->subHours((int) config('salud.horas_copia')));
    }

    /** El espacio libre no baja del porcentaje mínimo del total ni de los GB mínimos. */
    public static function disco(): bool
    {
        $espacio = self::espacioDisco();
        if ($espacio === null || $espacio['total'] <= 0) {
            return false;
        }

        return (float) config('salud.disco_porcentaje_minimo') <= $espacio['libre'] / $espacio['total'] * 100
            && $espacio['libre'] >= (float) config('salud.disco_gb_minimo') * 1024 ** 3;
    }

    /** Ningún trabajo lleva más de minutos_cola esperando y no hay trabajos fallidos. */
    public static function cola(): bool
    {
        $cola = self::estadoCola();

        return $cola !== null && $cola['atascados'] === 0 && $cola['fallidos'] === 0;
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

    /** @return array{archivo: string, fecha: CarbonImmutable, bytes: int}|null la copia más reciente de RESPALDOS_CARPETA */
    public static function ultimaCopia(): ?array
    {
        $carpeta = config('respaldos.carpeta');
        if (blank($carpeta)) {
            return null;
        }
        $copias = glob(rtrim($carpeta, '\\/').DIRECTORY_SEPARATOR.ComandoRespaldar::PREFIJO.'*.dump') ?: [];
        if ($copias === []) {
            return null;
        }
        clearstatcache();
        usort($copias, fn ($a, $b) => filemtime($b) <=> filemtime($a));

        return [
            'archivo' => basename($copias[0]),
            'fecha' => CarbonImmutable::createFromTimestamp(filemtime($copias[0])),
            'bytes' => (int) filesize($copias[0]),
        ];
    }

    /** @return array{libre: float, total: float}|null bytes del disco de storage */
    public static function espacioDisco(): ?array
    {
        if (self::$medirDisco !== null) {
            return (self::$medirDisco)();
        }
        $libre = @disk_free_space(storage_path());
        $total = @disk_total_space(storage_path());

        return $libre === false || $total === false ? null : ['libre' => (float) $libre, 'total' => (float) $total];
    }

    /** @return array{pendientes: int, atascados: int, fallidos: int}|null */
    public static function estadoCola(): ?array
    {
        try {
            $trabajos = config('queue.connections.database.table', 'jobs');
            $fallidos = config('queue.failed.table', 'failed_jobs');
            $limite = now()->subMinutes((int) config('salud.minutos_cola'))->getTimestamp();

            return [
                'pendientes' => Schema::hasTable($trabajos) ? DB::table($trabajos)->count() : 0,
                'atascados' => Schema::hasTable($trabajos) ? DB::table($trabajos)->where('available_at', '<', $limite)->count() : 0,
                'fallidos' => Schema::hasTable($fallidos) ? DB::table($fallidos)->count() : 0,
            ];
        } catch (Throwable) {
            return null;
        }
    }
}
