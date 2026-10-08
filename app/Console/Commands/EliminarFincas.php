<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Support\Datos\EliminadorFinca;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Borra definitivamente las fincas cuyo periodo de gracia venció (se programa a diario).
 * También vuelve a borrar las fincas ya eliminadas que reaparezcan al restaurar una copia de seguridad vieja
 * (la lista está fuera de la base: storage/app/private/fincas-eliminadas.json).
 */
class EliminarFincas extends Command
{
    protected $signature = 'piscicola:eliminar-fincas';

    public function __construct()
    {
        parent::__construct();
        $this->description = __('comandos.eliminar_fincas.descripcion');
    }

    public function handle(): int
    {
        // Reaparecidas tras restaurar una copia: se programan como vencidas y se borran de nuevo
        foreach (Tenant::query()->whereIn('id', EliminadorFinca::eliminadas())->get() as $reaparecida) {
            // Con la hora UTC de la base: es la que revisa la función borrar_bitacora_finca()
            DB::table('tenants')->where('id', $reaparecida->id)->update([
                'eliminacion_programada_at' => DB::raw("(now() AT TIME ZONE 'UTC') - interval '1 minute'"),
                'suspendida_at' => $reaparecida->suspendida_at ?? now(),
            ]);
            $this->warn(__('comandos.eliminar_fincas.reaparecida', ['id' => $reaparecida->id]));
        }

        $vencidas = Tenant::query()->whereNotNull('eliminacion_programada_at')->where('eliminacion_programada_at', '<=', now())->get();
        $fallas = 0;
        foreach ($vencidas as $finca) {
            try {
                $filas = array_sum(EliminadorFinca::eliminar($finca));
                $this->info(__('comandos.eliminar_fincas.eliminada', ['id' => $finca->id, 'filas' => $filas]));
            } catch (Throwable $error) {
                $fallas++;
                report($error);
                $this->error(__('comandos.eliminar_fincas.fallo', ['id' => $finca->id, 'error' => $error->getMessage()]));
            }
        }

        if ($vencidas->isEmpty()) {
            $this->info(__('comandos.eliminar_fincas.ninguna'));
        }

        return $fallas === 0 ? self::SUCCESS : self::FAILURE;
    }
}
