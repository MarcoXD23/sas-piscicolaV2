<?php

namespace App\Console\Commands;

use App\Models\AdministradorPlataforma;
use App\Notifications\AvisoSalud;
use App\Support\Salud\Comprobaciones;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Corre las comprobaciones del sistema (cada 5 minutos) y avisa por correo al administrador de plataforma
 * cuando algo empieza a fallar y cuando se recupera. No repite el aviso mientras siga fallando.
 * Si el programador mismo está caído, este aviso no puede salir: para eso se vigila /salud desde afuera.
 */
class VigilarSalud extends Command
{
    protected $signature = 'piscicola:vigilar';

    public function __construct()
    {
        parent::__construct();
        $this->description = __('comandos.vigilar.descripcion');
    }

    public function handle(): int
    {
        $resultados = Comprobaciones::correr();
        $fallas = array_keys(array_filter($resultados, fn (bool $bien) => ! $bien));
        $anterior = $this->estadoAnterior();

        if ($fallas !== [] && $anterior['fallas'] !== $fallas) {
            // Empezó a fallar (o falla algo distinto): un aviso
            $this->avisar(new AvisoSalud($fallas));
            Log::error('Estado del sistema: falla', ['comprobaciones' => $fallas]);
        } elseif ($fallas === [] && $anterior['fallas'] !== []) {
            $this->avisar(new AvisoSalud([], recuperado: true));
            Log::info('Estado del sistema: recuperado');
        }

        $this->guardarEstado($fallas);
        // Cada comprobación con su umbral (config/salud.php)
        foreach ($resultados as $nombre => $bien) {
            $this->line(($bien ? __('salud.bien') : __('salud.falla')).' · '.Comprobaciones::etiqueta($nombre));
        }
        $this->line($fallas === [] ? __('salud.todo_bien') : __('salud.fallan', ['comprobaciones' => implode(', ', array_map(fn ($f) => Comprobaciones::etiqueta($f), $fallas))]));

        return $fallas === [] ? self::SUCCESS : self::FAILURE;
    }

    /** Destino principal: SALUD_CORREO_AVISO; si está vacía, los administradores de plataforma. */
    private function avisar(AvisoSalud $aviso): void
    {
        $destinos = config('salud.correo_aviso')
            ? [config('salud.correo_aviso')]
            : AdministradorPlataforma::query()->pluck('email')->all();

        if ($destinos !== []) {
            Notification::route('mail', $destinos)->notify($aviso);
        }
    }

    /** @return array{fallas: list<string>} */
    private function estadoAnterior(): array
    {
        try {
            $disco = Storage::disk(config('salud.disco'));
            $ruta = config('salud.carpeta').'/estado.json';

            return $disco->exists($ruta) ? (json_decode($disco->get($ruta), true) ?: ['fallas' => []]) : ['fallas' => []];
        } catch (Throwable) {
            // Sin almacenamiento no se sabe el estado anterior: se avisa igual (la falla de almacenamiento es real)
            return ['fallas' => []];
        }
    }

    /** @param  list<string>  $fallas */
    private function guardarEstado(array $fallas): void
    {
        try {
            Storage::disk(config('salud.disco'))->put(config('salud.carpeta').'/estado.json', json_encode(['fallas' => $fallas, 'revisado_at' => now()->utc()->toIso8601String()]));
        } catch (Throwable) {
            // Sin almacenamiento no se puede guardar: la próxima vigilancia volverá a avisar
        }
    }
}
