<?php

namespace App\Support\Reportes;

use App\Models\Abono;
use App\Models\AvisoPesca;
use App\Models\Cosecha;
use App\Models\HoraExtra;
use App\Models\Lago;
use App\Models\MedicionAgua;
use App\Models\Mortalidad;
use App\Models\MovimientoAlimento;
use App\Models\Muestreo;
use App\Models\Rol;
use App\Models\Ronda;
use App\Models\Siembra;
use App\Models\Tarea;
use App\Models\Traslado;
use App\Models\Tratamiento;
use App\Models\User;
use App\Models\Venta;
use Carbon\CarbonImmutable;

/**
 * Datos del informe mensual de la finca actual. Los usan la pantalla, el Excel y el PDF (misma consulta).
 * Las ventas y las entradas de bodega se incluyen para el propietario (consulta) y el administrador (las opera).
 */
class InformeMensual
{
    /** @return array<string, mixed> */
    public static function datos(User $usuario, CarbonImmutable $mes): array
    {
        $desde = $mes->toDateString();
        $hasta = $mes->endOfMonth()->toDateString();
        $enMes = fn ($consulta, string $columna = 'fecha') => $consulta->whereBetween($columna, [$desde, $hasta]);
        $enMesMomento = fn ($consulta, string $columna) => $consulta->whereBetween($columna, [$mes, $mes->endOfMonth()]);
        $veBodegaYVentas = $usuario->tieneRol(Rol::PROPIETARIO, Rol::ADMINISTRADOR);

        $mortalidadPorLago = $enMes(Mortalidad::query()->join('siembras', 'siembras.id', '=', 'mortalidades.siembra_id'), 'mortalidades.fecha')
            ->groupBy('siembras.lago_id')->selectRaw('siembras.lago_id, SUM(mortalidades.cantidad) AS total')->pluck('total', 'lago_id');
        $alimentoPorLago = $enMes(MovimientoAlimento::where('tipo_movimiento', 'salida'))
            ->groupBy('lago_id')->selectRaw('lago_id, SUM(cantidad_kg) AS total')->pluck('total', 'lago_id');
        $muestreosPorLago = $enMes(Muestreo::query()->join('siembras', 'siembras.id', '=', 'muestreos.siembra_id'), 'muestreos.fecha')
            ->groupBy('siembras.lago_id')->selectRaw('siembras.lago_id, COUNT(*) AS total')->pluck('total', 'lago_id');

        $lagos = Lago::query()->with('siembrasActivas')->orderBy('nombre')->get()->map(fn (Lago $lago) => [
            'lago' => $lago,
            'mortalidad' => (int) ($mortalidadPorLago[$lago->id] ?? 0),
            'alimento_kg' => (float) ($alimentoPorLago[$lago->id] ?? 0),
            'muestreos' => (int) ($muestreosPorLago[$lago->id] ?? 0),
        ]);

        $mediciones = $enMesMomento(MedicionAgua::query()->with(['lago.siembrasActivas']), 'medido_at')->get();

        return [
            'mes' => $mes,
            'lagos' => $lagos,
            'siembrasNuevas' => $enMes(Siembra::query()->with('lago'))->orderBy('fecha')->get(),
            'alimentoPorConcentrado' => $enMes(MovimientoAlimento::where('tipo_movimiento', 'salida'))
                ->groupBy('tipo_concentrado')->selectRaw('tipo_concentrado, SUM(cantidad_kg) AS total')->orderBy('tipo_concentrado')
                ->pluck('total', 'tipo_concentrado'),
            'entradasBodega' => $veBodegaYVentas
                ? $enMes(MovimientoAlimento::where('tipo_movimiento', 'entrada'))->orderBy('fecha')->get()
                : null,
            'cosechas' => $enMes(Cosecha::query()->with(['lago', 'siembra', 'despachadoPor', 'pesajes']))->orderBy('fecha')->get(),
            'ventas' => $veBodegaYVentas
                ? [
                    'total' => (int) $enMes(Venta::query())->sum('total'),
                    'kg' => (float) $enMes(Venta::query())->sum('kg'),
                    'a_credito' => (int) $enMes(Venta::where('forma_pago', 'credito'))->sum('total'),
                    'abonos' => (int) $enMes(Abono::query())->sum('valor'),
                ]
                : null,
            'traslados' => $enMes(Traslado::query()->with(['lagoOrigen', 'lagoDestino', 'siembraOrigen']))->orderBy('fecha')->get(),
            'tratamientos' => $enMes(Tratamiento::query()->with('lago'), 'fecha_inicio')->orderBy('fecha_inicio')->get(),
            'mediciones' => $mediciones->count(),
            'medicionesConAlerta' => $mediciones->filter(fn (MedicionAgua $m) => $m->alertas($m->lago->siembrasActivas->pluck('especie')) !== [])->count(),
            'novedades' => $enMesMomento(Ronda::novedades()->with('lago'), 'registrada_at')->orderBy('registrada_at')->get(),
            // Avisos «listo para pescar» del mes (el propietario los consulta aquí)
            'avisosPesca' => $enMesMomento(AvisoPesca::query()->with(['lago', 'siembra', 'usuario']), 'reportado_at')->orderBy('reportado_at')->get(),
            'tareasCompletadas' => $enMesMomento(Tarea::query(), 'completada_at')->count(),
            'horasExtraAprobadas' => (float) $enMes(HoraExtra::query()->aprobadas())->sum('horas'),
        ];
    }

