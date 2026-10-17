<?php

namespace App\Models;

use App\Casts\InstanteLocal;
use App\Models\Concerns\PerteneceATenant;
use App\Support\Region;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

/**
 * Jornada de proceso: un día en que se pescan uno o varios lagos (docs/diseno/dia-de-proceso.md, tema 2).
 * - Abierta: se agregan lagos y pesajes enteros. Al cerrarla, App\Support\Proceso\CierreJornada crea las cosechas.
 * - Cerrada: no se reabre. Cancelada: conserva sus pesajes y no crea cosechas.
 * - Una sola jornada activa (no cancelada) por finca y fecha (índice único parcial en la base).
 * Solo existe si la finca encendió el ajuste proceso.jornadas; la cosecha manual sigue igual para las demás.
 */
class Jornada extends Model
{
    use PerteneceATenant;

    public const ABIERTA = 'abierta';

    public const CERRADA = 'cerrada';

    public const CANCELADA = 'cancelada';

    public const ESTADOS = [self::ABIERTA, self::CERRADA, self::CANCELADA];

    protected $fillable = ['fecha', 'estado', 'sin_alimentacion', 'observaciones', 'user_id', 'cerrada_at', 'cerrada_por', 'cancelada_at', 'cancelada_por'];

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'sin_alimentacion' => 'boolean',
            'cerrada_at' => InstanteLocal::class,
            'cancelada_at' => InstanteLocal::class,
        ];
    }

    public function scopeActivas(Builder $consulta): void
    {
        $consulta->where('estado', '<>', self::CANCELADA);
    }

    public function estaAbierta(): bool
    {
        return $this->estado === self::ABIERTA;
    }

    /** Ya llegó su fecha (en la zona de la finca): se puede pesar y cerrar. */
    public function yaEsSuDia(): bool
    {
        return $this->fecha->toDateString() <= Region::hoy()->toDateString();
    }

    public function lagos(): HasMany
    {
        return $this->hasMany(JornadaLago::class)->orderBy('orden')->orderBy('id');
    }

    public function cosechas(): HasMany
    {
        return $this->hasMany(Cosecha::class);
    }

    /** Pesadas del limpio por presentación (F6b). */
    public function pesajesLimpio(): HasMany
    {
        return $this->hasMany(PesajeLimpio::class)->orderBy('id');
    }

    /** Ventas rápidas de la jornada (F6c), por especie. */
    public function ventas(): HasMany
    {
        return $this->hasMany(Venta::class)->orderBy('id');
    }

    public function obsequios(): HasMany
    {
        return $this->hasMany(Obsequio::class)->orderBy('id');
    }

    /**
     * Bloquea la jornada para registrar en ella (pesajes, ventas, obsequios); si ya no está abierta, no deja seguir.
     * Va dentro de una transacción.
     */
    public static function bloquearAbierta(int $id): self
    {
        $bloqueada = self::query()->whereKey($id)->lockForUpdate()->firstOrFail();
        if (! $bloqueada->estaAbierta()) {
            throw ValidationException::withMessages(['jornada' => __('jornadas.ya_no_abierta')]);
        }

        return $bloqueada;
    }

    /** Rendimiento por especie, guardado al cerrar (F6b). */
    public function rendimientos(): HasMany
    {
        return $this->hasMany(JornadaRendimiento::class);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function cerradaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cerrada_por');
    }

    public function canceladaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelada_por');
    }
}
