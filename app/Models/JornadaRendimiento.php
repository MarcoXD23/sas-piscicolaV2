<?php

namespace App\Models;

use App\Models\Concerns\PerteneceATenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Rendimiento de una especie en una jornada cerrada (tema 12, a): limpio ÷ (entero − reproducción), en %. Guarda la
 * referencia con que se comparó (la del catálogo o el promedio real), si quedó bajo y el motivo que anotó el
 * administrador. Sin limpio, el rendimiento queda sin dato.
 */
class JornadaRendimiento extends Model
{
    use PerteneceATenant;

    public const CATALOGO = 'catalogo';

    public const PROMEDIO = 'promedio';

    protected $table = 'jornada_rendimientos';

    protected $fillable = ['jornada_id', 'especie_id', 'entero_kg', 'reproduccion_kg', 'limpio_kg', 'rendimiento', 'referencia', 'fuente_referencia', 'bajo', 'motivo'];

    protected function casts(): array
    {
        return [
            'entero_kg' => 'float',
            'reproduccion_kg' => 'float',
            'limpio_kg' => 'float',
            'rendimiento' => 'float',
            'referencia' => 'float',
            'bajo' => 'boolean',
        ];
    }

    public function jornada(): BelongsTo
    {
        return $this->belongsTo(Jornada::class);
    }

    public function especie(): BelongsTo
    {
        return $this->belongsTo(Especie::class);
    }
}
