<?php

namespace App\Models;

use App\Models\Concerns\PerteneceATenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Proceso de la finca (por ejemplo, descamado), para armar las presentaciones (tema 6). Si ya se usó en un pesaje de
 * limpio no se borra: se desactiva.
 */
class Proceso extends Model
{
    use PerteneceATenant;

    protected $fillable = ['nombre', 'orden', 'activo'];

    protected function casts(): array
    {
        return ['activo' => 'boolean'];
    }

    public function scopeActivos(Builder $consulta): void
    {
        $consulta->where('activo', true);
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
