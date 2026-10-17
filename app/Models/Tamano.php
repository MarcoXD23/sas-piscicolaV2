<?php

namespace App\Models;

use App\Models\Concerns\PerteneceATenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Tamaño de una especie con el nombre que usa la finca (docs/diseno/dia-de-proceso.md, tema 6). El rango de peso es solo
 * informativo: no valida nada. Si ya se usó en un pesaje de limpio no se borra: se desactiva.
 */
class Tamano extends Model
{
    use PerteneceATenant;

    protected $fillable = ['especie_id', 'nombre', 'peso_min_g', 'peso_max_g', 'orden', 'activo'];

    protected function casts(): array
    {
        return ['peso_min_g' => 'float', 'peso_max_g' => 'float', 'activo' => 'boolean'];
    }

    public function scopeActivos(Builder $consulta): void
    {
        $consulta->where('activo', true);
    }

    public function especie(): BelongsTo
    {
        return $this->belongsTo(Especie::class);
    }

    public function presentaciones(): HasMany
    {
        return $this->hasMany(Presentacion::class);
    }

    /** Alguna de sus presentaciones ya se pesó en una jornada. */
    public function enUso(): bool
    {
        return PesajeLimpio::query()->whereIn('presentacion_id', $this->presentaciones()->select('id'))->exists();
    }
}
