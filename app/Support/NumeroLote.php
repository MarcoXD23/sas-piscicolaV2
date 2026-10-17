<?php

namespace App\Support;

use App\Models\Siembra;
use App\Support\Ajustes\AjustesFinca;
use App\Support\Ajustes\CatalogoAjustes;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Número de lote de la finca para cada siembra, según el ajuste «lotes.numeracion»:
 * - manual (por defecto): lo escribe el administrador al sembrar, o queda vacío;
 * - automática: la app lo genera con el año de la siembra y un consecutivo de la finca en ese año («2026-001»).
 * El número no es único: el lote aparte que nace de un traslado conserva el número del lote de origen.
 */
final class NumeroLote
{
    public static function esAutomatica(): bool
    {
        return AjustesFinca::valor(CatalogoAjustes::LOTES_NUMERACION) === CatalogoAjustes::NUMERACION_AUTOMATICA;
    }

    /**
     * Siguiente número del año de la siembra. Llamar dentro de una transacción: bloquea la fila de la finca para que
     * dos siembras al mismo tiempo no reciban el mismo consecutivo.
     */
    public static function siguiente(CarbonInterface $fecha): string
    {
        $tenantId = app(TenantActual::class)->id();
        DB::table('tenants')->where('id', $tenantId)->lockForUpdate()->first();

        $anio = $fecha->format('Y');
        // Solo los números con la forma automática de ese año («2026-007»); los escritos a mano con otra forma no cuentan
        $mayor = Siembra::query()
            ->where('numero_lote', '~', '^'.$anio.'-[0-9]+$')
            ->selectRaw("MAX(CAST(split_part(numero_lote, '-', 2) AS INTEGER)) AS mayor")
            ->value('mayor');

        return sprintf('%s-%03d', $anio, ((int) $mayor) + 1);
    }
}
