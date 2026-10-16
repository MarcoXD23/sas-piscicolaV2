<?php

namespace App\Console\Commands;

use App\Models\AdministradorPlataforma;
use App\Rules\NoIgualAlCorreo;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

/**
 * Crea el acceso al panel de plataforma (dueño del software). La contraseña se pide por consola.
 */
class CrearAdminPlataforma extends Command
{
    protected $signature = 'piscicola:crear-admin-plataforma';

    public function __construct()
    {
        parent::__construct();
        $this->description = __('comandos.crear_admin_plataforma.descripcion');
    }

    public function handle(): int
    {
        $datos = [
            'nombre' => (string) $this->ask(__('comandos.crear_admin_plataforma.nombre')),
            'correo' => mb_strtolower((string) $this->ask(__('comandos.crear_admin_plataforma.correo'))),
            'clave' => (string) $this->secret(__('comandos.crear_admin_plataforma.clave')),
            'clave_confirmation' => (string) $this->secret(__('comandos.crear_admin_plataforma.confirmacion')),
        ];

        $validador = Validator::make($datos, [
            'nombre' => ['required', 'string', 'max:255'],
            'correo' => ['required', 'email', 'max:255', 'unique:administradores_plataforma,email'],
            'clave' => ['required', 'confirmed', Password::min(12)->mixedCase()->numbers()->symbols(), new NoIgualAlCorreo($datos['correo'])],
        ]);

        if ($validador->fails()) {
            foreach ($validador->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        AdministradorPlataforma::create(['name' => $datos['nombre'], 'email' => $datos['correo'], 'password' => $datos['clave']]);

        $this->info(__('comandos.crear_admin_plataforma.creado', ['correo' => $datos['correo'], 'url' => route('plataforma.login')]));

        return self::SUCCESS;
    }
}
