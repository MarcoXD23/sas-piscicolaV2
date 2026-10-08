<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Support\DosPasos;
use App\Support\TenantActual;
use Illuminate\Console\Command;

/**
 * Para quien perdió su teléfono y sus códigos de recuperación y no tiene a quién pedírselo (por ejemplo, el propietario):
 * le quita la verificación en dos pasos desde el servidor. Después la vuelve a activar en Mi cuenta.
 */
class QuitarDosPasos extends Command
{
    protected $signature = 'piscicola:quitar-dos-pasos {correo : Correo de la persona}';

    public function __construct()
    {
        parent::__construct();
        $this->description = __('comandos.quitar_dos_pasos.descripcion');
    }

    public function handle(): int
    {
        $correo = mb_strtolower(trim((string) $this->argument('correo')));
        // Búsqueda de identidad: el correo es único en todo el sistema
        $usuario = TenantActual::sinFiltro(fn () => User::query()->whereRaw('LOWER(email) = ?', [$correo])->first());

        if ($usuario === null) {
            $this->error(__('comandos.quitar_dos_pasos.no_existe', ['correo' => $correo]));

            return self::FAILURE;
        }

        if (! $usuario->tieneDosPasos()) {
            $this->info(__('comandos.quitar_dos_pasos.no_tenia', ['correo' => $correo]));

            return self::SUCCESS;
        }

        DosPasos::desactivar($usuario);
        $this->info(__('comandos.quitar_dos_pasos.listo', ['correo' => $correo]));

        return self::SUCCESS;
    }
}
