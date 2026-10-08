<?php

namespace App\Jobs;

use App\Models\Exportacion;
use App\Support\Datos\ExportadorFinca;
use App\Support\TenantActual;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Genera en segundo plano el paquete de datos de una finca.
 * Lleva su tenant_id y fija la finca al empezar (el filtro por finca falla cerrado: ver AGENTS.md).
 */
class GenerarExportacion implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(public readonly int $exportacionId, public readonly int $tenantId) {}

    public function handle(TenantActual $tenantActual): void
    {
        $tenantActual->establecer($this->tenantId);
        $exportacion = Exportacion::query()->findOrFail($this->exportacionId);

        try {
            ExportadorFinca::generar($exportacion);
        } catch (Throwable $error) {
            $exportacion->forceFill(['estado' => Exportacion::FALLO])->save();
            Log::error('Exportación de finca fallida', ['tenant_id' => $this->tenantId, 'exportacion' => $this->exportacionId, 'error' => $error->getMessage()]);
        }
    }
}
