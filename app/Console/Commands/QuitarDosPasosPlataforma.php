<?php

namespace App\Console\Commands;

use App\Models\AdministradorPlataforma;
use App\Support\DosPasos;
use Illuminate\Console\Command;

/**
 * Para el administrador de plataforma que perdió su teléfono y sus códigos de recuperación: le quita la verificación
 * en dos pasos desde el servidor (tener acceso al servidor prueba que es el dueño). Al volver a entrar al panel
 * se le exige activarla de nuevo (PlataformaConDosPasos).
 */
class QuitarDosPasosPlataforma extends Command
{
    protected $signature = 'piscicola:plataforma-quitar-dos-pasos {correo : Correo del administrador de plataforma}';

    public function __construct()
    {
        parent::__construct();
        $this->description = __('comandos.plataforma_quitar_dos_pasos.descripcion');
    }

    public function handle(): int
    {
        $correo = mb_strtolower(trim((string) $this->argument('correo')));
        $administrador = AdministradorPlataforma::query()->whereRaw('LOWER(email) = ?', [$correo])->first();

        if ($administrador === null) {
            $this->error(__('comandos.plataforma_quitar_dos_pasos.no_existe', ['correo' => $correo]));

            return self::FAILURE;
        }

        if (! $administrador->tieneDosPasos()) {
            $this->info(__('comandos.plataforma_quitar_dos_pasos.no_tenia', ['correo' => $correo]));

            return self::SUCCESS;
        }

        DosPasos::desactivar($administrador);
        $this->info(__('comandos.plataforma_quitar_dos_pasos.listo', ['correo' => $correo]));

        return self::SUCCESS;
    }
}
