<?php

namespace App\Models;

use App\Models\Concerns\PerteneceATenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Precio de venta de una especie (docs/diseno/dia-de-proceso.md, tema 8): el general (particulares y exceso del cupo) y,
 * si la especie entra en el cupo del trabajador, el precio dentro del cupo. Solo lo cambia el propietario (Ajustes ›
 * Precios) y cada cambio queda en la bitácora. Cada venta guarda los precios con que se hizo: un cambio solo aplica a las
 * ventas nuevas. En pesos enteros.
 */
class PrecioEspecie extends Model
{
    use PerteneceATenant;

    protected $table = 'precios_especie';

    protected $fillable = ['especie_id', 'precio_general', 'entra_en_cupo', 'precio_cupo', 'cambiado_por'];

    protected function casts(): array
    {
        return ['precio_general' => 'integer', 'entra_en_cupo' => 'boolean', 'precio_cupo' => 'integer'];
    }

    public function especie(): BelongsTo
    {
        return $this->belongsTo(Especie::class);
    }
}
