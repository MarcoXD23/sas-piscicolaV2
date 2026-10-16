<?php

namespace App\Http\Controllers\Plataforma;

use App\Http\Controllers\Controller;
use App\Models\InvitacionFinca;
use App\Support\Fincas\CreadorFinca;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Invitaciones para crear una finca (panel de plataforma): invitar un correo y anular una invitación pendiente.
 * El enlace se muestra una sola vez, al crearla (solo se guarda su sha256); el sistema no envía correos.
 */
class InvitacionController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $datos = $request->validate(['correo' => ['required', 'email', 'max:255']]);

        // Un correo que ya tiene cuenta no puede crear otra finca (el correo es único en todo el sistema)
        if (CreadorFinca::correoUsado($datos['correo'])) {
            throw ValidationException::withMessages(['correo' => __('invitaciones.correo_con_cuenta')]);
        }

        [$invitacion, $codigo] = InvitacionFinca::crear($datos['correo'], $request->user('plataforma'));

        return back()
            ->with('estado', __('invitaciones.creada', ['correo' => $invitacion->correo, 'dias' => InvitacionFinca::DIAS_VIGENCIA]))
            ->with('invitacion_enlace', route('invitacion.show', $codigo));
    }

    public function anular(InvitacionFinca $invitacion): RedirectResponse
    {
        abort_unless($invitacion->estado() === InvitacionFinca::PENDIENTE, 404);
        $invitacion->forceFill(['anulada_at' => now()])->save();

        return back()->with('estado', __('invitaciones.anulada', ['correo' => $invitacion->correo]));
    }
}
