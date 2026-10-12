<?php

namespace App\Console\Commands;

use App\Support\Formato;
use App\Support\Salud\Comprobaciones;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Detalle del estado del sistema, para quien administra el servidor (lo que /salud no muestra): cada comprobación
 * con su umbral y lo medido (versión de la base, último ciclo del programador, última copia, espacio libre y cola).
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
        $detalle = [
            'base' => $this->versionBase(),
            'almacenamiento' => null,
            'programador' => $this->ultimoCiclo(),
            'copia' => $this->ultimaCopia(),
            'disco' => $this->discoLibre(),
            'cola' => $this->cola(),
        ];

        $this->table([__('salud.columna_dato'), __('salud.columna_valor')], array_map(
            fn (string $nombre) => [Comprobaciones::etiqueta($nombre), $marca($resultados[$nombre]).($detalle[$nombre] !== null ? ' · '.$detalle[$nombre] : '')],
            Comprobaciones::NOMBRES,
        ));

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

    private function ultimoCiclo(): string
    {
        $ultimo = Comprobaciones::ultimoLatido();

        return $ultimo ? $ultimo->setTimezone(config('app.timezone'))->format('Y-m-d H:i').' UTC' : __('salud.nunca');
    }

    private function ultimaCopia(): string
    {
        if (blank(config('respaldos.carpeta'))) {
            return config('salud.exigir_copia') ? __('salud.sin_carpeta_respaldos') : __('salud.sin_carpeta_no_aplica');
        }
        $copia = Comprobaciones::ultimaCopia();
        if ($copia === null) {
            return __('salud.sin_copias');
        }

        return __('salud.copia', [
            'archivo' => $copia['archivo'],
            'fecha' => $copia['fecha']->setTimezone(config('app.timezone'))->format('Y-m-d H:i'),
            'horas' => Formato::numero((int) $copia['fecha']->diffInHours(now(), true)),
            'mb' => Formato::numero($copia['bytes'] / 1048576, 2),
        ]);
    }

    private function discoLibre(): string
    {
        $espacio = Comprobaciones::espacioDisco();
        if ($espacio === null || $espacio['total'] <= 0) {
            return __('salud.sin_dato');
        }

        return __('salud.disco', [
            'gb' => Formato::numero($espacio['libre'] / 1024 ** 3, 1),
            'porcentaje' => Formato::numero($espacio['libre'] / $espacio['total'] * 100, 1),
        ]);
    }

    private function cola(): string
    {
        $cola = Comprobaciones::estadoCola();

        return $cola === null ? __('salud.sin_dato') : __('salud.detalle_cola', [
            'pendientes' => Formato::numero($cola['pendientes']),
            'atascados' => Formato::numero($cola['atascados']),
            'fallidos' => Formato::numero($cola['fallidos']),
        ]);
    }
}
