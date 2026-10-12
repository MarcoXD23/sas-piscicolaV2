<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Global (bootstrap/app.php): cada respuesta lleva la hora del servidor en UTC (X-Hora-Servidor), también /up,
 * /campo/token y /campo/paquete. El teléfono guarda la más reciente por finca y persona y la adjunta a cada registro
 * sin conexión (X-Ultima-Hora-Servidor): el servidor rechaza el que diga haber ocurrido antes (RegistroSinConexion).
 */
class HoraServidor
{
    public const CABECERA = 'X-Hora-Servidor';

    public function handle(Request $request, Closure $next): Response
    {
        $respuesta = $next($request);
        $respuesta->headers->set(self::CABECERA, now()->utc()->format('Y-m-d\TH:i:s.v\Z'));

        return $respuesta;
    }
}
