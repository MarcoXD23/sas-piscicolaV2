<?php

namespace App\Models;

use App\Models\Concerns\PerteneceATenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pago parcial o total de una venta a crédito.
 */
class Abono extends Model
{
    use PerteneceATenant;

    public const MEDIOS = ['efectivo' => 'Efectivo', 'transferencia' => 'Transferencia', 'nomina' => 'Descuento de nómina'];

    protected $fillable = ['venta_id', 'liquidacion_id', 'user_id', 'fecha', 'valor', 'medio', 'observaciones'];

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'valor' => 'integer',
        ];
    }

    public function venta(): BelongsTo
    {
        return $this->belongsTo(Venta::class);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
