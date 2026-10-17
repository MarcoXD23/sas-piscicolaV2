<?php

namespace App\Models;

use App\Models\Concerns\PerteneceATenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Una pesada del limpio de la jornada por presentación (las cuentas de CalculoPesaje), o el total escrito (tipo «total»).
 * El limpio total de la jornada es la suma: nadie lo digita.
 */
class PesajeLimpio extends Model
{
    use PerteneceATenant;

    public const TOTAL = 'total';

    protected $table = 'pesajes_limpio';

    protected $fillable = ['jornada_id', 'presentacion_id', 'tipo', 'canastas', 'tara_unitaria_kg', 'bruto_kg', 'tara_kg', 'limpio_kg', 'user_id'];

    protected function casts(): array
    {
        return [
            'canastas' => 'integer',
            'tara_unitaria_kg' => 'float',
            'bruto_kg' => 'float',
            'tara_kg' => 'float',
            'limpio_kg' => 'float',
        ];
    }

    public function jornada(): BelongsTo
    {
        return $this->belongsTo(Jornada::class);
    }

    public function presentacion(): BelongsTo
    {
        return $this->belongsTo(Presentacion::class);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
