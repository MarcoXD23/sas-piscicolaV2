<?php

namespace App\Models;

use App\Casts\InstanteLocal;
use App\Models\Concerns\PerteneceATenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Registro hecho sin conexión que ya se recibió (por su id del teléfono): evita procesarlo dos veces.
 */
class RegistroSinConexion extends Model
{
    use PerteneceATenant;

    protected $table = 'registros_sin_conexion';

    protected $fillable = ['user_id', 'uuid', 'ruta', 'registrado_at'];

    protected function casts(): array
    {
        return ['registrado_at' => InstanteLocal::class];
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
