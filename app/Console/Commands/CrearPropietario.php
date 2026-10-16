<?php

namespace App\Console\Commands;

use App\Support\Fincas\CreadorFinca;
use App\Support\Region;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * Crea una finca (tenant) con su región y su propietario inicial. La contraseña se pide por consola.
 * La creación es la misma de las invitaciones de la plataforma (CreadorFinca).
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
        $pais = Str::upper((string) $this->ask(__('comandos.crear_propietario.pais')));
        // Sugiere la primera zona horaria del país (o la zona por defecto si el código no es válido)
        $zonaSugerida = Region::paisValido($pais) ? (Region::zonasDelPais($pais)[0] ?? Region::ZONA_POR_DEFECTO) : Region::ZONA_POR_DEFECTO;
        $zona = (string) $this->ask(__('comandos.crear_propietario.zona'), $zonaSugerida);
        $nombre = (string) $this->ask(__('comandos.crear_propietario.nombre'));
        $correo = (string) $this->ask(__('comandos.crear_propietario.correo'));
        $clave = (string) $this->secret(__('comandos.crear_propietario.clave'));
        $confirmacion = (string) $this->secret(__('comandos.crear_propietario.confirmacion'));

        $datos = [
            'finca' => $finca,
            'pais' => $pais,
            'zona_horaria' => $zona,
            'nombre' => $nombre,
            'correo' => $correo,
            'clave' => $clave,
        ];
        $validador = Validator::make($datos + ['clave_confirmation' => $confirmacion], CreadorFinca::reglas($correo), [
            'zona_horaria.in' => __('comandos.crear_propietario.zona_invalida'),
        ]);

        if ($validador->fails()) {
            foreach ($validador->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $propietario = CreadorFinca::crear($datos);

        $this->info(__('comandos.crear_propietario.creado', ['finca' => $finca, 'pais' => $pais, 'zona' => $zona]));
        // El código lo genera la app (CreadorFinca::generarCodigo)
        $this->info(__('comandos.crear_propietario.codigo_generado', ['codigo' => $propietario->tenant->codigo]));

        return self::SUCCESS;
    }
}
