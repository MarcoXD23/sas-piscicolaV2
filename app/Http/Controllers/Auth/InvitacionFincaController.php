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
 * - El correo es el invitado y no se cambia. El código de la finca lo genera la app (CreadorFinca).
 * - El enlace sirve una sola vez: se bloquea la invitación al crear la finca (dos envíos a la vez crean una sola).
 * - Vencido, anulado, ya usado o inventado: el mismo aviso, con o sin sesión, para no revelar qué invitaciones existen.
 * - Con una sesión abierta no se crea nada (ni se gasta la invitación): se avisa con qué cuenta está abierta y se
 *   ofrece cerrarla y volver al mismo enlace. Antes, el enlace mandaba callado al inicio de esa cuenta.
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
            'sesionAbierta' => Auth::guard('web')->user(),
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
        // Con sesión abierta no se crea la finca: vuelve al aviso y la invitación sigue pendiente
        if (Auth::guard('web')->check()) {
            return redirect()->route('invitacion.show', $codigo);
        }

        // El correo es siempre el invitado, aunque alguien envíe otro
        $request->merge(['correo' => $invitacion->correo, 'pais' => Str::upper((string) $request->input('pais'))]);
        $datos = $request->validate(CreadorFinca::reglas($invitacion->correo) + ['acepto' => ['accepted']], [
            'acepto.accepted' => __('legal.aceptar.marca_la_casilla'),
            'zona_horaria.in' => __('comandos.crear_propietario.zona_invalida'),
        ]);

        $propietario = DB::transaction(function () use ($codigo, $datos) {
            // Se vuelve a buscar bloqueada: si otra petición la usó mientras tanto, ya no está vigente
            $invitacion = InvitacionFinca::vigente($codigo, bloquear: true);
            if ($invitacion === null) {
                return null;
            }

            $propietario = CreadorFinca::crear([
                'finca' => $datos['finca'],
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

    /**
     * «Cerrar sesión y continuar»: cierra la sesión abierta como el cierre normal (LoginController::destroy) y vuelve al
     * mismo enlace; la invitación no se toca. La ruta termina en /logout para que el teléfono avise si hay registros sin
     * enviar y borre lo guardado, igual que al cerrar sesión (resources/js/app.js).
     */
    public function salir(Request $request, string $codigo): RedirectResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('invitacion.show', $codigo)->withHeaders(['Clear-Site-Data' => '"cache", "storage"']);
    }

    private function invalida(): View
    {
        return view('auth.invitacion', ['invitacion' => null]);
    }
}
