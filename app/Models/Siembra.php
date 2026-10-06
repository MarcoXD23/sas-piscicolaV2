<?php

namespace App\Models;

use App\Models\Concerns\PerteneceATenant;
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
    ];

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
        ];
    }

    public const ESPECIES = ['mojarra roja', 'cachama blanca', 'bagre rayado', 'bocachico'];

    /**
     * Ruta pública de la foto de una especie (public/images/especies/<especie>.webp), o null si no hay foto.
     */
    public static function fotoEspecie(string $especie): ?string
    {
        $ruta = 'images/especies/'.str_replace(' ', '-', mb_strtolower($especie)).'.webp';

        return is_file(public_path($ruta)) ? '/'.$ruta : null;
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
}
