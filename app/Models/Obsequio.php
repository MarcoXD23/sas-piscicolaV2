<?php

namespace App\Models;

use App\Models\Concerns\PerteneceATenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pescado regalado en una jornada (docs/diseno/dia-de-proceso.md, tema 9): especie, presentación opcional, kilos, a
 * quién, quién lo registró y quién lo autorizó. Quién puede autorizar lo decide el ajuste obsequios.autoriza; nadie se
 * autoriza a sí mismo (también un CHECK en la base). En el cuadre (F6d) es una salida sin dinero.
 */
class Obsequio extends Model
{
    use PerteneceATenant;

    protected $fillable = ['jornada_id', 'especie_id', 'presentacion_id', 'kg', 'destinatario', 'nota', 'registrado_por', 'autorizado_por'];

    protected function casts(): array
    {
        return ['kg' => 'float'];
    }

    public function jornada(): BelongsTo
    {
        return $this->belongsTo(Jornada::class);
    }

    public function especie(): BelongsTo
    {
        return $this->belongsTo(Especie::class);
    }

    public function presentacion(): BelongsTo
    {
        return $this->belongsTo(Presentacion::class);
    }

    public function registradoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registrado_por');
    }

    public function autorizadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'autorizado_por');
    }
}
