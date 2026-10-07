<?php

namespace App\Http\Middleware;

use App\Support\MomentoRegistro;
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
        // Un registro hecho sin conexión se revisa a la hora en que se hizo (MomentoRegistro), no a la de llegada
        if (! $request->user()?->puedeTrabajarComo($rol, MomentoRegistro::actual())) {
            abort(403, __('mensajes.verificar_turno.estas_fuera_tu_turno_solo'));
        }

        return $next($request);
    }
}
