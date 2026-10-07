<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\Region;
use App\Support\TenantActual;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Fija el tenant de la petición a partir del usuario autenticado.
 * Debe ir después de la autenticación (web o Sanctum).
 */
class EstablecerTenant
{
    public function __construct(private TenantActual $tenantActual) {}

    public function handle(Request $request, Closure $next): Response
    {
        $usuario = $request->user();

        // Solo usuarios de finca: el acceso de plataforma no tiene finca
        abort_unless($usuario instanceof User, 401);

        // Finca suspendida desde el panel de plataforma: se cierra la sesión (o el token) con un mensaje claro
        // Se consulta en la base (no la relación en memoria) para que la suspensión aplique de inmediato
        if ($usuario->tenant()->first()?->estaSuspendida()) {
            return $this->rechazarSuspendida($request, $usuario);
        }

        $anterior = $this->tenantActual->id();
        $this->tenantActual->establecer($usuario->tenant_id);

        // Idioma de la finca (solo los que tienen traducción completa)
        if (in_array(Region::idioma(), Region::IDIOMAS, true)) {
            app()->setLocale(Region::idioma());
        }

        try {
            return $next($request);
        } finally {
            $this->tenantActual->establecer($anterior);
        }
    }

    private function rechazarSuspendida(Request $request, User $usuario): Response
    {
        $mensaje = __('plataforma.finca_suspendida');

        if ($request->is('api/*') || $request->bearerToken() !== null) {
            $usuario->currentAccessToken()?->delete();

            return response()->json(['message' => $mensaje], 403);
        }

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->withErrors(['email' => $mensaje]);
    }
}
