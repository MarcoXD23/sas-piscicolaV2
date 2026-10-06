<?php

namespace App\Models;

use App\Models\Concerns\PerteneceATenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Muestreo extends Model
{
    use HasFactory, PerteneceATenant;

    protected $fillable = [
        'siembra_id',
        'user_id',
        'fecha',
        'peces_pesados',
        'peso_total_g',
        'peso_promedio_g',
    ];

    protected function casts(): array
    {
        return ['fecha' => 'date'];
    }

    public function siembra(): BelongsTo
    {
        return $this->belongsTo(Siembra::class);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
