<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Verificación en dos pasos de una persona: activar, verificar un código (de la app o de recuperación) y desactivar.
 * - La clave se guarda cifrada (cast «encrypted» del modelo); los 10 códigos de recuperación, solo como hashes.
 * - Un código de la app no se acepta dos veces (último paso usado) y uno de recuperación se borra al usarlo.
 * - La verificación bloquea la fila de la persona: dos intentos simultáneos con el mismo código no pasan los dos.
 */
final class DosPasos
{
    public const CODIGOS_RECUPERACION = 10;

    /**
     * Activa con la clave que la persona agregó a su app, si el código es válido.
     *
     * @return list<string>|null Códigos de recuperación en texto (se muestran una sola vez); null si el código no es válido
     */
    public static function activar(User $usuario, string $clave, string $codigo): ?array
    {
        $paso = Totp::verificar($clave, $codigo);
        if ($paso === null) {
            return null;
        }

        $codigos = self::nuevosCodigos();
        $usuario->forceFill([
            'dos_pasos_clave' => $clave,
            'dos_pasos_activado_at' => now(),
            'dos_pasos_ultimo_paso' => $paso,
            'dos_pasos_recuperacion' => array_map(fn (string $c) => Hash::make($c), $codigos),
        ])->save();

        return $codigos;
    }

    public static function desactivar(User $usuario): void
    {
        $usuario->forceFill([
            'dos_pasos_clave' => null, 'dos_pasos_activado_at' => null, 'dos_pasos_ultimo_paso' => null, 'dos_pasos_recuperacion' => null,
        ])->save();
    }

    /** ¿El código (de la app o de recuperación) es válido? Lo marca como usado. */
    public static function verificar(User $usuario, string $codigo): bool
    {
        return DB::transaction(function () use ($usuario, $codigo) {
            // Fila bloqueada y leída sin el filtro de finca: en el login todavía no hay finca activa
            $fila = $usuario->newQueryWithoutScopes()->whereKey($usuario->getKey())->lockForUpdate()->first();
            if ($fila === null || ! $fila->tieneDosPasos()) {
                return false;
            }

            $paso = Totp::verificar($fila->dos_pasos_clave, $codigo, $fila->dos_pasos_ultimo_paso);
            if ($paso !== null) {
                $fila->forceFill(['dos_pasos_ultimo_paso' => $paso])->save();

                return true;
            }

            // Código de recuperación: un solo uso
            $normalizado = strtolower(preg_replace('/[^A-Za-z0-9]/', '', $codigo));
            $hashes = $fila->dos_pasos_recuperacion ?? [];
            foreach ($hashes as $i => $hash) {
                if (strlen($normalizado) === 10 && Hash::check(self::formato($normalizado), $hash)) {
                    unset($hashes[$i]);
                    $fila->forceFill(['dos_pasos_recuperacion' => array_values($hashes)])->save();

                    return true;
                }
            }

            return false;
        });
    }

    /** @return list<string> 10 códigos como «a1b2c-3d4e5» */
    private static function nuevosCodigos(): array
    {
        return array_map(fn () => self::formato(strtolower(Str::random(10))), range(1, self::CODIGOS_RECUPERACION));
    }

    private static function formato(string $diezCaracteres): string
    {
        return substr($diezCaracteres, 0, 5).'-'.substr($diezCaracteres, 5, 5);
    }
}
