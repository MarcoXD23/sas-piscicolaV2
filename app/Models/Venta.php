<?php

namespace App\Models;

use App\Models\Concerns\PerteneceATenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Venta extends Model
{
    use PerteneceATenant;

    public const FORMAS_PAGO = ['contado', 'credito'];

    /** @return array<string, string> código => texto en el idioma de la finca */
    public static function opciones(): array
    {
        return collect(self::FORMAS_PAGO)->mapWithKeys(fn (string $codigo) => [$codigo => __('ventas.formas_pago.'.$codigo)])->all();
    }

    protected $fillable = ['cosecha_id', 'user_id', 'comprador_id', 'cliente', 'fecha', 'kg', 'precio_kg', 'total', 'forma_pago', 'saldo'];

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'kg' => 'float',
            'precio_kg' => 'integer',
            'total' => 'integer',
            'saldo' => 'integer',
        ];
    }

    public function cosecha(): BelongsTo
    {
        return $this->belongsTo(Cosecha::class);
    }

    public function comprador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'comprador_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function abonos(): HasMany
    {
        return $this->hasMany(Abono::class);
    }

    /** Créditos con saldo por cobrar. */
    public function scopeConSaldo(Builder $consulta): void
    {
        $consulta->where('saldo', '>', 0);
    }

    public function esCredito(): bool
    {
        return $this->forma_pago === 'credito';
    }
}
