<?php

namespace App\Models;

use App\Models\Concerns\PerteneceATenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Presentación: especie + tamaño + proceso (tema 6), activada en la tabla del catálogo, sin escribir cada combinación a
 * mano. El color de cabuya es texto libre de la finca. Nunca se borra: al quitarla del catálogo se desactiva.
 */
class Presentacion extends Model
{
    use PerteneceATenant;

    protected $table = 'presentaciones';

    protected $fillable = ['especie_id', 'tamano_id', 'proceso_id', 'color_cabuya', 'activo'];

    protected function casts(): array
    {
        return ['activo' => 'boolean'];
    }

    public function scopeActivas(Builder $consulta): void
    {
        $consulta->where('activo', true);
    }

    public function especie(): BelongsTo
    {
        return $this->belongsTo(Especie::class);
    }

    public function tamano(): BelongsTo
    {
        return $this->belongsTo(Tamano::class);
    }

    public function proceso(): BelongsTo
    {
        return $this->belongsTo(Proceso::class);
    }

    public function pesajes(): HasMany
    {
        return $this->hasMany(PesajeLimpio::class);
    }

    /** Especie, tamaño y proceso en una frase (necesita los tres cargados). */
    public function nombre(): string
    {
        return __('presentaciones.nombre', ['especie' => $this->especie?->nombre, 'tamano' => $this->tamano?->nombre, 'proceso' => $this->proceso?->nombre]);
    }
}
