<?php

namespace App\Models;

use App\Models\Concerns\PerteneceATenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Reglas esperadas de una fase para una especie en la finca: peso de entrada y salida, días en la fase,
 * supervivencia esperada, densidad de referencia (peces por m²) y el concentrado que le corresponde (opcional: si no
 * tiene, la ración sugiere el del tramo de peso; ver CalculadoraRacion).
 */
class ReglaFase extends Model
{
    use PerteneceATenant;

    protected $table = 'reglas_fase';

    protected $fillable = [
        'fase_id',
        'especie_id',
        'peso_entrada_g',
        'peso_salida_g',
        'dias_esperados',
        'supervivencia_porcentaje',
        'densidad_min_m2',
        'densidad_max_m2',
        'concentrado_id',
    ];

    protected function casts(): array
    {
        return [
            'peso_entrada_g' => 'float',
            'peso_salida_g' => 'float',
            'dias_esperados' => 'integer',
            'supervivencia_porcentaje' => 'float',
            'densidad_min_m2' => 'float',
            'densidad_max_m2' => 'float',
        ];
    }

    public function fase(): BelongsTo
    {
        return $this->belongsTo(Fase::class);
    }

    public function especie(): BelongsTo
    {
        return $this->belongsTo(Especie::class);
    }

    public function concentrado(): BelongsTo
    {
        return $this->belongsTo(Concentrado::class);
    }
}

