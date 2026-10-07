<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\UsaHerramientasPostgres;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

/**
 * Restaura una copia de piscicola:respaldar en una base NUEVA (o vacía). Nunca escribe sobre la base que usa la app:
 * para volver a una copia se restaura en otra base, se revisa y se cambia DB_DATABASE en el .env.
 */
class Restaurar extends Command
{
    use UsaHerramientasPostgres;

    protected $signature = 'piscicola:restaurar
        {archivo : Copia (.dump) creada con piscicola:respaldar}
        {--base= : Base de destino: nueva o vacía, distinta de la que usa la app}';

    public function __construct()
    {
        parent::__construct();
        $this->description = __('comandos.restaurar.descripcion');
    }

    public function handle(): int
    {
        $archivo = (string) $this->argument('archivo');
        $base = (string) $this->option('base');

        if (! is_file($archivo) || ! is_readable($archivo)) {
            $this->error(__('comandos.restaurar.sin_archivo', ['archivo' => $archivo]));

            return self::FAILURE;
        }

        // Solo minúsculas, números y guion bajo: el nombre se escribe tal cual en CREATE DATABASE
        if (! preg_match('/^[a-z_][a-z0-9_]{0,62}$/', $base)) {
            $this->error(__('comandos.restaurar.base_invalida'));

            return self::FAILURE;
        }

        if ($base === $this->baseActual()) {
            $this->error(__('comandos.restaurar.base_de_la_app', ['base' => $base]));

            return self::FAILURE;
        }

        $existe = DB::selectOne('SELECT 1 AS si FROM pg_database WHERE datname = ?', [$base]) !== null;
        if ($existe && $this->tieneTablas($base)) {
            $this->error(__('comandos.restaurar.base_con_datos', ['base' => $base]));

            return self::FAILURE;
        }

        if (! $existe) {
            DB::statement('CREATE DATABASE "'.$base.'"');
        }

        // --no-owner: las tablas quedan del rol que restaura (el dueño en producción); los permisos de la app se conservan
        $restauracion = Process::env($this->entornoPostgres())->timeout(3600)->run([
            $this->binario('pg_restore'),
            ...$this->argumentosConexion(),
            '--dbname='.$base,
            '--no-owner',
            '--exit-on-error',
            '--single-transaction',
            $archivo,
        ]);

        if ($restauracion->failed()) {
            // Una restauración a medias no sirve: si la base la creó este comando, se borra
            if (! $existe) {
                DB::statement('DROP DATABASE IF EXISTS "'.$base.'"');
            }
            Log::error('Restauración: pg_restore falló', ['base' => $base, 'salida' => $restauracion->errorOutput()]);
            $this->error(__('comandos.restaurar.fallo', ['detalle' => trim($restauracion->errorOutput())]));

            return self::FAILURE;
        }

        Log::info('Copia restaurada', ['base' => $base, 'archivo' => basename($archivo)]);
        $this->info(__('comandos.restaurar.listo', ['base' => $base]));

        return self::SUCCESS;
    }

    /** Revisa la base de destino con una conexión aparte, que se cierra al terminar. */
    private function tieneTablas(string $base): bool
    {
        config(['database.connections.restauracion' => ['database' => $base] + config('database.connections.pgsql')]);

        try {
            return (int) DB::connection('restauracion')
                ->selectOne("SELECT count(*) AS total FROM pg_tables WHERE schemaname = 'public'")->total > 0;
        } finally {
            DB::purge('restauracion');
        }
    }
}
