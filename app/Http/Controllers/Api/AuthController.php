<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(LoginRequest $request): JsonResponse
    {
        $usuario = User::query()->where('email', $request->input('email'))->first();

        if ($usuario === null || ! Hash::check($request->input('password'), $usuario->password)) {
            throw ValidationException::withMessages([
                'email' => __('mensajes.auth.correo_contrasena_no_son_correctos'),
            ]);
        }

        if ($usuario->tenant?->estaSuspendida()) {
            throw ValidationException::withMessages(['email' => __('plataforma.finca_suspendida')]);
        }

        if (! $usuario->estaAprobado()) {
            throw ValidationException::withMessages([
                'email' => __('mensajes.auth.tu_cuenta_aun_no_ha'),
            ]);
        }

        $token = $usuario->createToken($request->input('dispositivo', 'api'))->plainTextToken;

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
