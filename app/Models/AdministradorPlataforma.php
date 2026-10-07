<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * Dueño del software: entra al panel de plataforma con el guard «plataforma».
 * No pertenece a ninguna finca ni tiene roles de finca.
 */
#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class AdministradorPlataforma extends Authenticatable
{
    protected $table = 'administradores_plataforma';

    protected function casts(): array
    {
        return ['password' => 'hashed'];
    }
}
