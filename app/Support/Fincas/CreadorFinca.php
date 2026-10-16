<?php

namespace App\Support\Fincas;

use App\Models\AceptacionLegal;
use App\Models\Rol;
use App\Models\Tenant;
use App\Models\User;
use App\Rules\NoIgualAlCorreo;
use App\Support\Region;
use App\Support\TenantActual;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Crea una finca con su propietario: la usan el comando piscicola:crear-propietario y las invitaciones de la plataforma,
 * para que las dos creen la finca igual. Al crearse, la finca arranca con sus especies, fases y tabla de ración
 * (Tenant::booted).
 */
final class CreadorFinca
{
    /**
     * Reglas de los datos (finca, codigo, pais, zona_horaria, nombre, correo, clave con clave_confirmation).
     *
     * @return array<string, array<int, mixed>>
     */
    public static function reglas(string $correo): array
    {
        return [
            'finca' => ['required', 'string', 'max:255'],
            'codigo' => ['required', 'alpha_dash', 'max:60', 'unique:tenants,codigo'],
            'pais' => ['required', function ($atributo, $valor, $falla) {
                if (! Region::paisValido((string) $valor)) {
                    $falla(__('comandos.crear_propietario.pais_invalido'));
                }
            }],
            'zona_horaria' => ['required', Rule::in(Region::zonasValidas())],
            'nombre' => ['required', 'string', 'max:255'],
            // El correo es único en todo el sistema, sin importar mayúsculas (se revisa en todas las fincas)
            'correo' => ['required', 'email', 'max:255', function ($atributo, $valor, $falla) {
                if (self::correoUsado((string) $valor)) {
                    $falla(__('validation.unique', ['attribute' => $atributo]));
                }
            }],
            'clave' => ['required', 'confirmed', Password::min(8), new NoIgualAlCorreo($correo)],
        ];
    }

    public static function correoUsado(string $correo): bool
    {
        return TenantActual::sinFiltro(fn () => User::query()->whereRaw('LOWER(email) = ?', [mb_strtolower(trim($correo))])->exists());
    }

    /**
     * Crea la finca y su propietario (aprobado, con el rol de propietario) en una transacción.
     * Con $aceptoTerminos queda la aceptación de los términos (la invitación la pide; el comando no, y el propietario
     * los acepta al entrar por primera vez).
     *
     * @param  array{finca: string, codigo: string, pais: string, zona_horaria: string, nombre: string, correo: string, clave: string}  $datos
     */
    public static function crear(array $datos, bool $aceptoTerminos = false): User
    {
        return DB::transaction(function () use ($datos, $aceptoTerminos) {
            $finca = Tenant::create([
                'nombre' => $datos['finca'],
                'codigo' => $datos['codigo'],
                'pais' => $datos['pais'],
                'zona_horaria' => $datos['zona_horaria'],
                'idioma' => Region::IDIOMA_POR_DEFECTO,
            ]);

            $propietario = new User(['name' => $datos['nombre'], 'email' => $datos['correo'], 'password' => $datos['clave']]);
            $propietario->tenant_id = $finca->id;
            $propietario->aprobado_at = now();
            $propietario->save();
            $propietario->asignarRoles([Rol::PROPIETARIO]);

            if ($aceptoTerminos) {
                AceptacionLegal::registrar($propietario);
            }

            return $propietario;
        });
    }
}
