<?php

namespace App\Support\Seguridad;

use App\Models\User;
use App\Support\Region;
use App\Support\TenantActual;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Sesiones abiertas de la tabla sessions (SESSION_DRIVER=database): verlas y cerrarlas.
 * Cerrar las sesiones de alguien también cambia su token de «recordarme» y revoca sus tokens de la API, para que no
 * vuelva a entrar solo con una cookie vieja.
 */
final class Sesiones
{
    /**
     * Sesiones de una persona, de la más reciente a la más vieja.
     *
     * @return Collection<int, array{id: string, equipo: string, red: ?string, actividad: CarbonImmutable, actual: bool}>
     */
    public static function de(User $usuario, string $actual): Collection
    {
        return DB::table('sessions')->where('user_id', $usuario->id)->orderByDesc('last_activity')->get()
            ->map(fn ($sesion) => [
                'id' => $sesion->id,
                'equipo' => Equipo::describir($sesion->user_agent),
                'red' => Equipo::red($sesion->ip_address),
                'actividad' => CarbonImmutable::createFromTimestamp($sesion->last_activity)->setTimezone(Region::zona()),
                'actual' => hash_equals($sesion->id, $actual),
            ]);
    }

    /** Cierra una sesión de la persona (nunca la de otra). Devuelve si existía. */
    public static function cerrarUna(User $usuario, string $id): bool
    {
        return DB::table('sessions')->where('user_id', $usuario->id)->where('id', $id)->delete() > 0;
    }

    /** Cierra todas las sesiones de la persona menos la actual, cambia su «recordarme» y revoca sus tokens de la API. */
    public static function cerrarDemas(User $usuario, string $actual): int
    {
        $cerradas = DB::table('sessions')->where('user_id', $usuario->id)->where('id', '!=', $actual)->delete();
        self::olvidarRecordarme($usuario);
        $usuario->tokens()->delete();

        return $cerradas;
    }

    /**
     * Cierra las sesiones de todo el personal de la finca menos las de quien lo pide, cambia su «recordarme» y revoca
     * sus tokens de la API. Devuelve cuántas personas quedaron fuera.
     */
    public static function cerrarPersonal(User $quien): int
    {
        $personal = TenantActual::sinFiltro(fn () => User::query()->where('tenant_id', $quien->tenant_id)->whereKeyNot($quien->id)->get());

        DB::transaction(function () use ($personal) {
            DB::table('sessions')->whereIn('user_id', $personal->modelKeys())->delete();
            foreach ($personal as $persona) {
                self::olvidarRecordarme($persona);
                $persona->tokens()->delete();
            }
        });

        return $personal->count();
    }

    private static function olvidarRecordarme(User $usuario): void
    {
        $usuario->forceFill(['remember_token' => Str::random(60)])->saveQuietly();
    }
}
