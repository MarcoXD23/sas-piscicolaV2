<?php

namespace App\Models;

use App\Casts\InstanteLocal;
use App\Models\Concerns\PerteneceATenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Aireacion extends Model
{
    use PerteneceATenant;

    protected $table = 'aireaciones';

    protected $fillable = ['lago_id', 'user_id', 'apagado_por', 'encendido_at', 'apagado_at', 'motivo'];

    protected function casts(): array
    {
        return [
            'encendido_at' => InstanteLocal::class,
            'apagado_at' => InstanteLocal::class,
        ];
    }

    public function lago(): BelongsTo
    {
        return $this->belongsTo(Lago::class);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function apagadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'apagado_por');
    }

    public function estaEncendido(): bool
    {
        return $this->apagado_at === null;
    }
}
