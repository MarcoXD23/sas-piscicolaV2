<?php

namespace App\Support\Seguridad;

use App\Models\Ingreso;
use App\Models\User;
use App\Support\TenantActual;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;

/**
 * Registra cada ingreso a una cuenta de la finca, exitoso o fallido (ingreso web, segundo paso y API).
 * - Solo de personas que existen; la red recortada, nunca la IP completa (Equipo::red).
 * - Equipo nuevo: cada navegador lleva una cookie cifrada con un identificador al azar; se guarda su sha256. Un ingreso
 *   exitoso desde un equipo con el que la persona nunca entró (y que no es su primer ingreso) avisa en el inicio.
 *   Borrar las cookies también lo marca como nuevo. La API no tiene cookie: no avisa.
 */
final class RegistroIngresos
{
    public const COOKIE = 'equipo';

    /** La cookie del equipo dura 400 días (el máximo que permiten los navegadores). */
    private const MINUTOS_COOKIE = 400 * 24 * 60;

    public static function exitoso(User $usuario, Request $request, string $canal = Ingreso::CANAL_WEB): Ingreso
    {
        $hash = $canal === Ingreso::CANAL_WEB ? hash('sha256', self::identificadorEquipo($request)) : null;
        // Al entrar todavía no hay finca activa: las consultas corren sin el filtro, por la persona
        $nuevo = $hash !== null && TenantActual::sinFiltro(function () use ($usuario, $hash) {
            $previos = Ingreso::query()->where('user_id', $usuario->id)->where('exitoso', true);

            return (clone $previos)->exists() && ! (clone $previos)->where('equipo_hash', $hash)->exists();
        });

        return self::guardar($usuario, $request, $canal, ['exitoso' => true, 'equipo_hash' => $hash, 'equipo_nuevo' => $nuevo]);
    }

    public static function fallido(User $usuario, Request $request, string $motivo, string $canal = Ingreso::CANAL_WEB): Ingreso
    {
        return self::guardar($usuario, $request, $canal, ['exitoso' => false, 'motivo' => $motivo]);
    }

    /** Borra los ingresos de más de 90 días (todas las fincas; lo corre el programador a diario). */
    public static function limpiar(): int
    {
        return TenantActual::sinFiltro(fn () => Ingreso::query()->where('created_at', '<', now()->subDays(Ingreso::DIAS_GUARDADOS))->delete());
    }

    /** @param  array<string, mixed>  $datos */
    private static function guardar(User $usuario, Request $request, string $canal, array $datos): Ingreso
    {
        // Antes de entrar todavía no hay finca activa: la finca es la de la persona
        $ingreso = (new Ingreso($datos + [
            'user_id' => $usuario->id,
            'canal' => $canal,
            'equipo' => mb_substr(Equipo::describir($request->userAgent()), 0, 120),
            'red' => Equipo::red($request->ip()),
        ]))->forceFill(['tenant_id' => $usuario->tenant_id]);
        $ingreso->save();

        return $ingreso;
    }

    /** El identificador al azar del equipo (de su cookie cifrada); si no tiene, se crea y se envía la cookie. */
    private static function identificadorEquipo(Request $request): string
    {
        $actual = $request->cookie(self::COOKIE);
        if (is_string($actual) && strlen($actual) === 40) {
            return $actual;
        }

        $nuevo = Str::random(40);
        Cookie::queue(Cookie::make(self::COOKIE, $nuevo, self::MINUTOS_COOKIE, null, null, null, true, false, 'lax'));

        return $nuevo;
    }
}
