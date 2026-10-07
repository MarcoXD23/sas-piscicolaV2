<?php

namespace App\Console\Commands\Concerns;

/**
 * Datos para llamar a pg_dump y pg_restore con la conexión de la app.
 * La contraseña viaja en la variable de entorno PGPASSWORD del proceso: nunca en la línea de comando
 * (que cualquiera ve en la lista de procesos) ni en los logs.
 */
trait UsaHerramientasPostgres
{
    protected function binario(string $nombre): string
    {
        $carpeta = config('respaldos.pg_bin');

        return filled($carpeta) ? rtrim((string) $carpeta, '\\/').DIRECTORY_SEPARATOR.$nombre : $nombre;
    }

    /** Host, puerto y usuario como argumentos; nunca la contraseña. */
    protected function argumentosConexion(): array
    {
        $conexion = config('database.connections.pgsql');

        return [
            '--host='.$conexion['host'],
            '--port='.$conexion['port'],
            '--username='.$conexion['username'],
            '--no-password',
        ];
    }

    protected function entornoPostgres(): array
    {
        return ['PGPASSWORD' => (string) config('database.connections.pgsql.password')];
    }

    protected function baseActual(): string
    {
        return (string) config('database.connections.pgsql.database');
    }

    /** Ruta absoluta y normalizada (separadores "/", sin "/" final) para comparar carpetas. */
    protected function normalizar(string $ruta): string
    {
        $real = realpath($ruta) ?: $ruta;
        $ruta = rtrim(str_replace('\\', '/', $real), '/');

        return DIRECTORY_SEPARATOR === '\\' ? mb_strtolower($ruta) : $ruta;
    }
}
