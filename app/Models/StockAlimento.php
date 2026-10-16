<?php

namespace App\Models;

use App\Models\Concerns\PerteneceATenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockAlimento extends Model
{
    use HasFactory, PerteneceATenant;

    protected $table = 'stocks_alimento';

    protected $fillable = [
        'concentrado_id',
        'tipo_concentrado',
        'cantidad_kg',
    ];

    public function concentrado(): BelongsTo
    {
        return $this->belongsTo(Concentrado::class);
    }
}
