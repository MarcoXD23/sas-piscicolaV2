<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Uso: ->middleware('plataforma_dos_pasos'), después de 'auth:plataforma'.
 * La verificación en dos pasos es obligatoria en el panel: sin ella activa solo se puede activar o salir.
 */
class PlataformaConDosPasos
{
    private const PERMITIDAS = ['plataforma.dos-pasos.activar', 'plataforma.dos-pasos.confirmar', 'plataforma.logout'];

    public function handle(Request $request, Closure $next): Response
    {
        $administrador = $request->user('plataforma');

        if ($administrador !== null && ! $administrador->tieneDosPasos() && ! $request->routeIs(...self::PERMITIDAS)) {
            return redirect()->route('plataforma.dos-pasos.activar')->with('estado', __('plataforma.dos_pasos.obligatoria'));
        }

        return $next($request);
    }
}
