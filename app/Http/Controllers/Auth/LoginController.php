<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Models\Rol;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function create(): View
    {
        return view('auth.login');
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        $credenciales = $request->only('email', 'password');

        // Guard «web» explícito: el panel de plataforma usa otro guard. La persona se busca con ProveedorUsuarios.
        $guard = Auth::guard('web');

        if (! $guard->validate($credenciales)) {
            throw ValidationException::withMessages([
                'email' => __('mensajes.login.correo_contrasena_no_son_correctos'),
            ]);
        }

        /** @var User $usuario */
        $usuario = $guard->getProvider()->retrieveByCredentials(['email' => $credenciales['email']]);

        // Suspendida: nadie entra, salvo el propietario de una finca en eliminación (para poder cancelarla)
        $finca = $usuario->tenant()->first();
        if ($finca?->estaSuspendida() && ! ($finca->enEliminacion() && $usuario->tieneRol(Rol::PROPIETARIO))) {
            throw ValidationException::withMessages(['email' => __('plataforma.finca_suspendida')]);
        }

        if (! $usuario->estaAprobado()) {
            throw ValidationException::withMessages([
                'email' => __('mensajes.login.tu_cuenta_aun_no_ha'),
            ]);
        }

        $request->session()->regenerate();

        // Con verificación en dos pasos todavía no entra: queda pendiente del código (DosPasosController)
        if ($usuario->tieneDosPasos()) {
            $request->session()->put(DosPasosController::SESION, [
                'usuario' => $usuario->id,
                'recordar' => $request->boolean('recordarme'),
                'vence' => now()->addMinutes(DosPasosController::MINUTOS)->getTimestamp(),
            ]);

            return redirect()->route('dos-pasos.create');
        }

        $guard->login($usuario, $request->boolean('recordarme'));

        return redirect()->intended(route('inicio'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        // Borra en el navegador las pantallas guardadas para consulta sin conexión (y el service worker)
        return redirect()->route('login')->withHeaders(['Clear-Site-Data' => '"cache", "storage"']);
    }
}
