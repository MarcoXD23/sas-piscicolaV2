<?php

namespace App\Support;

use App\Models\Cosecha;
use App\Models\Lago;
use App\Models\MedicionAgua;
use App\Models\Mortalidad;
use App\Models\MovimientoAlimento;
use App\Models\Muestreo;
use App\Models\Siembra;
use App\Models\Traslado;
use App\Models\Tratamiento;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Línea de tiempo de un lago: siembras, muestreos, mortalidad, alimentación, traslados, agua y sanidad.
 * La usan el detalle del lago (últimos registros) y el libro de campo (todo un período).
 */
class HistorialLago
{
    /**
     * @param  ?int  $limitePorTipo  Máximo de registros por tipo (null = todos los del período)
     * @return Collection<int, array{fecha: CarbonInterface, orden: string, icono: string, actividad: string, titulo: string, detalle: string, responsable: ?string, tono: string}>
     */
    public static function eventos(Lago $lago, ?CarbonInterface $desde = null, ?CarbonInterface $hasta = null, ?int $limitePorTipo = null): Collection
    {
        $numero = fn ($valor, $decimales = 0) => Formato::numero((float) $valor, $decimales);
        $t = fn (string $clave, array $datos = []) => __('historial.'.$clave, $datos);
        $unir = fn (?string ...$partes) => implode(' · ', array_filter($partes, fn ($parte) => $parte !== null && $parte !== ''));
        $siembras = $lago->siembras()->get()->keyBy('id');
        $ids = $siembras->keys();
        $eventos = collect();
        $agregar = function ($fecha, $orden, string $icono, string $actividad, string $titulo, string $detalle, ?string $responsable, string $tono = 'neutro') use ($eventos) {
            $eventos->push(compact('fecha', 'icono', 'actividad', 'titulo', 'detalle', 'responsable', 'tono') + ['orden' => (string) $orden]);
        };
        // Filtra por período y limita por tipo. Las columnas de solo fecha se comparan con texto Y-m-d;
        // las de fecha y hora (con $conHora) con el inicio del día en la zona de la finca (se pasa a UTC al consultar).
        $filtrar = function ($consulta, string $columna, bool $conHora = false) use ($desde, $hasta, $limitePorTipo) {
            $inicio = fn (CarbonInterface $dia) => $conHora
                ? CarbonImmutable::parse($dia->toDateString(), Region::zona())
                : $dia->toDateString();

            return $consulta
                ->when($desde, fn ($q) => $q->where($columna, '>=', $inicio($desde)))
                ->when($hasta, fn ($q) => $q->where($columna, '<', $inicio($hasta->copy()->addDay())))
                ->latest($columna)
                ->when($limitePorTipo, fn ($q) => $q->limit($limitePorTipo))
                ->get();
        };
        // Se compara el día de calendario (las fechas con hora ya vienen en la zona de la finca)
        $enPeriodo = fn (?CarbonInterface $fecha) => $fecha !== null
            && ($desde === null || $fecha->toDateString() >= $desde->toDateString())
            && ($hasta === null || $fecha->toDateString() <= $hasta->toDateString());

        foreach ($siembras as $siembra) {
            /** @var Siembra $siembra */
            if ($enPeriodo($siembra->fecha)) {
                $agregar($siembra->fecha, $siembra->created_at, 'pez', $t('actividades.siembra'), $t('siembra', ['especie' => $siembra->nombre_especie]),
                    $unir($t('peces_de_gramos', ['peces' => $numero($siembra->cantidad_alevinos), 'gramos' => $numero($siembra->peso_promedio_inicial_g, 2)]),
                        $siembra->proveedor_alevinos ? $t('proveedor', ['proveedor' => $siembra->proveedor_alevinos]) : null,
                        $siembra->lote_alevinos ? $t('lote', ['lote' => $siembra->lote_alevinos]) : null),
                    null, 'positivo');
            }
            if ($enPeriodo($siembra->finalizada_at)) {
                $agregar($siembra->finalizada_at, $siembra->finalizada_at, 'ok', $t('actividades.fin_siembra'), $t('fin_siembra', ['especie' => $siembra->nombre_especie]),
                    $t('peces_al_cierre', ['peces' => $numero($siembra->peces_vivos)]), null);
            }
        }

        $filtrar(Muestreo::whereIn('siembra_id', $ids)->with('usuario'), 'fecha')
            ->each(fn ($m) => $agregar($m->fecha, $m->created_at, 'biomasa', $t('actividades.muestreo'), $t('muestreo', ['especie' => $siembras[$m->siembra_id]->nombre_especie]),
                $t('detalle_muestreo', ['peces' => $numero($m->peces_pesados), 'gramos' => $numero($m->peso_promedio_g, 2)]), $m->usuario?->name));

        $filtrar(Mortalidad::whereIn('siembra_id', $ids)->with('usuario'), 'fecha')
            ->each(fn ($m) => $agregar($m->fecha, $m->created_at, 'mortalidad', $t('actividades.mortalidad'), $t('mortalidad', ['especie' => $siembras[$m->siembra_id]->nombre_especie]),
                $t('peces', ['peces' => $numero($m->cantidad)]), $m->usuario?->name, 'alerta'));

        $filtrar(MovimientoAlimento::where('lago_id', $lago->id)->where('tipo_movimiento', 'salida')->with('usuario'), 'fecha')
            ->each(fn ($m) => $agregar($m->fecha, $m->created_at, 'alimentar', $t('actividades.alimentacion'), $t('actividades.alimentacion'),
                $t('detalle_alimentacion', ['kg' => $numero($m->cantidad_kg, 1), 'tipo' => $m->tipo_concentrado]), $m->usuario?->name));

        $filtrar(Traslado::where(fn ($q) => $q->where('lago_origen_id', $lago->id)->orWhere('lago_destino_id', $lago->id))
            ->with(['lagoOrigen', 'lagoDestino', 'siembraOrigen', 'usuario']), 'fecha')
            ->each(function (Traslado $traslado) use ($agregar, $lago, $numero, $t) {
                $sale = $traslado->lago_origen_id === $lago->id;
                $agregar($traslado->fecha, $traslado->created_at, 'lagos', $t('actividades.traslado'),
                    $sale ? $t('traslado_hacia', ['lago' => $traslado->lagoDestino->nombre]) : $t('traslado_desde', ['lago' => $traslado->lagoOrigen->nombre]),
                    $t('detalle_traslado', ['peces' => $numero($traslado->cantidad), 'especie' => $traslado->siembraOrigen->nombre_especie, 'kg' => $numero($traslado->biomasa_kg, 2)]), $traslado->usuario?->name);
            });

        $filtrar(Cosecha::where('lago_id', $lago->id)->with('usuario')->withCount('pesajes'), 'fecha')
            ->each(fn (Cosecha $c) => $agregar($c->fecha, $c->created_at, 'pez', $t('actividades.cosecha'), $t('cosecha', ['especie' => $siembras[$c->siembra_id]->nombre_especie]),
                $t($c->pesajes_count > 0 ? 'detalle_cosecha_canastas' : 'detalle_cosecha', ['peces' => $numero($c->peces), 'kg' => $numero($c->kg, 2), 'gramos' => $numero($c->peso_promedio_g), 'canastas' => $c->pesajes_count]),
                $c->usuario?->name, 'positivo'));

        $filtrar(MedicionAgua::where('lago_id', $lago->id)->with('usuario'), 'medido_at', conHora: true)
            ->each(fn (MedicionAgua $m) => $agregar($m->medido_at, $m->medido_at, 'reloj', $t('actividades.calidad_agua'), $t('calidad_agua', ['hora' => $m->medido_at->format('H:i')]),
                $unir(
                    $m->temperatura_c !== null ? "{$numero($m->temperatura_c, 1)} °C" : null,
                    $m->oxigeno_mg_l !== null ? $t('oxigeno', ['valor' => $numero($m->oxigeno_mg_l, 2)]) : null,
                    $m->ph !== null ? $t('ph', ['valor' => $numero($m->ph, 2)]) : null,
                    $m->amonio_mg_l !== null ? $t('amonio', ['valor' => $numero($m->amonio_mg_l, 2)]) : null,
                    $m->nitritos_mg_l !== null ? $t('nitritos', ['valor' => $numero($m->nitritos_mg_l, 2)]) : null,
                    $m->transparencia_cm !== null ? $t('transparencia', ['valor' => $m->transparencia_cm]) : null,
                ), $m->usuario?->name));

        $filtrar(Tratamiento::where('lago_id', $lago->id)->with('usuario'), 'fecha_inicio')
            ->each(fn (Tratamiento $tratamiento) => $agregar($tratamiento->fecha_inicio, $tratamiento->created_at, 'alerta', $t('actividades.tratamiento'), $t('tratamiento', ['motivo' => $tratamiento->motivo]),
                $unir($t('producto', ['producto' => $tratamiento->producto, 'dosis' => $tratamiento->dosis, 'via' => __('lagos.vias.'.$tratamiento->via)]),
                    $tratamiento->registro_sanitario ? __('region.registro_sanitario_detalle', ['numero' => $tratamiento->registro_sanitario]) : null,
                    $t('hasta_el', ['fecha' => $tratamiento->fecha_fin->format('d/m/Y')]),
                    $tratamiento->dias_retiro > 0 ? $t('retiro_hasta', ['fecha' => $tratamiento->finRetiro()->format('d/m/Y')]) : null),
                $tratamiento->usuario?->name, 'alerta'));

        return $eventos->sortByDesc(fn ($e) => $e['fecha']->format('Y-m-d').' '.$e['orden'])->values();
    }
}
