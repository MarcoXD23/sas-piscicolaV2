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
}
