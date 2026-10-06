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
        $numero = fn ($valor, $decimales = 0) => number_format((float) $valor, $decimales, ',', '.');
        $unir = fn (?string ...$partes) => implode(' · ', array_filter($partes, fn ($parte) => $parte !== null && $parte !== ''));
        $siembras = $lago->siembras()->get()->keyBy('id');
        $ids = $siembras->keys();
        $eventos = collect();
        $agregar = function ($fecha, $orden, string $icono, string $actividad, string $titulo, string $detalle, ?string $responsable, string $tono = 'neutro') use ($eventos) {
            $eventos->push(compact('fecha', 'icono', 'actividad', 'titulo', 'detalle', 'responsable', 'tono') + ['orden' => (string) $orden]);
        };
        // Filtra por período (columna de fecha) y limita por tipo
        $filtrar = function ($consulta, string $columna) use ($desde, $hasta, $limitePorTipo) {
            return $consulta
                ->when($desde, fn ($q) => $q->where($columna, '>=', $desde->toDateString()))
                ->when($hasta, fn ($q) => $q->where($columna, '<', $hasta->copy()->addDay()->toDateString()))
                ->latest($columna)
                ->when($limitePorTipo, fn ($q) => $q->limit($limitePorTipo))
                ->get();
        };
        $enPeriodo = fn (?CarbonInterface $fecha) => $fecha !== null
            && ($desde === null || $fecha->gte($desde->copy()->startOfDay()))
            && ($hasta === null || $fecha->lte($hasta->copy()->endOfDay()));

        foreach ($siembras as $siembra) {
            /** @var Siembra $siembra */
            if ($enPeriodo($siembra->fecha)) {
                $agregar($siembra->fecha, $siembra->created_at, 'pez', 'Siembra', "Siembra de {$siembra->nombre_especie}",
                    $unir("{$numero($siembra->cantidad_alevinos)} peces de {$numero($siembra->peso_promedio_inicial_g, 2)} g",
                        $siembra->proveedor_alevinos ? "proveedor {$siembra->proveedor_alevinos}" : null,
                        $siembra->lote_alevinos ? "lote {$siembra->lote_alevinos}" : null),
                    null, 'positivo');
            }
            if ($enPeriodo($siembra->finalizada_at)) {
                $agregar($siembra->finalizada_at, $siembra->finalizada_at, 'ok', 'Fin de siembra', "Fin de la siembra de {$siembra->nombre_especie}",
                    "{$numero($siembra->peces_vivos)} peces al cierre", null);
            }
        }

        $filtrar(Muestreo::whereIn('siembra_id', $ids)->with('usuario'), 'fecha')
            ->each(fn ($m) => $agregar($m->fecha, $m->created_at, 'biomasa', 'Muestreo', 'Muestreo · '.$siembras[$m->siembra_id]->nombre_especie,
                "{$numero($m->peces_pesados)} peces, promedio {$numero($m->peso_promedio_g, 2)} g", $m->usuario?->name));

        $filtrar(Mortalidad::whereIn('siembra_id', $ids)->with('usuario'), 'fecha')
            ->each(fn ($m) => $agregar($m->fecha, $m->created_at, 'mortalidad', 'Mortalidad', 'Mortalidad · '.$siembras[$m->siembra_id]->nombre_especie,
                "{$numero($m->cantidad)} peces", $m->usuario?->name, 'alerta'));

        $filtrar(MovimientoAlimento::where('lago_id', $lago->id)->where('tipo_movimiento', 'salida')->with('usuario'), 'fecha')
            ->each(fn ($m) => $agregar($m->fecha, $m->created_at, 'alimentar', 'Alimentación', 'Alimentación',
                "{$numero($m->cantidad_kg, 1)} kg de {$m->tipo_concentrado}", $m->usuario?->name));

        $filtrar(Traslado::where(fn ($q) => $q->where('lago_origen_id', $lago->id)->orWhere('lago_destino_id', $lago->id))
            ->with(['lagoOrigen', 'lagoDestino', 'siembraOrigen', 'usuario']), 'fecha')
            ->each(function (Traslado $t) use ($agregar, $lago, $numero) {
                $sale = $t->lago_origen_id === $lago->id;
                $agregar($t->fecha, $t->created_at, 'lagos', 'Traslado',
                    $sale ? "Traslado hacia {$t->lagoDestino->nombre}" : "Traslado desde {$t->lagoOrigen->nombre}",
                    "{$numero($t->cantidad)} peces de {$t->siembraOrigen->nombre_especie} ({$numero($t->biomasa_kg, 2)} kg)", $t->usuario?->name);
            });

        $filtrar(Cosecha::where('lago_id', $lago->id)->with('usuario')->withCount('pesajes'), 'fecha')
            ->each(fn (Cosecha $c) => $agregar($c->fecha, $c->created_at, 'pez', 'Cosecha', 'Cosecha · '.$siembras[$c->siembra_id]->nombre_especie,
                "{$numero($c->peces)} peces, {$numero($c->kg, 2)} kg ({$numero($c->peso_promedio_g)} g en promedio)"
                .($c->pesajes_count > 0 ? " en {$c->pesajes_count} canastas" : ''), $c->usuario?->name, 'positivo'));

        $filtrar(MedicionAgua::where('lago_id', $lago->id)->with('usuario'), 'medido_at')
            ->each(fn (MedicionAgua $m) => $agregar($m->medido_at, $m->medido_at, 'reloj', 'Calidad del agua', 'Calidad del agua · '.$m->medido_at->format('H:i'),
                $unir(
                    $m->temperatura_c !== null ? "{$numero($m->temperatura_c, 1)} °C" : null,
                    $m->oxigeno_mg_l !== null ? "oxígeno {$numero($m->oxigeno_mg_l, 2)} mg/L" : null,
                    $m->ph !== null ? "pH {$numero($m->ph, 2)}" : null,
                    $m->amonio_mg_l !== null ? "amonio {$numero($m->amonio_mg_l, 2)} mg/L" : null,
                    $m->nitritos_mg_l !== null ? "nitritos {$numero($m->nitritos_mg_l, 2)} mg/L" : null,
                    $m->transparencia_cm !== null ? "transparencia {$m->transparencia_cm} cm" : null,
                ), $m->usuario?->name));

        $filtrar(Tratamiento::where('lago_id', $lago->id)->with('usuario'), 'fecha_inicio')
            ->each(fn (Tratamiento $t) => $agregar($t->fecha_inicio, $t->created_at, 'alerta', 'Tratamiento', "Tratamiento: {$t->motivo}",
                $unir("{$t->producto} ({$t->dosis}, vía {$t->via})",
                    $t->registro_ica ? "registro ICA {$t->registro_ica}" : null,
                    "hasta el {$t->fecha_fin->format('d/m/Y')}",
                    $t->dias_retiro > 0 ? "retiro hasta el {$t->finRetiro()->format('d/m/Y')}" : null),
                $t->usuario?->name, 'alerta'));

        return $eventos->sortByDesc(fn ($e) => $e['fecha']->format('Y-m-d').' '.$e['orden'])->values();
    }
}
