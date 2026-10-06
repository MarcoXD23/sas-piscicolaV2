<?php

namespace App\Models;

use App\Models\Concerns\PerteneceATenant;
use App\Support\CatalogoEspecies;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Siembra extends Model
{
    use HasFactory, PerteneceATenant;

    protected $fillable = [
        'lago_id',
        'especie',
        'fecha',
        'cantidad_alevinos',
        'peso_promedio_inicial_g',
        'peces_vivos',
        'peso_actual_g',
        'biomasa_kg',
        'finalizada_at',
    ];

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'finalizada_at' => 'datetime',
        ];
    }

    public function lago(): BelongsTo
    {
        return $this->belongsTo(Lago::class);
    }

    public function muestreos(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Muestreo::class);
    }

    public function mortalidades(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Mortalidad::class);
    }

    /**
     * Calcula los días transcurridos desde la fecha de siembra.
     */
    protected function diasCultivo(): Attribute
    {
        return Attribute::get(fn () => (int) $this->fecha->startOfDay()->diffInDays(now()->startOfDay()));
    }

    /** Siembras que siguen en el agua (sin finalizar). */
    public function scopeActivas(Builder $consulta): void
    {
        $consulta->whereNull('finalizada_at');
    }

    public function estaActiva(): bool
    {
        return $this->finalizada_at === null;
    }

    protected function nombreEspecie(): Attribute
    {
        return Attribute::get(fn () => CatalogoEspecies::nombre($this->especie));
    }

    protected function fotoEspecie(): Attribute
    {
        return Attribute::get(fn () => CatalogoEspecies::foto($this->especie));
    }
}
