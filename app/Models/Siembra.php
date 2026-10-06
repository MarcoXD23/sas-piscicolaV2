<?php

namespace App\Models;

use App\Casts\InstanteLocal;
use App\Models\Concerns\PerteneceATenant;
use App\Support\CatalogoEspecies;
use App\Support\Region;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Siembra extends Model
{
    use HasFactory, PerteneceATenant;

    protected $fillable = [
        'lago_id',
        'especie',
        'proveedor_alevinos',
        'lote_alevinos',
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
            'finalizada_at' => InstanteLocal::class,
        ];
    }

    public function lago(): BelongsTo
    {
        return $this->belongsTo(Lago::class);
    }

    public function muestreos(): HasMany
    {
        return $this->hasMany(Muestreo::class);
    }

    public function mortalidades(): HasMany
    {
        return $this->hasMany(Mortalidad::class);
    }

    /**
     * Calcula los días transcurridos desde la fecha de siembra.
     */
    protected function diasCultivo(): Attribute
    {
        // Días de calendario entre la siembra y hoy en la zona de la finca
        return Attribute::get(fn () => (int) CarbonImmutable::parse($this->fecha->toDateString())
            ->diffInDays(CarbonImmutable::parse(Region::hoy()->toDateString())));
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
