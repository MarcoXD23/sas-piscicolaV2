<?php

namespace App\Models;

use App\Models\Concerns\TieneDosPasos;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * Dueño del software: entra al panel de plataforma con el guard «plataforma».
 * No pertenece a ninguna finca ni tiene roles de finca. La verificación en dos pasos es obligatoria
 * (middleware PlataformaConDosPasos).
 */
#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token', 'dos_pasos_clave', 'dos_pasos_recuperacion', 'dos_pasos_ultimo_paso'])]
class AdministradorPlataforma extends Authenticatable
{
    use TieneDosPasos;

    protected $table = 'administradores_plataforma';

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            // Clave cifrada con APP_KEY; códigos de recuperación solo como hashes
            'dos_pasos_clave' => 'encrypted',
            'dos_pasos_activado_at' => 'immutable_datetime',
            'dos_pasos_ultimo_paso' => 'integer',
            'dos_pasos_recuperacion' => 'array',
        ];
    }
}
