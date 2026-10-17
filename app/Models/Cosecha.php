<?php

namespace App\Models;

use App\Models\Concerns\PerteneceATenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cosecha extends Model
{
    use PerteneceATenant;

    protected $fillable = ['siembra_id', 'lago_id', 'user_id', 'despachado_por_id', 'destino', 'fecha', 'peces', 'kg', 'peso_promedio_g', 'observaciones', 'jornada_id'];

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'kg' => 'float',
            'peso_promedio_g' => 'float',
        ];
    }

    public function siembra(): BelongsTo
    {
        return $this->belongsTo(Siembra::class);
    }

    public function lago(): BelongsTo
    {
        return $this->belongsTo(Lago::class);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** Quién despachó el pedido (por defecto, quien registró la cosecha). */
    public function despachadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'despachado_por_id');
    }

    /** Jornada de proceso que creó esta cosecha al cerrarse (null en la cosecha manual). */
    public function jornada(): BelongsTo
    {
        return $this->belongsTo(Jornada::class);
    }

    public function esDeJornada(): bool
    {
        return $this->jornada_id !== null;
    }

    /**
     * Destino para mostrar: el de la cosecha manual, o «Jornada de proceso dd/mm» si la creó una jornada. Ese texto no
     * se guarda en la base: sale de lang/es/jornadas.php (la cosecha tiene la misma fecha que su jornada).
     */
    public function textoDestino(): string
    {
        return $this->esDeJornada()
            ? __('jornadas.destino_cosecha', ['fecha' => $this->fecha->format('d/m')])
            : ($this->destino ?: __('cosechas.sin_dato'));
    }

    public function ventas(): HasMany
    {
        return $this->hasMany(Venta::class);
    }

    /** Pesadas de la cosecha (vacío si se registró con el total entero). */
    public function pesajes(): HasMany
    {
        return $this->hasMany(PesajeCosecha::class)->orderBy('numero');
    }

    /** Kg de esta cosecha que aún no se han vendido. Usa ventas_sum_kg si viene precargado. */
    public function kgDisponibles(): float
    {
        $vendidos = $this->ventas_sum_kg ?? $this->ventas()->sum('kg');

        return round($this->kg - (float) $vendidos, 2);
    }
}
