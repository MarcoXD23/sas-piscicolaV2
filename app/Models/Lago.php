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

    /**
     * Devuelve la siembra más reciente del lago.
     * En un sistema real puede requerirse un campo 'estado' (activa/cosechada),
     * por ahora asumimos que la última siembra es la activa.
     */
    public function siembraActiva(): HasOne
    {
        return $this->hasOne(Siembra::class)->latestOfMany();
    }
}
