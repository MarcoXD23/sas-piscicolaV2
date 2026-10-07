<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
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

        // Guard «web» explícito: el panel de plataforma usa otro guard
        $guard = Auth::guard('web');

        if (! $guard->attempt($credenciales, $request->boolean('recordarme'))) {
            throw ValidationException::withMessages([
                'email' => __('mensajes.login.correo_contrasena_no_son_correctos'),
            ]);
        }

        if ($guard->user()->tenant()->first()?->estaSuspendida()) {
            $guard->logout();

            throw ValidationException::withMessages(['email' => __('plataforma.finca_suspendida')]);
        }

        if (! $guard->user()->estaAprobado()) {
            $guard->logout();

            throw ValidationException::withMessages([
                'email' => __('mensajes.login.tu_cuenta_aun_no_ha'),
            ]);
        }

        $request->session()->regenerate();

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
