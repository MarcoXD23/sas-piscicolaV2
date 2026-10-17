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

    protected $fillable = ['cosecha_id', 'user_id', 'comprador_id', 'tercero_id', 'cliente', 'fecha', 'kg', 'precio_kg', 'total', 'forma_pago', 'saldo',
        'jornada_id', 'especie_id', 'kg_cupo', 'precio_cupo', 'recibio_id'];

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'kg' => 'float',
            'precio_kg' => 'integer',
            'total' => 'integer',
            'saldo' => 'integer',
            'kg_cupo' => 'float',
            'precio_cupo' => 'integer',
        ];
    }

    /** Venta vieja (o de una finca sin jornadas): sale de una cosecha. Las de jornada no tienen cosecha. */
    public function cosecha(): BelongsTo
    {
        return $this->belongsTo(Cosecha::class);
    }

    /** Venta rápida de una jornada (F6c), por especie. */
    public function jornada(): BelongsTo
    {
        return $this->belongsTo(Jornada::class);
    }

    public function especie(): BelongsTo
    {
        return $this->belongsTo(Especie::class);
    }

    /** Quién recibió el dinero de una venta de contado en la jornada (para cuadrar la caja del día). */
    public function recibio(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recibio_id');
    }

    /** De dónde salió el pescado: el lago de la cosecha, o la jornada y la especie (necesita cosecha.lago o especie). */
    public function textoPescado(): string
    {
        return $this->cosecha_id !== null
            ? __('lagos.lago_nombre', ['lago' => $this->cosecha?->lago?->nombre])
            : __('ventas.de_jornada', ['fecha' => $this->fecha->format('d/m'), 'especie' => $this->especie?->nombre]);
    }

    /** Kg vendidos fuera del cupo (al precio general). */
    public function kgExceso(): float
    {
        return round($this->kg - (float) ($this->kg_cupo ?? 0), 2);
    }

    public function comprador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'comprador_id');
    }

    /** Comprador sin cuenta (jornalero). */
    public function tercero(): BelongsTo
    {
        return $this->belongsTo(Tercero::class);
    }

    /** Nombre del comprador: el de hoy si es un jornalero (o «Jornalero N» si se anonimizó); si no, el guardado. */
    public function nombreCliente(): string
    {
        return $this->tercero_id !== null && $this->tercero ? $this->tercero->nombreCompleto() : $this->cliente;
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
