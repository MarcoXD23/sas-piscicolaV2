<?php

namespace App\Models;

use App\Models\Concerns\PerteneceATenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cosecha extends Model
{
    use PerteneceATenant;

    protected $fillable = ['siembra_id', 'lago_id', 'user_id', 'despachado_por_id', 'destino', 'fecha', 'peces', 'kg', 'peso_promedio_g', 'observaciones', 'jornada_id',
        'limpio_kg', 'peces_esperados', 'peso_esperado_g', 'peso_medido_g', 'cerro_lote'];

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'kg' => 'float',
            'peso_promedio_g' => 'float',
            'limpio_kg' => 'float',
            'peces_esperados' => 'integer',
            'peso_esperado_g' => 'float',
            'peso_medido_g' => 'float',
            'cerro_lote' => 'boolean',
        ];
    }

    /**
     * Esperado contra real (docs/diseno/dia-de-proceso.md, 15.5). Esperado: lo que decía el registro justo antes de pescar.
     * - Peso promedio: el real es el medido al pescar; en la cosecha manual, kg ÷ peces contados. En la jornada sin
     *   muestra no hay peso real (los peces salieron de kg ÷ el peso del registro).
     * - Población y kg: solo si la cosecha cerró el lote. La población real de una jornada es kg ÷ peso (el medido o el
     *   del registro); la diferencia con lo esperado es «mortalidad no registrada».
     * Null en las cosechas anteriores a F6b, que no guardaron lo esperado.
     *
     * @return ?array{peso_esperado: float, peso_real: ?float, peces_esperados: ?int, peces_reales: ?int, kg_esperados: ?float, kg_reales: ?float, mortalidad_no_registrada: ?int}
     */
    public function esperadoContraReal(): ?array
    {
        if ($this->peces_esperados === null || $this->peso_esperado_g === null) {
            return null;
        }

        $pesoReal = $this->peso_medido_g ?? ($this->esDeJornada() ? null : $this->peso_promedio_g);
        $cerro = (bool) $this->cerro_lote;
        $pecesReales = null;
        if ($cerro) {
            $peso = $this->peso_medido_g ?? $this->peso_esperado_g;
            $pecesReales = $this->esDeJornada() ? ($peso > 0 ? (int) round($this->kg * 1000 / $peso) : null) : (int) $this->peces;
        }

        return [
            'peso_esperado' => $this->peso_esperado_g,
            'peso_real' => $pesoReal,
            'peces_esperados' => $cerro ? $this->peces_esperados : null,
            'peces_reales' => $pecesReales,
            'kg_esperados' => $cerro ? round($this->peces_esperados * $this->peso_esperado_g / 1000, 2) : null,
            'kg_reales' => $cerro ? $this->kg : null,
            'mortalidad_no_registrada' => $cerro && $pecesReales !== null ? $this->peces_esperados - $pecesReales : null,
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
