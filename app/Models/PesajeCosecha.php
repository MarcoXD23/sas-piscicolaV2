<?php

namespace App\Models;

use App\Models\Concerns\PerteneceATenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Peso de una canasta en una cosecha: bruto, tara de la canasta y neto.
 */
class PesajeCosecha extends Model
{
    use PerteneceATenant;

    protected $table = 'pesajes_cosecha';

    protected $fillable = ['cosecha_id', 'numero', 'bruto_kg', 'tara_kg', 'neto_kg'];

    protected function casts(): array
    {
        return [
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
