<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Ingreso;
use App\Models\User;
use App\Support\DosPasos;
use App\Support\Seguridad\RegistroIngresos;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Segundo paso del ingreso web: la contraseña ya se revisó (LoginController), pero la persona no entra
 * hasta escribir un código válido de su app o uno de recuperación. El pendiente vence a los 5 minutos.
 */
class DosPasosController extends Controller
{
    public const SESION = 'dos_pasos_pendiente';

    public const MINUTOS = 5;

    /** Intentos por minuto por persona (además del límite por IP de la ruta). */
    public const INTENTOS = 5;

    public function create(Request $request): View|RedirectResponse
    {
        if ($this->pendiente($request) === null) {
            return redirect()->route('login');
        }

        return view('auth.dos-pasos');
    }

    public function store(Request $request): RedirectResponse
    {
        $usuario = $this->pendiente($request);
        if ($usuario === null) {
            return redirect()->route('login')->withErrors(['email' => __('cuenta.dos_pasos.vencido')]);
        }

        $codigo = (string) $request->validate(['codigo' => ['required', 'string', 'max:20']])['codigo'];

        $clave = 'dos-pasos|'.$usuario->id;
        if (RateLimiter::tooManyAttempts($clave, self::INTENTOS)) {
            throw ValidationException::withMessages(['codigo' => __('cuenta.dos_pasos.demasiados', ['segundos' => RateLimiter::availableIn($clave)])]);
        }

        if (! DosPasos::verificar($usuario, $codigo)) {
            RateLimiter::hit($clave);
            RegistroIngresos::fallido($usuario, $request, Ingreso::MOTIVO_CODIGO);

            throw ValidationException::withMessages(['codigo' => __('cuenta.dos_pasos.codigo_invalido')]);
        }

        RateLimiter::clear($clave);
        $recordar = (bool) $request->session()->pull(self::SESION)['recordar'];
        Auth::guard('web')->login($usuario, $recordar);
        $request->session()->regenerate();
        RegistroIngresos::exitoso($usuario, $request);

        return redirect()->intended(route('inicio'));
    }

    /** Persona con la contraseña ya revisada y el código pendiente; null si no hay o ya venció. */
    private function pendiente(Request $request): ?User
    {
        $pendiente = $request->session()->get(self::SESION);
        if (! is_array($pendiente) || ($pendiente['vence'] ?? 0) < now()->getTimestamp()) {
            $request->session()->forget(self::SESION);

            return null;
        }

        // Búsqueda de identidad sin el filtro de finca (ProveedorUsuarios)
        $usuario = Auth::guard('web')->getProvider()->retrieveById($pendiente['usuario']);

        return $usuario instanceof User && $usuario->tieneDosPasos() ? $usuario : null;
    }
}
