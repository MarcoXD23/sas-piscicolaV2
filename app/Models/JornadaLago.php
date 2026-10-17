<?php

namespace App\Models;

use App\Models\Concerns\PerteneceATenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Un lago que se pesca en una jornada. Al cerrar la jornada se marca «quedó vacío» o «quedó pescado» (tema 4) y
 * cierre guarda lo que pasó con cada siembra: kg, peces cosechados, peces que quedan y la diferencia con lo estimado.
 */
class JornadaLago extends Model
{
    use PerteneceATenant;

    public const VACIO = 'vacio';

    public const QUEDO_PESCADO = 'quedo_pescado';

    public const RESULTADOS = [self::VACIO, self::QUEDO_PESCADO];

    protected $table = 'jornada_lagos';

    protected $fillable = ['jornada_id', 'lago_id', 'orden', 'resultado', 'cierre'];

    protected function casts(): array
    {
        return ['cierre' => 'array'];
    }

    public function jornada(): BelongsTo
    {
        return $this->belongsTo(Jornada::class);
    }

    public function lago(): BelongsTo
    {
        return $this->belongsTo(Lago::class);
    }

    public function pesajes(): HasMany
    {
        return $this->hasMany(PesajeEntero::class)->orderBy('id');
    }

    /** Kg de «reproducción» por especie, que vuelven al lago (F6b, solo con el ajuste encendido). */
    public function reproducciones(): HasMany
    {
        return $this->hasMany(ReproduccionJornada::class)->orderBy('id');
    }
}
