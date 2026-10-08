<?php

namespace App\Http\Middleware;

use App\Models\AceptacionLegal;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Uso: ->middleware('aceptacion'), después de 'auth' y 'tenant'.
 * Quien no aceptó la versión vigente de los términos y la privacidad no sigue hasta aceptarla:
 * en la web va a /aceptar-terminos; en la API recibe 403 con terminos_pendientes (y acepta en /api/terminos/aceptar).
 * Siempre se puede cerrar sesión y aceptar.
 */
class AceptacionVigente
{
    /** Rutas que se permiten con la aceptación pendiente. */
    private const LIBRES = ['logout', 'legal.aceptar', 'legal.aceptar.store', 'api.logout', 'api.terminos.aceptar'];

    public function handle(Request $request, Closure $next): Response
    {
        $usuario = $request->user();
        if (! $usuario instanceof User || in_array($request->route()?->getName(), self::LIBRES, true) || AceptacionLegal::vigente($usuario)) {
            return $next($request);
        }

        if ($request->is('api/*') || $request->expectsJson()) {
            return response()->json(['message' => __('legal.aceptar.pendiente_api'), 'terminos_pendientes' => true, 'version' => config('legal.version')], 403);
        }

        return redirect()->route('legal.aceptar');
    }
}
