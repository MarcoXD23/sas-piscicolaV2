<?php

namespace App\Models;

use App\Models\Concerns\PerteneceATenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
}
