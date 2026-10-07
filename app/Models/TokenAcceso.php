<?php

namespace App\Models;

use App\Models\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Token de la API. Al validar un token todavía no hay finca activa:
 * el dueño del token se carga sin el filtro de finca (búsqueda de identidad). Después, EstablecerTenant fija la finca.
 */
class TokenAcceso extends PersonalAccessToken
{
    protected $table = 'personal_access_tokens';

    public function tokenable(): MorphTo
    {
        return $this->morphTo('tokenable')->withoutGlobalScopes([TenantScope::class]);
    }
}
