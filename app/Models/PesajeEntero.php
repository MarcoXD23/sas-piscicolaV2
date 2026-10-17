<?php

namespace App\Models;

use App\Models\Concerns\PerteneceATenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Una pesada del entero de un lago y una especie en la jornada (las mismas cuentas de CalculoPesaje), o el total
 * escrito (tipo «total», sin canastas). entero_kg es el pescado sin procesar, ya sin canastas.
 */
class PesajeEntero extends Model
{
    use PerteneceATenant;

    public const TOTAL = 'total';

    protected $table = 'pesajes_entero';

    protected $fillable = ['jornada_lago_id', 'especie_id', 'tipo', 'canastas', 'tara_unitaria_kg', 'bruto_kg', 'tara_kg', 'entero_kg', 'user_id'];

    protected function casts(): array
    {
        return [
            'canastas' => 'integer',
            'tara_unitaria_kg' => 'float',
            'bruto_kg' => 'float',
            'tara_kg' => 'float',
            'entero_kg' => 'float',
        ];
    }

    public function jornadaLago(): BelongsTo
    {
        return $this->belongsTo(JornadaLago::class);
    }

    public function especie(): BelongsTo
    {
        return $this->belongsTo(Especie::class);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
