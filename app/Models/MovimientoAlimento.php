<?php

namespace App\Models;

use App\Models\Concerns\PerteneceATenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MovimientoAlimento extends Model
{
    use HasFactory, PerteneceATenant;

    protected $table = 'movimientos_alimento';

    protected $fillable = [
        'user_id',
        'lago_id',
        'despacho_id',
        'fecha',
        'tipo_movimiento',
        'tipo_concentrado',
        'cantidad_kg',
        'proveedor',
        'lote',
        'bultos',
        'peso_bulto_kg',
    ];

    protected function casts(): array
    {
        return ['fecha' => 'date'];
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function lago(): BelongsTo
    {
        return $this->belongsTo(Lago::class);
    }

    /** Despacho contra el que se recibió (solo entradas; vacío = recepción sin despacho). */
    public function despacho(): BelongsTo
    {
        return $this->belongsTo(DespachoAlimento::class, 'despacho_id');
    }
}
