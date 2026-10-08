<?php

namespace App\Models;

use App\Casts\InstanteLocal;
use App\Models\Concerns\PerteneceATenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Paquete ZIP con los datos de la finca, pedido por el propietario. Se genera en segundo plano y vence a las 24 horas.
 */
class Exportacion extends Model
{
    use PerteneceATenant;

    public const PENDIENTE = 'pendiente';

    public const LISTA = 'lista';

    public const FALLO = 'fallo';

    protected $table = 'exportaciones';

    protected $fillable = ['user_id', 'estado', 'ruta', 'bytes', 'vence_at'];

    protected $attributes = ['estado' => self::PENDIENTE];

    protected function casts(): array
    {
        return [
            'bytes' => 'integer',
            'vence_at' => InstanteLocal::class,
        ];
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function descargable(): bool
    {
        return $this->estado === self::LISTA && $this->vence_at !== null && $this->vence_at->isFuture();
    }
}
