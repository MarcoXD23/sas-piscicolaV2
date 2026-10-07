<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Support\Alertas\EvaluadorAlertas;
use Illuminate\Console\Command;

/**
 * Evalúa las alertas de todas las fincas activas (se programa cada hora en routes/console.php).
 * Cubre lo que cambia con el paso del tiempo: el consumo promedio de bodega, el período de mortalidad
 * y la vigencia de la última medición de agua.
 */
class EvaluarAlertas extends Command
{
    protected $signature = 'piscicola:evaluar-alertas';

    public function __construct()
    {
        parent::__construct();
        $this->description = __('comandos.evaluar_alertas.descripcion');
    }

    public function handle(): int
    {
        $fincas = Tenant::query()->whereNull('suspendida_at')->pluck('id');

        foreach ($fincas as $id) {
            EvaluadorAlertas::paraFinca($id);
        }

        $this->info(__('comandos.evaluar_alertas.listo', ['fincas' => $fincas->count()]));

        return self::SUCCESS;
    }
}
