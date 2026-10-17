<?php

namespace App\Models;

use App\Models\Concerns\PerteneceATenant;
use App\Support\Region;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Lago extends Model
{
    use HasFactory, PerteneceATenant;

    protected $fillable = ['nombre', 'tipo', 'fase_id', 'area_m2', 'fase_desde'];

    protected function casts(): array
    {
        return [
            'area_m2' => 'float',
            'fase_desde' => 'date',
        ];
    }

    /**
     * Desde cuándo el lago está en su fase (para los días en la fase): al ponerle o cambiarle la fase queda el día de
     * hoy en la zona de la finca; sin fase, vacío. Si se escribe fase_desde a propósito, se respeta.
     */
    protected static function booted(): void
    {
        static::saving(function (Lago $lago) {
            if ($lago->isDirty('fase_id') && ! $lago->isDirty('fase_desde')) {
                $lago->fase_desde = $lago->fase_id === null ? null : Region::hoy()->toDateString();
            }
        });
    }

    public const TIPOS = ['tierra', 'geomembrana', 'concreto'];

    public function fase(): BelongsTo
    {
        return $this->belongsTo(Fase::class);
    }

    /** @return array<string, string> código => texto en el idioma de la finca */
    public static function opciones(): array
    {
        return collect(self::TIPOS)->mapWithKeys(fn (string $codigo) => [$codigo => __('lagos.tipos.'.$codigo)])->all();
    }

    public function siembras(): HasMany
    {
        return $this->hasMany(Siembra::class);
    }

    public function cosechas(): HasMany
    {
        return $this->hasMany(Cosecha::class);
    }

    /** Siembras que siguen en el agua. Con policultivo puede haber varias (de distinta especie). */
    public function siembrasActivas(): HasMany
    {
        return $this->hasMany(Siembra::class)->whereNull('finalizada_at')->orderBy('fecha');
    }

    /** Aviso «listo para pescar» pendiente (solo puede haber uno por lago). */
    public function avisoPescaPendiente(): HasOne
    {
        return $this->hasOne(AvisoPesca::class)->where('estado', AvisoPesca::PENDIENTE);
    }

    public function mediciones(): HasMany
    {
        return $this->hasMany(MedicionAgua::class);
    }

    public function ultimaMedicion(): HasOne
    {
        return $this->hasOne(MedicionAgua::class)->latestOfMany('medido_at');
    }

    public function tratamientos(): HasMany
    {
        return $this->hasMany(Tratamiento::class);
    }

    /** Encendido del aireador que sigue abierto (si el aireador está prendido). */
    public function aireacionActiva(): HasOne
    {
        return $this->hasOne(Aireacion::class)->whereNull('apagado_at');
    }

    /** Tratamientos cuyo período de retiro sigue vigente: el pescado de este lago no se debe cosechar. */
    public function tratamientosEnRetiro(): HasMany
    {
        return $this->hasMany(Tratamiento::class)->enRetiro();
    }
}
