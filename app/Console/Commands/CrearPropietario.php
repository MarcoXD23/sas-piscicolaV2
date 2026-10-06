<?php

namespace App\Console\Commands;

use App\Models\Rol;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Region;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Crea una finca (tenant) con su región y su propietario inicial. La contraseña se pide por consola.
 */
class CrearPropietario extends Command
{
    protected $signature = 'piscicola:crear-propietario';

    public function __construct()
    {
        parent::__construct();
        $this->description = __('comandos.crear_propietario.descripcion');
    }

    public function handle(): int
    {
        $finca = (string) $this->ask(__('comandos.crear_propietario.finca'));
        $codigo = (string) $this->ask(__('comandos.crear_propietario.codigo'), Str::slug($finca));
        $pais = Str::upper((string) $this->ask(__('comandos.crear_propietario.pais')));
        // Sugiere la primera zona horaria del país (o la zona por defecto si el código no es válido)
        $zonaSugerida = Region::paisValido($pais) ? (Region::zonasDelPais($pais)[0] ?? Region::ZONA_POR_DEFECTO) : Region::ZONA_POR_DEFECTO;
        $zona = (string) $this->ask(__('comandos.crear_propietario.zona'), $zonaSugerida);
        $nombre = (string) $this->ask(__('comandos.crear_propietario.nombre'));
        $correo = (string) $this->ask(__('comandos.crear_propietario.correo'));
        $clave = (string) $this->secret(__('comandos.crear_propietario.clave'));
        $confirmacion = (string) $this->secret(__('comandos.crear_propietario.confirmacion'));

        $validador = Validator::make([
            'finca' => $finca,
            'codigo' => $codigo,
            'pais' => $pais,
            'zona_horaria' => $zona,
            'nombre' => $nombre,
            'correo' => $correo,
            'clave' => $clave,
            'clave_confirmation' => $confirmacion,
        ], [
            'finca' => ['required', 'string', 'max:255'],
            'codigo' => ['required', 'alpha_dash', 'max:60', 'unique:tenants,codigo'],
            'pais' => ['required', function ($atributo, $valor, $falla) {
                if (! Region::paisValido($valor)) {
                    $falla(__('comandos.crear_propietario.pais_invalido'));
                }
            }],
            'zona_horaria' => ['required', Rule::in(Region::zonasValidas())],
            'nombre' => ['required', 'string', 'max:255'],
            'correo' => ['required', 'email', 'max:255', 'unique:users,email'],
            'clave' => ['required', 'confirmed', Password::min(8)],
        ], [
            'zona_horaria.in' => __('comandos.crear_propietario.zona_invalida'),
        ]);

        if ($validador->fails()) {
            foreach ($validador->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        DB::transaction(function () use ($finca, $codigo, $pais, $zona, $nombre, $correo, $clave): void {
            $tenant = Tenant::create([
                'nombre' => $finca,
                'codigo' => $codigo,
                'pais' => $pais,
                'zona_horaria' => $zona,
                'idioma' => Region::IDIOMA_POR_DEFECTO,
            ]);

            $usuario = new User(['name' => $nombre, 'email' => $correo, 'password' => $clave]);
            $usuario->tenant_id = $tenant->id;
            $usuario->aprobado_at = now();
            $usuario->save();

            $usuario->asignarRoles([Rol::PROPIETARIO]);
        });

        $this->info(__('comandos.crear_propietario.creado', ['finca' => $finca, 'codigo' => $codigo, 'pais' => $pais, 'zona' => $zona]));

        return self::SUCCESS;
    }
}
