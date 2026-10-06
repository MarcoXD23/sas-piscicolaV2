<?php

namespace App\Models;

use App\Models\Concerns\PerteneceATenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Lago extends Model
{
    use HasFactory, PerteneceATenant;

    protected $fillable = ['nombre', 'tipo'];

    public const TIPOS = ['tierra', 'geomembrana', 'concreto'];

    public function siembras(): HasMany
    {
        return $this->hasMany(Siembra::class);
    }

    /** Siembras que siguen en el agua. Con policultivo puede haber varias (de distinta especie). */
    public function siembrasActivas(): HasMany
    {
        return $this->hasMany(Siembra::class)->whereNull('finalizada_at')->orderBy('fecha');
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
