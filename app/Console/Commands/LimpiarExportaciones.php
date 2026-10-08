<?php

namespace App\Console\Commands;

use App\Models\Exportacion;
use App\Support\TenantActual;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Borra los paquetes de exportación vencidos (24 horas) y los que fallaron hace más de un día (se programa cada hora).
 */
class LimpiarExportaciones extends Command
{
    protected $signature = 'piscicola:limpiar-exportaciones';

    public function __construct()
    {
        parent::__construct();
        $this->description = __('comandos.limpiar_exportaciones.descripcion');
    }

    public function handle(): int
    {
        // Todas las fincas a propósito: es mantenimiento del servidor
        $vencidas = TenantActual::sinFiltro(fn () => Exportacion::query()
            ->where(fn ($q) => $q->where('vence_at', '<=', now())->orWhere(fn ($q) => $q->where('estado', '!=', Exportacion::LISTA)->where('created_at', '<=', now()->subDay())))
            ->get());

        foreach ($vencidas as $exportacion) {
            if ($exportacion->ruta) {
                Storage::disk('exportaciones')->delete($exportacion->ruta);
            }
            $exportacion->delete();
        }

        $this->info(__('comandos.limpiar_exportaciones.listo', ['cantidad' => $vencidas->count()]));

        return self::SUCCESS;
    }
}
