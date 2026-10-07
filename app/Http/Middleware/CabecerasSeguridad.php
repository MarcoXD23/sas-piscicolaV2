<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cabeceras de seguridad en todas las respuestas:
 * - la app no se puede meter en un iframe ajeno (clickjacking);
 * - el navegador no adivina tipos de archivo;
 * - no se envía la dirección completa a otros sitios;
 * - cámara solo para la propia app (fotos de especies desde el celular); micrófono y ubicación denegados;
 * - con HTTPS, el navegador recuerda usar siempre HTTPS (HSTS).
 */
class CabecerasSeguridad
{
    public function handle(Request $request, Closure $next): Response
    {
        $respuesta = $next($request);

        $respuesta->headers->set('X-Frame-Options', 'DENY');
        $respuesta->headers->set('X-Content-Type-Options', 'nosniff');
        $respuesta->headers->set('Referrer-Policy', 'same-origin');
        $respuesta->headers->set('Permissions-Policy', 'camera=(self), microphone=(), geolocation=()');

        if ($request->isSecure()) {
            $respuesta->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $respuesta;
    }
}