    /**
     * Tablas para exportar (Excel y PDF). Celdas: texto o número (los números se formatean al mostrarlos).
     *
     * @param  array<string, mixed>  $d  Resultado de datos()
     * @return list<Tabla>
     */
    public static function tablas(array $d): array
    {
        $t = fn (string $clave) => __('reportes.exportar.'.$clave);
        $fecha = fn ($valor) => $valor->format('d/m/Y');

        $resumen = [
            [$t('peces_sembrados'), (int) $d['siembrasNuevas']->sum('cantidad_alevinos')],
            [$t('mortalidad'), (int) $d['lagos']->sum('mortalidad')],
            [$t('alimento_kg'), (float) $d['lagos']->sum('alimento_kg')],
            [$t('tratamientos'), $d['tratamientos']->count()],
            [$t('mediciones'), $d['mediciones']],
            [$t('mediciones_alerta'), $d['medicionesConAlerta']],
            [$t('tareas_completadas'), $d['tareasCompletadas']],
            [$t('horas_extra'), $d['horasExtraAprobadas']],
            [$t('novedades'), $d['novedades']->count()],
        ];
        if ($d['ventas'] !== null) {
            array_push($resumen,
                [$t('ventas_total'), $d['ventas']['total']],
                [$t('ventas_kg'), $d['ventas']['kg']],
                [$t('ventas_credito'), $d['ventas']['a_credito']],
                [$t('abonos'), $d['ventas']['abonos']],
            );
        }

        $tablas = [
            new Tabla($t('hoja_resumen'), [[$t('indicador'), null], [$t('valor'), null]], $resumen),
            new Tabla($t('hoja_lagos'), [[$t('lago'), null], [$t('especies_hoy'), null], [$t('mortalidad'), 0], [$t('alimento_kg'), 1], [$t('muestreos'), 0], [$t('biomasa_kg'), 2]],
                $d['lagos']->map(fn ($fila) => [
                    $fila['lago']->nombre,
                    $fila['lago']->siembrasActivas->pluck('nombre_especie')->join(', '),
                    $fila['mortalidad'], $fila['alimento_kg'], $fila['muestreos'],
                    (float) $fila['lago']->siembrasActivas->sum('biomasa_kg'),
                ])->all()),
            new Tabla($t('hoja_alimento'), [[$t('concentrado'), null], [$t('alimento_kg'), 1]],
                $d['alimentoPorConcentrado']->map(fn ($kg, $concentrado) => [$concentrado, (float) $kg])->values()->all()),
            new Tabla($t('hoja_siembras'), [[$t('fecha'), null], [$t('lago'), null], [$t('especie'), null], [$t('peces'), 0], [$t('peso_g'), 2]],
                $d['siembrasNuevas']->map(fn ($s) => [$fecha($s->fecha), $s->lago->nombre, $s->nombre_especie, (int) $s->cantidad_alevinos, (float) $s->peso_promedio_inicial_g])->all()),
            // Cosechas con su encabezado (destino, despachó) y totales, y cada pesada con su cuenta
            TablasCosecha::cosechas($d['cosechas']),
            TablasCosecha::pesadas($d['cosechas']),
            new Tabla($t('hoja_traslados'), [[$t('fecha'), null], [$t('especie'), null], [$t('peces'), 0], [$t('origen'), null], [$t('destino'), null]],
                $d['traslados']->map(fn ($tr) => [$fecha($tr->fecha), $tr->siembraOrigen->nombre_especie, (int) $tr->cantidad, $tr->lagoOrigen->nombre, $tr->lagoDestino->nombre])->all()),
            new Tabla($t('hoja_sanidad'), [[$t('fecha'), null], [$t('lago'), null], [$t('motivo'), null], [$t('producto'), null]],
                $d['tratamientos']->map(fn ($tr) => [$fecha($tr->fecha_inicio), $tr->lago->nombre, $tr->motivo, $tr->producto])->all()),
            new Tabla($t('hoja_avisos_pesca'), [[$t('momento'), null], [$t('lago'), null], [$t('especie'), null], [$t('reportado_por'), null], [$t('estado'), null], [$t('nota'), null]],
                $d['avisosPesca']->map(fn ($a) => [$a->reportado_at->format('d/m/Y H:i'), $a->lago->nombre, $a->siembra->nombre_especie, $a->usuario->name,
                    $a->estaPendiente() ? __('reportes.aviso_pendiente') : __('reportes.aviso_atendido', ['fecha' => $a->atendido_at->format('d/m H:i')]), (string) $a->nota])->all()),
            new Tabla($t('hoja_novedades'), [[$t('momento'), null], [$t('tipo'), null], [$t('lago'), null], [$t('descripcion'), null]],
                $d['novedades']->map(fn ($n) => [$n->registrada_at->format('d/m/Y H:i'), $n->textoTipo(), $n->lago?->nombre ?? '', (string) $n->descripcion])->all()),
        ];

        if ($d['entradasBodega'] !== null) {
            $tablas[] = new Tabla($t('hoja_bodega'), [[$t('fecha'), null], [$t('concentrado'), null], [$t('kg'), 1], [$t('proveedor'), null], [$t('lote'), null]],
                $d['entradasBodega']->map(fn ($e) => [$fecha($e->fecha), $e->tipo_concentrado, (float) $e->cantidad_kg, (string) $e->proveedor, (string) $e->lote])->all());
        }

        return $tablas;
    }
}
