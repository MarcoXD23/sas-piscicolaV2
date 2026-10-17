<?php

namespace App\Models;

use App\Models\Concerns\PerteneceATenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Kg de «reproducción» (peces demasiado pequeños que se separan antes del proceso) de un lago y una especie en la
 * jornada. Solo con el ajuste proceso.reproduccion encendido. Esos peces vuelven al lago: se restan del entero antes del
 * reparto y del rendimiento, y las siembras no se tocan.
 */
class ReproduccionJornada extends Model
{
    use PerteneceATenant;

    protected $table = 'reproducciones_jornada';

    protected $fillable = ['jornada_lago_id', 'especie_id', 'kg', 'user_id'];

    protected function casts(): array
    {
        return ['kg' => 'float'];
    }

    public function jornadaLago(): BelongsTo
    {
        return $this->belongsTo(JornadaLago::class);
    }

    public function especie(): BelongsTo
    {
        return $this->belongsTo(Especie::class);
    }
}
