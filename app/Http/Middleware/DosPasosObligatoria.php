<?php

namespace App\Http\Middleware;

use App\Models\Rol;
use App\Models\User;
use App\Support\Ajustes\AjustesFinca;
use App\Support\Ajustes\CatalogoAjustes;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ajuste «Verificación en dos pasos obligatoria» (Ajustes › Seguridad): con el ajuste encendido, el propietario y el
 * administrador que no la tienen van a activarla antes de seguir, como en el panel de plataforma. Trabajador y celador:
 * siempre opcional.
 * - Nunca atrapa: activarla, ver los códigos, cerrar sesión y aceptar los términos siempre se pueden (la invitación
 *   está fuera de este grupo de rutas).
 * - Los registros de la cola sin conexión y las peticiones JSON reciben 401: la cola los guarda (no los pierde) y pide
 *   entrar de nuevo, hasta que la persona active los dos pasos.
 */
class DosPasosObligatoria
{
    /** Rutas libres aunque falte activarla. */
    private const LIBRES = ['logout', 'legal.aceptar', 'legal.aceptar.store', 'mi-cuenta.dos-pasos.activar', 'mi-cuenta.dos-pasos.confirmar', 'mi-cuenta.dos-pasos.codigos'];

    public function handle(Request $request, Closure $next): Response
    {
        $usuario = $request->user();
        if (! $usuario instanceof User
            || in_array($request->route()?->getName(), self::LIBRES, true)
            || $usuario->tieneDosPasos()
            || ! $usuario->tieneRol(Rol::PROPIETARIO, Rol::ADMINISTRADOR)
            || AjustesFinca::valor(CatalogoAjustes::SEGURIDAD_DOS_PASOS) !== CatalogoAjustes::ENCENDIDA) {
            return $next($request);
        }

        if ($request->expectsJson() || $request->hasHeader(RegistroSinConexion::CABECERA_ID)) {
            return response()->json(['message' => __('seguridad.dos_pasos_obligatoria.pendiente'), 'dos_pasos_obligatoria' => true], 401);
        }

        return redirect()->route('mi-cuenta.dos-pasos.activar')->with('estado', __('seguridad.dos_pasos_obligatoria.pendiente'));
    }
}
