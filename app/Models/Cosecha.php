<?php

namespace App\Models;

use App\Models\Concerns\PerteneceATenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cosecha extends Model
{
    use PerteneceATenant;

    protected $fillable = ['siembra_id', 'lago_id', 'user_id', 'fecha', 'peces', 'kg', 'peso_promedio_g', 'observaciones'];

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'kg' => 'float',
            'peso_promedio_g' => 'float',
        ];
    }

    public function siembra(): BelongsTo
    {
        return $this->belongsTo(Siembra::class);
    }

    public function lago(): BelongsTo
    {
        return $this->belongsTo(Lago::class);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function ventas(): HasMany
    {
        return $this->hasMany(Venta::class);
    }

    /** Kg de esta cosecha que aún no se han vendido. Usa ventas_sum_kg si viene precargado. */
    public function kgDisponibles(): float
    {
        $vendidos = $this->ventas_sum_kg ?? $this->ventas()->sum('kg');

        return round($this->kg - (float) $vendidos, 2);
    }
}
