<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cabeceras de seguridad en todas las respuestas:
 * - la app no se puede meter en un iframe ajeno (clickjacking);
 * - el navegador no adivina tipos de archivo;
 * - no se envía la dirección completa a otros sitios;
 * - cámara solo para la propia app (fotos de especies desde el celular); micrófono y ubicación denegados;
 * - con HTTPS, el navegador recuerda usar siempre HTTPS (HSTS);
 * - en las páginas HTML, una Content-Security-Policy estricta: todo sale del mismo sitio, sin scripts ni estilos en
 *   línea (los textos del JS van en un <script type="application/json">, las confirmaciones en data-confirmar y los
 *   tamaños de las gráficas en atributos de SVG), sin evaluar código y sin iframes. Las descargas (PDF, Excel) no la
 *   llevan, para no estorbar al visor del navegador.
 */
class CabecerasSeguridad
{
    /** Política de las páginas HTML. Cada fuente que se agregue necesita una razón (ver CspTest). */
    public const CSP = [
        "default-src 'self'",
        "script-src 'self'",
        "style-src 'self'",
        "img-src 'self'",
        "font-src 'self'",
        "connect-src 'self'",
        "worker-src 'self'",
        "manifest-src 'self'",
        "object-src 'none'",
        "base-uri 'self'",
        "form-action 'self'",
        "frame-ancestors 'none'",
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $respuesta = $next($request);

        if (str_starts_with((string) $respuesta->headers->get('Content-Type', 'text/html'), 'text/html')) {
            $respuesta->headers->set('Content-Security-Policy', self::politica());
        }

        $respuesta->headers->set('X-Frame-Options', 'DENY');
        $respuesta->headers->set('X-Content-Type-Options', 'nosniff');
        $respuesta->headers->set('Referrer-Policy', 'same-origin');
        $respuesta->headers->set('Permissions-Policy', 'camera=(self), microphone=(), geolocation=()');

        if ($request->isSecure()) {
            $respuesta->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $respuesta;
    }

    /**
     * La política, y con «npm run dev» (archivo public/hot) también el servidor de Vite, que inyecta los estilos en
     * línea y recarga por websocket. En producción no existe public/hot y la política queda tal cual.
     */
    public static function politica(): string
    {
        $politica = self::CSP;
        if (Vite::isRunningHot()) {
            $vite = rtrim(trim((string) @file_get_contents(public_path('hot'))), '/');
            $ws = preg_replace('#^http#', 'ws', $vite);
            $politica = array_map(fn (string $regla) => match (strtok($regla, ' ')) {
                'script-src' => "{$regla} {$vite}",
                'style-src' => "{$regla} {$vite} 'unsafe-inline'",
                'connect-src' => "{$regla} {$vite} {$ws}",
                'font-src', 'img-src' => "{$regla} {$vite}",
                default => $regla,
            }, $politica);
        }

        return implode('; ', $politica);
    }
}
