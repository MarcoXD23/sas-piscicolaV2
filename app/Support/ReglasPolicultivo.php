<?php

namespace App\Support;

use App\Models\Especie;
use App\Models\Siembra;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Reglas para tener varias especies en un mismo lago. Se usan al sembrar y al trasladar peces.
 * - Varias especies activas a la vez; la MISMA especie dos veces solo como lotes de fecha distinta.
 * - Agua fría con agua cálida: ADVERTENCIA que la persona confirma (no un bloqueo).
 */
class ReglasPolicultivo
{
    public const CAMPO_CONFIRMACION = 'confirmar_clima';

    /**
     * @param  Collection<int, Siembra>  $activas  Siembras activas del lago
     *
     * @throws ValidationException si mezcla agua fría y cálida sin la confirmación de la persona
     */
    public static function verificarClima(Collection $activas, Especie $especie, bool $confirmado): void
    {
        $distintas = $activas->filter(fn (Siembra $siembra) => $siembra->especie && $siembra->especie->clima !== $especie->clima);

        if ($distintas->isNotEmpty() && ! $confirmado) {
            throw ValidationException::withMessages([
                self::CAMPO_CONFIRMACION => __('mensajes.siembra.advertencia_clima', [
                    'especie' => $especie->nombre,
                    'clima' => __($especie->esFria() ? 'especies.agua_fria' : 'especies.agua_calida'),
                    'otras' => $distintas->pluck('nombre_especie')->unique()->join(', '),
                ]),
            ]);
        }
    }

    /**
     * @param  Collection<int, Siembra>  $activas
     *
     * @throws ValidationException si ya hay un lote activo de esa especie con la misma fecha de siembra
     */
    public static function verificarLote(Collection $activas, Especie $especie, string $fecha, string $campo): void
    {
        $repetido = $activas->contains(fn (Siembra $siembra) => $siembra->especie_id === $especie->id && $siembra->fecha->toDateString() === $fecha);

        if ($repetido) {
            throw ValidationException::withMessages([
                $campo => __('mensajes.siembra.lote_repetido', ['especie' => $especie->nombre, 'fecha' => CarbonImmutable::parse($fecha)->format('d/m/Y')]),
            ]);
        }
    }
}
