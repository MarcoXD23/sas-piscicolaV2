<?php

namespace App\Http\Controllers\Plataforma;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Ingreso al panel de plataforma con el guard «plataforma» (separado del ingreso de las fincas).
 * Con la verificación en dos pasos activa, la contraseña sola no abre sesión: queda pendiente del código
 * (Plataforma\DosPasosController). Sin ella activa, entra y el panel le exige activarla (PlataformaConDosPasos).
 */
class LoginController extends Controller
{
    public function create(): View
    {
        return view('plataforma.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credenciales = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $guard = Auth::guard('plataforma');
        $administrador = $guard->getProvider()->retrieveByCredentials(['email' => mb_strtolower($credenciales['email'])]);
        if ($administrador === null || ! $guard->getProvider()->validateCredentials($administrador, ['password' => $credenciales['password']])) {
            throw ValidationException::withMessages(['email' => __('plataforma.login.incorrecto')]);
        }

        $request->session()->regenerate();

        if ($administrador->tieneDosPasos()) {
            $request->session()->put(DosPasosController::SESION, [
                'administrador' => $administrador->id,
                'vence' => now()->addMinutes(DosPasosController::MINUTOS)->getTimestamp(),
            ]);

            return redirect()->route('plataforma.dos-pasos.create');
        }

        $guard->login($administrador);

        return redirect()->route('plataforma.dos-pasos.activar');
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('plataforma')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        // Borra en el navegador cualquier copia guardada para consulta sin conexión
        return redirect()->route('plataforma.login')->withHeaders(['Clear-Site-Data' => '"cache", "storage"']);
    }
}
