<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Auth\DosPasosController;
use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Models\Ingreso;
use App\Models\User;
use App\Support\DosPasos;
use App\Support\Seguridad\RegistroIngresos;
use App\Support\TenantActual;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(LoginRequest $request): JsonResponse
    {
        // Búsqueda de identidad: todavía no hay finca activa (el correo es único en todo el sistema)
        $usuario = TenantActual::sinFiltro(fn () => User::query()->where('email', $request->input('email'))->first());

        if ($usuario === null || ! Hash::check($request->input('password'), $usuario->password)) {
            // Intento fallido de una persona que existe: queda en sus ingresos (un correo inventado no se guarda)
            if ($usuario !== null) {
                RegistroIngresos::fallido($usuario, $request, Ingreso::MOTIVO_CONTRASENA, Ingreso::CANAL_API);
            }

            throw ValidationException::withMessages([
                'email' => __('mensajes.auth.correo_contrasena_no_son_correctos'),
            ]);
        }

        if ($usuario->tenant?->estaSuspendida()) {
            RegistroIngresos::fallido($usuario, $request, Ingreso::MOTIVO_SUSPENDIDA, Ingreso::CANAL_API);
            throw ValidationException::withMessages(['email' => __('plataforma.finca_suspendida')]);
        }

        if (! $usuario->estaAprobado()) {
            RegistroIngresos::fallido($usuario, $request, Ingreso::MOTIVO_PENDIENTE, Ingreso::CANAL_API);
            throw ValidationException::withMessages([
                'email' => __('mensajes.auth.tu_cuenta_aun_no_ha'),
            ]);
        }

        // Con verificación en dos pasos, el token solo se entrega con un código válido (de la app o de recuperación)
        if ($usuario->tieneDosPasos()) {
            $this->segundoPaso($request, $usuario);
        }

        $token = $usuario->createToken($request->input('dispositivo', 'api'))->plainTextToken;
        RegistroIngresos::exitoso($usuario, $request, Ingreso::CANAL_API);

        return response()->json([
            'token' => $token,
            'usuario' => $this->datosUsuario($usuario),
        ]);
    }

    public function yo(Request $request): JsonResponse
    {
        return response()->json(['usuario' => $this->datosUsuario($request->user())]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['mensaje' => __('mensajes.auth.sesion_cerrada')]);
    }

    /** Exige y revisa el código del segundo paso, con su propio límite de intentos por persona. */
    private function segundoPaso(Request $request, User $usuario): void
    {
        $codigo = (string) $request->input('codigo', '');
        if (trim($codigo) === '') {
            // La app móvil sabe así que debe pedir el código y volver a enviar el ingreso
            throw new HttpResponseException(response()->json([
                'message' => __('cuenta.dos_pasos.requerido'),
                'errors' => ['codigo' => [__('cuenta.dos_pasos.requerido')]],
                'dos_pasos_requerido' => true,
            ], 422));
        }

        $clave = 'dos-pasos|'.$usuario->id;
        if (RateLimiter::tooManyAttempts($clave, DosPasosController::INTENTOS)) {
            throw ValidationException::withMessages(['codigo' => __('cuenta.dos_pasos.demasiados', ['segundos' => RateLimiter::availableIn($clave)])]);
        }

        if (! DosPasos::verificar($usuario, $codigo)) {
            RateLimiter::hit($clave);
            RegistroIngresos::fallido($usuario, $request, Ingreso::MOTIVO_CODIGO, Ingreso::CANAL_API);

            throw ValidationException::withMessages(['codigo' => __('cuenta.dos_pasos.codigo_invalido')]);
        }

        RateLimiter::clear($clave);
    }

    /**
     * @return array<string, mixed>
     */
    private function datosUsuario(User $usuario): array
    {
        return [
            'id' => $usuario->id,
            'name' => $usuario->name,
            'email' => $usuario->email,
            'tenant_id' => $usuario->tenant_id,
            'roles' => $usuario->roles->pluck('nombre')->values(),
        ];
    }
}
