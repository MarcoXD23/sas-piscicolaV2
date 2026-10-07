<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Rules\NoIgualAlCorreo;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as ReglaContrasena;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * "Olvidé mi contraseña": enlace por correo de un solo uso que vence (config/auth.php, passwords.users.expire).
 * Las respuestas no revelan si el correo está registrado.
 */
class RecuperarContrasenaController extends Controller
{
    public function create(): View
    {
        return view('auth.olvide-contrasena');
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate(['email' => ['required', 'email', 'max:255']]);

        // Sin importar el resultado (enviado, correo inexistente o pedido hace poco) la respuesta es la misma
        Password::sendResetLink(['email' => mb_strtolower($request->input('email'))]);

        return back()->with('estado', __('acceso.recuperar.enviado'));
    }

    public function edit(Request $request, string $token): View
    {
        return view('auth.restablecer-contrasena', ['token' => $token, 'email' => $request->query('email')]);
    }

    public function update(Request $request): RedirectResponse
    {
        $datos = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'confirmed', ReglaContrasena::min(8), new NoIgualAlCorreo($request->input('email'))],
        ]);

        $estado = Password::reset(
            ['email' => mb_strtolower($datos['email'])] + $datos + ['password_confirmation' => $request->input('password_confirmation')],
            function (User $usuario, string $contrasena) {
                // La contraseña nueva cierra las sesiones recordadas y revoca los tokens de la API
                $usuario->forceFill(['password' => $contrasena, 'remember_token' => Str::random(60)])->save();
                $usuario->tokens()->delete();

                event(new PasswordReset($usuario));
            },
        );

        if ($estado !== Password::PASSWORD_RESET) {
            // Correo inexistente y enlace inválido, vencido o ya usado reciben el mismo mensaje
            throw ValidationException::withMessages(['email' => __('acceso.recuperar.enlace_invalido')]);
        }

        return redirect()->route('login')->with('estado', __('acceso.recuperar.restablecida'));
    }
}
