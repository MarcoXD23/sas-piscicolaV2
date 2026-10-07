<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Con HTTPS, la cookie de sesión se marca segura sola (solo viaja cifrada).
 * Corre en el grupo global, después de TrustProxies y antes de abrir la sesión: así también detecta el HTTPS
 * que llega a través de un proxy de confianza (TRUSTED_PROXIES), y nunca el que declara un proxy falso.
 * Si SESSION_SECURE_COOKIE tiene valor (true o false), se respeta tal cual.
 */
class CookieSeguraConHttps
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! is_bool(config('session.secure')) && $request->isSecure()) {
            config(['session.secure' => true]);
        }

        return $next($request);
    }
}
