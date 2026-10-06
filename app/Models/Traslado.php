<?php

namespace App\Models;

use App\Models\Concerns\PerteneceATenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Traslado extends Model
{
    use PerteneceATenant;

    protected $fillable = [
        'siembra_origen_id', 'siembra_destino_id', 'lago_origen_id', 'lago_destino_id',
        'user_id', 'fecha', 'cantidad', 'peso_promedio_g', 'biomasa_kg', 'observaciones',
    ];

    protected function casts(): array
    {
        return ['fecha' => 'date'];
    }

    public function siembraOrigen(): BelongsTo
    {
        return $this->belongsTo(Siembra::class, 'siembra_origen_id');
    }

    public function siembraDestino(): BelongsTo
    {
        return $this->belongsTo(Siembra::class, 'siembra_destino_id');
    }

    public function lagoOrigen(): BelongsTo
    {
        return $this->belongsTo(Lago::class, 'lago_origen_id');
    }

    public function lagoDestino(): BelongsTo
    {
        return $this->belongsTo(Lago::class, 'lago_destino_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
