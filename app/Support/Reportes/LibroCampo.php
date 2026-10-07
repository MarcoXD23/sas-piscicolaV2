<?php

namespace App\Support\Reportes;

use App\Models\Lago;
use App\Support\HistorialLago;
use Carbon\CarbonImmutable;

/**
 * Datos del libro de campo de un lago en un período (días de la finca). Los usan la pantalla, el Excel y el PDF.
 */
class LibroCampo
{
    /** @return array<string, mixed> */
    public static function datos(Lago $lago, CarbonImmutable $desde, CarbonImmutable $hasta): array
    {
        return [
            'lago' => $lago,
            'desde' => $desde,
            'hasta' => $hasta,
            'siembras' => $lago->siembras()
                ->where('fecha', '<=', $hasta->toDateString())
                ->where(fn ($q) => $q->whereNull('finalizada_at')->orWhere('finalizada_at', '>=', $desde))
                ->orderBy('fecha')->get(),
            // Cosechas del lago en el período, con sus pesadas
            'cosechas' => $lago->cosechas()->with(['lago', 'siembra', 'despachadoPor', 'pesajes'])
                ->whereBetween('fecha', [$desde->toDateString(), $hasta->toDateString()])->orderBy('fecha')->get(),
            // El libro va en orden cronológico (del más antiguo al más reciente)
            'registros' => HistorialLago::eventos($lago, $desde, $hasta)->reverse()->values(),
        ];
    }

    /**
     * @param  array<string, mixed>  $d  Resultado de datos()
     * @return list<Tabla>
     */
    public static function tablas(array $d): array
    {
        $t = fn (string $clave) => __('reportes.exportar.'.$clave);

        return [
            new Tabla($t('hoja_siembras'),
                [[$t('especie'), null], [$t('fecha'), null], [$t('peces'), 0], [$t('peso_g'), 2], [$t('proveedor'), null], [$t('lote'), null], [$t('estado'), null]],
                $d['siembras']->map(fn ($s) => [
                    $s->nombre_especie, $s->fecha->format('d/m/Y'), (int) $s->cantidad_alevinos, (float) $s->peso_promedio_inicial_g,
                    $s->proveedor_alevinos ?: __('reportes.sin_registrar'), $s->lote_alevinos ?: __('reportes.sin_registrar'),
                    $s->estaActiva() ? __('reportes.activa') : __('reportes.finalizada_el', ['fecha' => $s->finalizada_at->format('d/m/Y')]),
                ])->all()),
            new Tabla($t('hoja_registro'),
                [[$t('fecha'), null], [$t('actividad'), null], [$t('detalle'), null], [$t('responsable'), null]],
                $d['registros']->map(fn ($r) => [$r['fecha']->format('d/m/Y'), $r['titulo'], $r['detalle'], $r['responsable'] ?? ''])->all()),
            TablasCosecha::cosechas($d['cosechas']),
            TablasCosecha::pesadas($d['cosechas']),
        ];
    }
}
