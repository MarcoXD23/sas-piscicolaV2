@extends('layouts.app')

@section('titulo', __('reportes.informe_mensual'))

@section('contenido')
    @php
        $numero = fn ($valor, $decimales = 0) => \App\Support\Formato::numero((float) $valor, $decimales);
        $totalMortalidad = $lagos->sum('mortalidad');
        $totalAlimento = $lagos->sum('alimento_kg');
    @endphp

    <x-encabezado-reporte :titulo="__('reportes.informe_mensual')" :periodo="ucfirst($mes->translatedFormat(__('comun.formatos.mes_anio')))" />

    <section class="mb-6 grid grid-cols-2 gap-3 sm:grid-cols-4 print:grid-cols-4">
        <x-indicador :etiqueta="__('reportes.peces_sembrados')" :valor="$numero($siembrasNuevas->sum('cantidad_alevinos'))" />
        <x-indicador :etiqueta="__('reportes.mortalidad')" :valor="$numero($totalMortalidad)" :unidad="__('reportes.peces')" />
        <x-indicador :etiqueta="__('reportes.alimento_suministrado')" :valor="$numero($totalAlimento, 1)" unidad="kg" />
        <x-indicador :etiqueta="__('reportes.tratamientos')" :valor="$tratamientos->count()" />
    </section>

    <section class="mb-6 break-inside-avoid">
        <h2 class="mb-2 text-lg font-semibold text-slate-900">{{ __('reportes.lago_2') }}</h2>
        <x-tabla :columnas="[__('reportes.lago'), __('reportes.especies_hoy'), __('reportes.mortalidad_mes'), __('reportes.alimento_mes'), __('reportes.muestreos'), __('reportes.biomasa_hoy')]">
            @foreach ($lagos as $fila)
                <tr>
                    <td data-label="{{ __('reportes.lago') }}" class="font-semibold">{{ $fila['lago']->nombre }}</td>
                    <td data-label="{{ __('reportes.especies_hoy') }}">{{ $fila['lago']->siembrasActivas->pluck('nombre_especie')->join(', ') ?: '—' }}</td>
                    <td data-label="{{ __('reportes.mortalidad_mes') }}" class="tabular-nums">{{ $numero($fila['mortalidad']) }}</td>
                    <td data-label="{{ __('reportes.alimento_mes') }}" class="tabular-nums">{{ $numero($fila['alimento_kg'], 1) }} kg</td>
                    <td data-label="{{ __('reportes.muestreos') }}" class="tabular-nums">{{ $fila['muestreos'] }}</td>
                    <td data-label="{{ __('reportes.biomasa_hoy') }}" class="tabular-nums">{{ $numero($fila['lago']->siembrasActivas->sum('biomasa_kg'), 2) }} kg</td>
                </tr>
            @endforeach
        </x-tabla>
        <p class="mt-2 text-xs text-slate-500">{{ __('reportes.especies_hoy_biomasa_hoy_muestran') }}</p>
    </section>

    <div class="grid gap-6 md:grid-cols-2 print:grid-cols-2">
        <x-tarjeta :titulo="__('reportes.alimento_concentrado')" class="break-inside-avoid">
            @forelse ($alimentoPorConcentrado as $concentrado => $kg)
                <div class="flex justify-between gap-3 border-t border-slate-100 py-2 first:border-t-0">
                    <span>{{ $concentrado }}</span><span class="font-semibold tabular-nums">{{ $numero($kg, 1) }} kg</span>
                </div>
            @empty
                <p class="text-slate-500">{{ __('reportes.sin_alimentacion_registrada') }}</p>
            @endforelse
        </x-tarjeta>

        <x-tarjeta :titulo="__('reportes.siembras_mes')" class="break-inside-avoid">
            @forelse ($siembrasNuevas as $siembra)
                <div class="border-t border-slate-100 py-2 first:border-t-0">
                    <p class="font-medium">{{ __('reportes.fecha_lago_especie', ['fecha' => $siembra->fecha->format('d/m'), 'lago' => $siembra->lago->nombre, 'especie' => $siembra->nombre_especie]) }}</p>
                    <p class="text-sm text-slate-500">{{ __('reportes.peces_de_gramos', ['peces' => $numero($siembra->cantidad_alevinos), 'gramos' => $numero($siembra->peso_promedio_inicial_g, 2)]) }}</p>
                </div>
            @empty
                <p class="text-slate-500">{{ __('reportes.sin_siembras_nuevas') }}</p>
            @endforelse
        </x-tarjeta>

        <x-tarjeta :titulo="__('reportes.cosechas_ventas')" class="break-inside-avoid">
            @forelse ($cosechas as $cosecha)
                <p class="border-t border-slate-100 py-2 first:border-t-0">
                    {{ __('reportes.fecha_lago_especie', ['fecha' => $cosecha->fecha->format('d/m'), 'lago' => $cosecha->lago->nombre, 'especie' => $cosecha->siembra->nombre_especie]) }}:
                    {{ __('reportes.kg_y_peces', ['kg' => $numero($cosecha->kg, 1), 'peces' => $numero($cosecha->peces)]) }}
                </p>
            @empty
                <p class="text-slate-500">{{ __('reportes.sin_cosechas') }}</p>
            @endforelse
            @if ($ventas !== null)
                <div class="mt-2 space-y-1 border-t border-slate-200 pt-2">
                    <p>{{ __('reportes.vendido') }} <strong>{{ \App\Support\Formato::dinero($ventas['total']) }}</strong> ({{ $numero($ventas['kg'], 1) }} kg)</p>
                    <p class="text-sm text-slate-600">
                        {{ __('reportes.credito_y_abonos', ['credito' => \App\Support\Formato::dinero($ventas['a_credito']), 'abonos' => \App\Support\Formato::dinero($ventas['abonos'])]) }}
                    </p>
                </div>
            @endif
        </x-tarjeta>

        <x-tarjeta :titulo="__('reportes.traslados')" class="break-inside-avoid">
            @forelse ($traslados as $traslado)
                <p class="border-t border-slate-100 py-2 first:border-t-0">
                    {{ __('reportes.detalle_traslado', ['fecha' => $traslado->fecha->format('d/m'), 'cantidad' => $numero($traslado->cantidad), 'especie' => $traslado->siembraOrigen->nombre_especie, 'origen' => $traslado->lagoOrigen->nombre, 'destino' => $traslado->lagoDestino->nombre]) }}
                </p>
            @empty
                <p class="text-slate-500">{{ __('reportes.sin_traslados') }}</p>
            @endforelse
        </x-tarjeta>

        <x-tarjeta :titulo="__('reportes.sanidad_agua')" class="break-inside-avoid">
            @foreach ($tratamientos as $tratamiento)
                <p class="border-t border-slate-100 py-2 first:border-t-0">
                    {{ __('reportes.detalle_tratamiento', ['fecha' => $tratamiento->fecha_inicio->format('d/m'), 'lago' => $tratamiento->lago->nombre, 'motivo' => $tratamiento->motivo, 'producto' => $tratamiento->producto]) }}
                </p>
            @endforeach
            <p @class(['py-2', 'border-t border-slate-100' => $tratamientos->isNotEmpty()])>
                {{ __('reportes.resumen_mediciones', ['mediciones' => $mediciones, 'alertas' => $medicionesConAlerta]) }}
            </p>
        </x-tarjeta>

        <x-tarjeta :titulo="__('reportes.personal_seguridad')" class="break-inside-avoid">
            <p class="py-1">{{ __('reportes.tareas_completadas', ['cantidad' => $tareasCompletadas]) }}</p>
            <p class="py-1">{{ __('reportes.horas_extra_aprobadas', ['horas' => $numero($horasExtraAprobadas, 1)]) }}</p>
            <p class="py-1">{{ __('reportes.novedades_seguridad', ['cantidad' => $novedades->count()]) }}</p>
            @foreach ($novedades as $novedad)
                <p class="text-sm text-slate-600">{{ $novedad->registrada_at->format('d/m H:i') }} · {{ $novedad->textoTipo() }}: {{ $novedad->descripcion }}</p>
            @endforeach
        </x-tarjeta>

        @if ($entradasBodega !== null)
            <x-tarjeta :titulo="__('reportes.entradas_bodega')" class="break-inside-avoid">
                @forelse ($entradasBodega as $entrada)
                    <p class="border-t border-slate-100 py-2 first:border-t-0">
                        {{ __('reportes.detalle_entrada', ['fecha' => $entrada->fecha->format('d/m'), 'kg' => $numero($entrada->cantidad_kg, 1), 'tipo' => $entrada->tipo_concentrado]) }}
                        <span class="text-sm text-slate-500">{{ __('reportes.proveedor_lote', ['proveedor' => $entrada->proveedor, 'lote' => $entrada->lote]) }}</span>
                    </p>
                @empty
                    <p class="text-slate-500">{{ __('reportes.sin_entradas') }}</p>
                @endforelse
            </x-tarjeta>
        @endif
    </div>
@endsection
