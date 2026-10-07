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

        if (! Auth::guard('plataforma')->attempt(['email' => mb_strtolower($credenciales['email']), 'password' => $credenciales['password']])) {
            throw ValidationException::withMessages(['email' => __('plataforma.login.incorrecto')]);
        }

        $request->session()->regenerate();

        return redirect()->intended(route('plataforma.fincas.index'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('plataforma')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('plataforma.login');
    }
}
