<?php

namespace App\Support;

use App\Models\Especie;

/**
 * Rango de agua común a las especies de un lago (policultivo):
 * - temperatura: de la mínima más alta a la máxima más baja;
 * - oxígeno: el mínimo más exigente (el más alto);
 * - pH: de la mínima más alta a la máxima más baja.
 * Nunca inventa valores: una especie sin un rango no cuenta para ese parámetro (y se avisa); si ninguna lo tiene,
 * ese parámetro no se alerta (y se avisa). Si los rangos no se cruzan (por ejemplo, frío con cálido), tampoco.
 */
final class RangoAgua
{
    /**
     * @param  iterable<Especie|null>  $especies
     * @return array{temperatura: array{0: float, 1: float}|null, oxigeno: float|null, ph: array{0: float, 1: float}|null, especies: string, avisos: list<string>}
     */
    public static function para(iterable $especies): array
    {
        $especies = collect($especies)->filter()->unique('id')->values();
        $avisos = [];

        if ($especies->isEmpty()) {
            return ['temperatura' => null, 'oxigeno' => null, 'ph' => null, 'especies' => '', 'avisos' => []];
        }

        foreach ($especies->reject(fn (Especie $e) => $e->tieneRangos()) as $sinRangos) {
            $avisos[] = __('lagos.rango.sin_rangos_especie', ['especie' => $sinRangos->nombre]);
        }

        $comun = function (string $parametro, ?callable $rango) use ($especies, &$avisos) {
            $rangos = $especies->map($rango)->filter()->values();
            if ($rangos->isEmpty()) {
                $avisos[] = __('lagos.rango.ninguna_tiene', ['parametro' => __('lagos.rango.parametros.'.$parametro)]);

                return null;
            }
            $minimo = $rangos->max(fn (array $r) => $r[0]);
            $maximo = $rangos->min(fn (array $r) => $r[1]);
            if ($minimo > $maximo) {
                $avisos[] = __('lagos.rango.sin_rango_comun', ['parametro' => __('lagos.rango.parametros.'.$parametro)]);

                return null;
            }

            return [$minimo, $maximo];
        };

        $temperatura = $comun('temperatura', fn (Especie $e) => $e->rangoTemperatura());
        $ph = $comun('ph', fn (Especie $e) => $e->rangoPh());

        $oxigenos = $especies->pluck('oxigeno_min')->filter(fn ($valor) => $valor !== null);
        if ($oxigenos->isEmpty()) {
            $avisos[] = __('lagos.rango.ninguna_tiene', ['parametro' => __('lagos.rango.parametros.oxigeno')]);
        }

        return [
            'temperatura' => $temperatura,
            'oxigeno' => $oxigenos->isEmpty() ? null : (float) $oxigenos->max(),
            'ph' => $ph,
            // Las especies que dan el rango (las que tienen al menos un rango)
            'especies' => $especies->filter(fn (Especie $e) => $e->rangoTemperatura() || $e->oxigeno_min !== null || $e->rangoPh())
                ->pluck('nombre')->join(', ', ' '.__('comun.y').' '),
            'avisos' => array_values(array_unique($avisos)),
        ];
    }
}
