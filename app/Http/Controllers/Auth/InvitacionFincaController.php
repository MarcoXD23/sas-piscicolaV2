<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\InvitacionFinca;
use App\Support\Fincas\CreadorFinca;
use App\Support\Region;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Página pública del enlace de invitación: el cliente crea su finca y entra como propietario.
 * - El correo es el invitado y no se cambia.
 * - El enlace sirve una sola vez: se bloquea la invitación al crear la finca (dos envíos a la vez crean una sola).
 * - Vencido, anulado, ya usado o inventado: el mismo aviso, para no revelar qué invitaciones existen.
 */
class InvitacionFincaController extends Controller
{
    public function show(string $codigo): View
    {
        $invitacion = InvitacionFinca::vigente($codigo);
        if ($invitacion === null) {
            return $this->invalida();
        }

        return view('auth.invitacion', [
            'invitacion' => $invitacion,
            'codigo' => $codigo,
            'paises' => Region::paises(),
            'zonas' => Region::zonasValidas(),
            'zonaPorDefecto' => Region::ZONA_POR_DEFECTO,
        ]);
    }

    public function store(Request $request, string $codigo): View|RedirectResponse
    {
        $invitacion = InvitacionFinca::vigente($codigo);
        if ($invitacion === null) {
            return $this->invalida();
        }

        // El correo es siempre el invitado, aunque alguien envíe otro
        $request->merge(['correo' => $invitacion->correo, 'pais' => Str::upper((string) $request->input('pais'))]);
        $datos = $request->validate(CreadorFinca::reglas($invitacion->correo) + ['acepto' => ['accepted']], [
            'acepto.accepted' => __('legal.aceptar.marca_la_casilla'),
            'zona_horaria.in' => __('comandos.crear_propietario.zona_invalida'),
            'codigo.unique' => __('invitaciones.codigo_usado'),
        ]);

        $propietario = DB::transaction(function () use ($codigo, $datos) {
            // Se vuelve a buscar bloqueada: si otra petición la usó mientras tanto, ya no está vigente
            $invitacion = InvitacionFinca::vigente($codigo, bloquear: true);
            if ($invitacion === null) {
                return null;
            }

            $propietario = CreadorFinca::crear([
                'finca' => $datos['finca'],
                'codigo' => $datos['codigo'],
                'pais' => $datos['pais'],
                'zona_horaria' => $datos['zona_horaria'],
                'nombre' => $datos['nombre'],
                'correo' => $invitacion->correo,
                'clave' => $datos['clave'],
            ], aceptoTerminos: true);
            $invitacion->forceFill(['usada_at' => now(), 'finca_creada_id' => $propietario->tenant_id])->save();

            return $propietario;
        });

        if ($propietario === null) {
            return $this->invalida();
        }

        Auth::guard('web')->login($propietario);
        $request->session()->regenerate();

        return redirect()->route('inicio')->with('estado', __('invitaciones.finca_creada', ['finca' => $datos['finca']]));
    }

    private function invalida(): View
    {
        return view('auth.invitacion', ['invitacion' => null]);
    }
}
