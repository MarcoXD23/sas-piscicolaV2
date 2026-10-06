<?php

namespace App\Http\Middleware;

use App\Support\Region;
use App\Support\TenantActual;
use Closure;
use Illuminate\Http\Request;
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

        abort_if($usuario === null, 401);

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
}
