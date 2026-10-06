<?php

namespace App\Console\Commands;

use App\Models\Rol;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

/**
 * Crea una finca (tenant) y su propietario inicial. La contraseña se pide por consola.
 */
class CrearPropietario extends Command
{
    protected $signature = 'piscicola:crear-propietario';

    protected $description = 'Crea una finca y su usuario propietario (pide la contraseña)';

    public function handle(): int
    {
        $finca = (string) $this->ask('Nombre de la finca');
        $codigo = (string) $this->ask('Código de la finca (lo usará el personal para registrarse)', Str::slug($finca));
        $nombre = (string) $this->ask('Nombre del propietario');
        $correo = (string) $this->ask('Correo del propietario');
        $clave = (string) $this->secret('Contraseña (mínimo 8 caracteres)');
        $confirmacion = (string) $this->secret('Confirma la contraseña');

        $validador = Validator::make([
            'finca' => $finca,
            'codigo' => $codigo,
            'nombre' => $nombre,
            'correo' => $correo,
            'clave' => $clave,
            'clave_confirmation' => $confirmacion,
        ], [
            'finca' => ['required', 'string', 'max:255'],
            'codigo' => ['required', 'alpha_dash', 'max:60', 'unique:tenants,codigo'],
            'nombre' => ['required', 'string', 'max:255'],
            'correo' => ['required', 'email', 'max:255', 'unique:users,email'],
            'clave' => ['required', 'confirmed', Password::min(8)],
        ]);

        if ($validador->fails()) {
            foreach ($validador->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        DB::transaction(function () use ($finca, $codigo, $nombre, $correo, $clave): void {
            $tenant = Tenant::create(['nombre' => $finca, 'codigo' => $codigo]);

            $usuario = new User(['name' => $nombre, 'email' => $correo, 'password' => $clave]);
            $usuario->tenant_id = $tenant->id;
            $usuario->aprobado_at = now();
            $usuario->save();

            $usuario->asignarRoles([Rol::PROPIETARIO]);
        });

        $this->info("Propietario creado para la finca «{$finca}» (código: {$codigo}).");

        return self::SUCCESS;
    }
}

