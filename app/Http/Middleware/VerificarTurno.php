<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Uso: ->middleware('turno:trabajador')
 * Trabajador y celador solo pueden registrar dentro de un turno en curso de ese rol.
 * Propietario y administrador no dependen de turnos.
 */
class VerificarTurno
{
    public function handle(Request $request, Closure $next, string $rol): Response
    {
        if (! $request->user()?->puedeTrabajarComo($rol)) {
            abort(403, __('mensajes.verificar_turno.estas_fuera_tu_turno_solo'));
        }

        return $next($request);
    }
}
