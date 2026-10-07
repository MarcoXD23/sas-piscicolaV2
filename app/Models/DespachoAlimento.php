<?php

namespace App\Models;

use App\Casts\InstanteLocal;
use App\Models\Concerns\PerteneceATenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Despacho de concentrado hacia la finca, registrado por el propietario (sin dinero).
 * El administrador lo recibe con una o varias recepciones (entradas en movimientos_alimento con este despacho_id).
 */
class DespachoAlimento extends Model
{
    use PerteneceATenant;

    public const PESO_BULTO_POR_DEFECTO = 40;

    public const EXACTO = 'exacto';

    public const FALTANTE = 'faltante';

    public const SOBRANTE = 'sobrante';

    public const SIN_RECEPCION = 'sin_recepcion';

    protected $table = 'despachos_alimento';

    protected $fillable = ['user_id', 'fecha', 'proveedor', 'tipo_concentrado', 'bultos', 'peso_bulto_kg', 'cantidad_kg', 'lote'];

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'cantidad_kg' => 'float',
            'peso_bulto_kg' => 'float',
            'revisado_at' => InstanteLocal::class,
        ];
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function recepciones(): HasMany
    {
        return $this->hasMany(MovimientoAlimento::class, 'despacho_id')->where('tipo_movimiento', 'entrada');
    }

    /** Kg recibidos contra este despacho (usa recepciones_sum_cantidad_kg si viene precargado). */
    public function kgRecibidos(): float
    {
        return (float) ($this->recepciones_sum_cantidad_kg ?? $this->recepciones()->sum('cantidad_kg'));
    }

    /** Recibido menos despachado: negativo = faltante, positivo = sobrante. */
    public function diferenciaKg(): float
    {
        return round($this->kgRecibidos() - (float) $this->cantidad_kg, 2);
    }

    public function estado(): string
    {
        if (! $this->tieneRecepciones()) {
            return self::SIN_RECEPCION;
        }

        return match (true) {
            $this->diferenciaKg() < 0 => self::FALTANTE,
            $this->diferenciaKg() > 0 => self::SOBRANTE,
            default => self::EXACTO,
        };
    }

    public function tieneRecepciones(): bool
    {
        return (int) ($this->recepciones_count ?? $this->recepciones()->count()) > 0;
    }
}
