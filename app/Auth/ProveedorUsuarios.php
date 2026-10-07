<?php

namespace App\Auth;

use App\Models\Scopes\TenantScope;
use Illuminate\Auth\EloquentUserProvider;

/**
 * Proveedor de usuarios de finca para la autenticación (login, sesión, "recordarme" y recuperar contraseña).
 * Antes de saber la finca hay que encontrar a la persona por correo o id (el correo es único en todo el sistema),
 * así que solo estas búsquedas de identidad van sin el filtro de finca. Después, EstablecerTenant fija la finca.
 */
class ProveedorUsuarios extends EloquentUserProvider
{
    protected function newModelQuery($model = null)
    {
        return parent::newModelQuery($model)->withoutGlobalScope(TenantScope::class);
    }
}
