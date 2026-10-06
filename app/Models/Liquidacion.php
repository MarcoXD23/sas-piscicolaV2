<?php

namespace App\Models;

use App\Casts\InstanteLocal;
use App\Models\Concerns\PerteneceATenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Liquidación semanal de nómina de una persona.
 */
class Liquidacion extends Model
{
    use PerteneceATenant;

    protected $table = 'liquidaciones';

    protected $fillable = [
        'user_id', 'creada_por', 'semana_inicio', 'semana_fin', 'horas_turnos', 'valor_hora', 'valor_turnos',
        'horas_extra', 'valor_hora_extra', 'valor_horas_extra', 'bruto', 'descuento_pescado', 'total_pagar', 'pagada_at',
    ];

    protected function casts(): array
    {
        return [
            'semana_inicio' => 'date',
            'semana_fin' => 'date',
            'horas_turnos' => 'float',
            'horas_extra' => 'float',
            'valor_hora' => 'integer',
            'valor_turnos' => 'integer',
            'valor_hora_extra' => 'integer',
            'valor_horas_extra' => 'integer',
            'bruto' => 'integer',
            'descuento_pescado' => 'integer',
            'total_pagar' => 'integer',
            'pagada_at' => InstanteLocal::class,
        ];
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function abonos(): HasMany
    {
        return $this->hasMany(Abono::class);
    }

    public function estaPagada(): bool
    {
        return $this->pagada_at !== null;
    }
}
