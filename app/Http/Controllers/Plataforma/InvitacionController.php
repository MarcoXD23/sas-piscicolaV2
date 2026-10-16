<?php

namespace App\Http\Controllers\Plataforma;

use App\Http\Controllers\Controller;
use App\Models\InvitacionFinca;
use App\Support\Fincas\CreadorFinca;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Invitaciones para crear una finca (panel de plataforma): invitar un correo (una invitación por correo) y anular una
 * invitación pendiente.
 * El enlace se muestra una sola vez, al crearla (solo se guarda su sha256); el sistema no envía correos.
 */
class InvitacionController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $datos = $request->validate(['correo' => ['required', 'email', 'max:255']]);
        $correo = mb_strtolower(trim($datos['correo']));

        // Una invitación por correo (sin importar mayúsculas ni espacios):
        // - ya usó una o ya tiene cuenta (el correo es único en todo el sistema): no se invita de nuevo;
        // - tiene una pendiente: no se crea otra hasta anularla;
        // - la suya venció o fue anulada sin usarse: sí se puede invitar de nuevo.
        $anteriores = InvitacionFinca::query()->whereRaw('LOWER(TRIM(correo)) = ?', [$correo])->get();
        if (CreadorFinca::correoUsado($correo) || $anteriores->contains(fn (InvitacionFinca $i) => $i->estado() === InvitacionFinca::USADA)) {
            throw ValidationException::withMessages(['correo' => __('invitaciones.correo_ya_uso')]);
        }
        if ($anteriores->contains(fn (InvitacionFinca $i) => $i->estado() === InvitacionFinca::PENDIENTE)) {
            throw ValidationException::withMessages(['correo' => __('invitaciones.correo_con_pendiente')]);
        }

        [$invitacion, $codigo] = InvitacionFinca::crear($correo, $request->user('plataforma'));

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
