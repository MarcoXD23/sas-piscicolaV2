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
    /** Alfabeto del código de la finca: mayúsculas y números sin los que se confunden (0, O, 1, I, L). */
    public const ALFABETO_CODIGO = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    public const LARGO_CODIGO = 6;

    /**
     * Reglas de los datos (finca, pais, zona_horaria, nombre, correo, clave con clave_confirmation). El código de la
     * finca no se pide: lo genera la app.
     *
     * @return array<string, array<int, mixed>>
     */
    public static function reglas(string $correo): array
    {
        return [
            'finca' => ['required', 'string', 'max:255'],
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
            'clave' => ['required', 'confirmed', Password::defaults(), new NoIgualAlCorreo($correo)],
        ];
    }

    public static function correoUsado(string $correo): bool
    {
        return TenantActual::sinFiltro(fn () => User::query()->whereRaw('LOWER(email) = ?', [mb_strtolower(trim($correo))])->exists());
    }

    /**
     * Código nuevo de finca: 6 caracteres del alfabeto sin confusiones, que ninguna finca tenga (sin importar
     * mayúsculas, porque así se busca). Si choca, se genera otro.
     */
    public static function generarCodigo(): string
    {
        do {
            $codigo = '';
            for ($i = 0; $i < self::LARGO_CODIGO; $i++) {
                $codigo .= self::ALFABETO_CODIGO[random_int(0, strlen(self::ALFABETO_CODIGO) - 1)];
            }
        } while (Tenant::query()->whereRaw('LOWER(codigo) = ?', [mb_strtolower($codigo)])->exists());

        return $codigo;
    }

    /**
     * Crea la finca (con su código generado) y su propietario (aprobado, con el rol de propietario) en una transacción.
     * Con $aceptoTerminos queda la aceptación de los términos (la invitación la pide; el comando no, y el propietario
     * los acepta al entrar por primera vez). La finca queda en $propietario->tenant.
     *
     * @param  array{finca: string, pais: string, zona_horaria: string, nombre: string, correo: string, clave: string}  $datos
     */
    public static function crear(array $datos, bool $aceptoTerminos = false): User
    {
        return DB::transaction(function () use ($datos, $aceptoTerminos) {
            $finca = Tenant::create([
                'nombre' => $datos['finca'],
                'codigo' => self::generarCodigo(),
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

            return $propietario->setRelation('tenant', $finca);
        });
    }
}
