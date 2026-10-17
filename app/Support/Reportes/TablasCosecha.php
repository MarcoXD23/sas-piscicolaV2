<?php

namespace App\Support\Reportes;

use App\Models\Cosecha;
use App\Support\CalculoPesaje;
use Illuminate\Support\Collection;

/**
 * Cosechas y sus pesadas para el informe mensual y el libro de campo (pantalla, Excel y PDF),
 * con las mismas palabras de la pantalla de la cosecha y el total entero como kg de la cosecha.
 */
final class TablasCosecha
{
    /** @param  Collection<int, Cosecha>  $cosechas  con lago, siembra, despachadoPor y pesajes cargados */
    public static function cosechas(Collection $cosechas): Tabla
    {
        $t = fn (string $clave) => __('reportes.exportar.'.$clave);

        return new Tabla($t('hoja_cosechas'), [
            [$t('fecha'), null], [$t('lago'), null], [$t('especie'), null], [$t('destino_pedido'), null], [$t('despacho'), null],
            [$t('peces'), 0], [$t('pesadas'), 0], [$t('total_pesado'), 2], [$t('total_descuento'), 2], [$t('kg_enteros'), 2],
        ], $cosechas->map(function (Cosecha $c) {
            $totales = $c->pesajes->isNotEmpty() ? CalculoPesaje::deCosecha($c)['totales'] : null;

            return [
                $c->fecha->format('d/m/Y'), $c->lago->nombre, $c->siembra->nombre_especie,
                $c->textoDestino(), $c->despachadoPor?->name ?? __('cosechas.sin_dato'),
                (int) $c->peces, $totales['pesadas'] ?? 0,
                $totales['pesado'] ?? null, $totales['descuento'] ?? null, (float) $c->kg,
            ];
        })->all());
    }

    /** Una fila por pesada y una fila de totales (con la cuenta en números) por cosecha. */
    public static function pesadas(Collection $cosechas): Tabla
    {
        $t = fn (string $clave) => __('reportes.exportar.'.$clave);
        $filas = [];

        foreach ($cosechas->filter(fn (Cosecha $c) => $c->pesajes->isNotEmpty()) as $c) {
            $pesaje = CalculoPesaje::deCosecha($c);
            $encabezado = [$c->fecha->format('d/m/Y'), $c->lago->nombre, $c->textoDestino()];

            foreach ($pesaje['filas'] as $fila) {
                $filas[] = [...$encabezado, (string) $fila['numero'], __('cosechas.tipos.'.$fila['tipo']), $fila['cantidad'],
                    $fila['peso_vacia'], $fila['peso_pesado'], $fila['descuento'], $fila['entero'], ''];
            }

            $totales = $pesaje['totales'];
            $filas[] = [...$encabezado, $t('total'), __('cosechas.cantidad_por_tipo', ['canastas' => $totales['por_tipo']['canasta'], 'canastillas' => $totales['por_tipo']['canastilla']]),
                $totales['cantidad'], null, $totales['pesado'], $totales['descuento'], $totales['entero'], CalculoPesaje::lineaCuenta($totales)];
        }

        return new Tabla($t('hoja_pesadas'), [
            [$t('fecha'), null], [$t('lago'), null], [$t('destino_pedido'), null], [$t('pesada'), null], [$t('tipo'), null], [$t('cantidad'), 0],
            [$t('peso_vacia'), 2], [$t('peso_pesado'), 2], [$t('descuento'), 2], [$t('peso_entero'), 2], [$t('cuenta'), null],
        ], $filas);
    }
}
