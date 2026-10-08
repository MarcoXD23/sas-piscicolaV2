<?php

namespace App\Console\Commands;

use App\Console\Commands\Respaldar as ComandoRespaldar;
use App\Support\Formato;
use App\Support\Salud\Comprobaciones;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Detalle del estado del sistema, para quien administra el servidor (lo que /salud no muestra):
 * base de datos, cola y cola de fallos, espacio libre, última copia de seguridad y último ciclo del programador.
 */
class Estado extends Command
{
    protected $signature = 'piscicola:estado';

    public function __construct()
    {
        parent::__construct();
        $this->description = __('comandos.estado.descripcion');
    }

    public function handle(): int
    {
        $resultados = Comprobaciones::correr();
        $marca = fn (bool $bien) => $bien ? __('salud.bien') : __('salud.falla');

        $this->table([__('salud.columna_dato'), __('salud.columna_valor')], [
            [__('salud.comprobaciones.base'), $marca($resultados['base']).' · '.$this->versionBase()],
            [__('salud.comprobaciones.almacenamiento'), $marca($resultados['almacenamiento'])],
            [__('salud.comprobaciones.programador'), $marca($resultados['programador']).' · '.$this->ultimoCiclo()],
            [__('salud.cola'), $this->contar('jobs')],
            [__('salud.cola_fallos'), $this->contar('failed_jobs')],
            [__('salud.disco_libre'), $this->discoLibre()],
            [__('salud.ultima_copia'), $this->ultimaCopia()],
        ]);

        $this->line(Comprobaciones::todoBien($resultados) ? __('salud.todo_bien') : __('salud.algo_falla'));

        return Comprobaciones::todoBien($resultados) ? self::SUCCESS : self::FAILURE;
    }

    private function versionBase(): string
    {
        try {
            return (string) DB::selectOne('SHOW server_version')->server_version;
        } catch (Throwable) {
            return __('salud.sin_dato');
        }
    }

    private function contar(string $tabla): string
    {
        try {
            return Schema::hasTable($tabla) ? Formato::numero(DB::table($tabla)->count()) : __('salud.sin_dato');
        } catch (Throwable) {
            return __('salud.sin_dato');
        }
    }

    private function discoLibre(): string
    {
        $libre = @disk_free_space(storage_path());

        return $libre === false ? __('salud.sin_dato') : __('salud.gb', ['gb' => Formato::numero($libre / 1024 ** 3, 1)]);
    }

    private function ultimoCiclo(): string
    {
        $ultimo = Comprobaciones::ultimoLatido();

        return $ultimo ? $ultimo->setTimezone(config('app.timezone'))->format('Y-m-d H:i').' UTC' : __('salud.nunca');
    }

    private function ultimaCopia(): string
    {
        $carpeta = config('respaldos.carpeta');
        if (blank($carpeta)) {
            return __('salud.sin_carpeta_respaldos');
        }

        $copias = glob(rtrim($carpeta, '\\/').DIRECTORY_SEPARATOR.ComandoRespaldar::PREFIJO.'*.dump') ?: [];
        if ($copias === []) {
            return __('salud.sin_copias');
        }
        usort($copias, fn ($a, $b) => filemtime($b) <=> filemtime($a));

        return __('salud.copia', [
            'archivo' => basename($copias[0]),
            'fecha' => date('Y-m-d H:i', filemtime($copias[0])),
            'mb' => Formato::numero(filesize($copias[0]) / 1048576, 2),
        ]);
    }
}
