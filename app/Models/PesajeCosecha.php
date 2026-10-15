<?php

namespace App\Models;

use App\Models\Concerns\PerteneceATenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Una PESADA de la cosecha: un montón de pescado en varias canastas o canastillas (ver CalculoPesaje).
 * En la base: canastas = Cantidad, tara_unitaria_kg = peso de una vacía, bruto_kg = peso pesado,
 * tara_kg = descuento (canastas × tara_unitaria_kg) y neto_kg = peso ENTERO (pescado sin procesar, ya sin canastas): la columna conserva el nombre neto_kg, pero guarda el entero. En pantalla nunca se usan bruto, tara ni neto.
 */
class PesajeCosecha extends Model
{
    use PerteneceATenant;

    protected $table = 'pesajes_cosecha';

    protected $fillable = ['cosecha_id', 'numero', 'tipo', 'canastas', 'tara_unitaria_kg', 'bruto_kg', 'tara_kg', 'neto_kg'];

    protected function casts(): array
    {
        return [
            'canastas' => 'integer',
            'tara_unitaria_kg' => 'float',
            'bruto_kg' => 'float',
            'tara_kg' => 'float',
            'neto_kg' => 'float',
        ];
    }

    public function cosecha(): BelongsTo
    {
        return $this->belongsTo(Cosecha::class);
    }
}
