<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Catálogo global de roles (no es tabla de negocio, no lleva tenant_id).
 */
class Rol extends Model
{
    protected $table = 'roles';

    protected $fillable = ['nombre'];

    public const PROPIETARIO = 'propietario';

    public const ADMINISTRADOR = 'administrador';

    public const TRABAJADOR = 'trabajador';

    public const CELADOR = 'celador';

    public const TODOS = [self::PROPIETARIO, self::ADMINISTRADOR, self::TRABAJADOR, self::CELADOR];

    /** Roles que el propietario puede asignar desde /admin/personal. */
    public const ASIGNABLES = [self::ADMINISTRADOR, self::TRABAJADOR, self::CELADOR];

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'rol_user')->withTimestamps();
    }
}

