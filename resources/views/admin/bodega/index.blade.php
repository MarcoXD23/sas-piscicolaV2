@extends('layouts.app')

@section('titulo', __('bodega.bodega_alimento'))

@section('contenido')
    @php
        $numero = fn ($valor, $decimales = 0) => \App\Support\Formato::numero((float) $valor, $decimales);
        $estilos = [
            \App\Support\CoberturaBodega::AGOTADO => ['icono' => 'bg-red-50 text-red-600', 'cifra' => 'text-red-600', 'chip' => 'bg-red-100 text-red-800', 'texto' => __('bodega.agotado')],
            \App\Support\CoberturaBodega::BAJO => ['icono' => 'bg-amber-50 text-amber-600', 'cifra' => 'text-amber-700', 'chip' => 'bg-amber-100 text-amber-900', 'texto' => __('bodega.stock_bajo')],
            \App\Support\CoberturaBodega::SUFICIENTE => ['icono' => 'bg-marca-50 text-marca-700', 'cifra' => 'text-marca-700', 'chip' => null, 'texto' => null],
        ];
    @endphp

    <x-encabezado :titulo="__('bodega.bodega_alimento')" :subtitulo="__('bodega.inventario_concentrados_movimientos_finca')">
        <x-boton tipo="button" data-abrir-modal="modal-recepcion">
            <x-icono nombre="mas" class="h-5 w-5" /> {{ __('bodega.recibir_alimento') }}
        </x-boton>
    </x-encabezado>

    @if ($cobertura->isEmpty())
        <x-vacio icono="bodega" :mensaje="__('bodega.bodega_esta_vacia_registra_primera')">
            <x-boton tipo="button" data-abrir-modal="modal-recepcion">
                <x-icono nombre="mas" class="h-5 w-5" /> {{ __('bodega.recibir_alimento') }}
            </x-boton>
        </x-vacio>
    @endif

    <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ($cobertura as $item)
            @php
                $stock = $item['stock'];
                $estilo = $estilos[$item['estado']];
            @endphp
            <x-tarjeta>
                <div class="flex items-start gap-4">
                    <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl {{ $estilo['icono'] }}">
                        <x-icono nombre="bodega" />
                    </span>
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <h2 class="text-lg font-semibold text-slate-900">{{ $stock->tipo_concentrado }}</h2>
                            @if ($estilo['chip'])
                                <span class="rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $estilo['chip'] }}">{{ $estilo['texto'] }}</span>
                            @endif
                        </div>
                        <p class="mt-1 text-3xl font-bold tabular-nums {{ $estilo['cifra'] }}">
                            {{ $numero($stock->cantidad_kg, 2) }} <span class="text-base font-medium text-slate-500">kg</span>
                        </p>
                        <p class="mt-1 text-sm text-slate-500">
                            @if ($item['dias'] !== null)
                                {!! __('bodega.alcanza_para', [
                                    'dias' => '<strong class="font-semibold text-slate-700">'.e(__('bodega.dias', ['dias' => $numero($item['dias'], 1)])).'</strong>',
                                    'consumo' => e($numero($item['consumo_diario_kg'], 1)),
                                ]) !!}
                            @else
                                {{ __('bodega.sin_consumo', ['dias' => \App\Support\CoberturaBodega::VENTANA_DIAS]) }}
                            @endif
                        </p>
                    </div>
                </div>
            </x-tarjeta>
        @endforeach
    </div>

    <section class="mt-8">
        <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
            <h2 class="text-lg font-semibold text-slate-900">{{ __('bodega.movimientos') }}</h2>
            <nav class="flex gap-1 rounded-xl bg-slate-200/70 p-1 text-sm font-semibold" aria-label="{{ __('bodega.filtrar_movimientos') }}">
                @foreach ([null => __('bodega.todos'), 'entrada' => __('bodega.entradas'), 'salida' => __('bodega.salidas')] as $valor => $texto)
                    <a href="{{ route('admin.bodega.index', array_filter(['tipo' => $valor ?: null])) }}"
                       @if ($tipo === ($valor ?: null)) aria-current="page" @endif
                       @class([
                           'flex min-h-10 items-center rounded-lg px-3',
                           'bg-white text-marca-800 shadow-sm' => $tipo === ($valor ?: null),
                           'text-slate-600' => $tipo !== ($valor ?: null),
                       ])>{{ $texto }}</a>
                @endforeach
            </nav>
        </div>

        <x-tabla :columnas="[__('comun.fecha'), __('bodega.movimiento'), __('bodega.concentrado'), __('bodega.cantidad'), __('bodega.detalle')]" :vacio="__('bodega.aun_no_hay_movimientos')">
            @foreach ($movimientos as $movimiento)
                @php $entrada = $movimiento->tipo_movimiento === 'entrada'; @endphp
                <tr>
                    <td data-label="{{ __('comun.fecha') }}">{{ $movimiento->fecha->format('d/m/Y') }}</td>
                    <td data-label="{{ __('bodega.movimiento') }}">
                        <span @class([
                            'rounded-full px-2.5 py-0.5 text-sm font-semibold',
                            'bg-marca-100 text-marca-800' => $entrada,
                            'bg-slate-100 text-slate-700' => ! $entrada,
                        ])>{{ $entrada ? __('bodega.entrada') : __('bodega.salida') }}</span>
                    </td>
                    <td data-label="{{ __('bodega.concentrado') }}">{{ $movimiento->tipo_concentrado }}</td>
                    <td data-label="{{ __('bodega.cantidad') }}" class="font-semibold tabular-nums">{{ $entrada ? '+' : '−' }}{{ $numero($movimiento->cantidad_kg, 1) }} kg</td>
                    <td data-label="{{ __('bodega.detalle') }}" class="text-sm text-slate-600">
                        @if ($entrada)
                            {{ __('bodega.detalle_entrada', ['proveedor' => $movimiento->proveedor, 'lote' => $movimiento->lote, 'bultos' => $movimiento->bultos]) }}
                        @else
                            {{ __('lagos.lago_nombre', ['lago' => $movimiento->lago?->nombre]) }}
                        @endif
                        · {{ $movimiento->usuario?->name }}
                    </td>
                </tr>
            @endforeach
        </x-tabla>

        @if ($movimientos->hasPages())
            <nav class="mt-4 flex items-center justify-between gap-3" aria-label="{{ __('comun.paginas') }}">
                @if ($movimientos->onFirstPage())
                    <span></span>
                @else
                    <x-boton variante="secundario" :href="$movimientos->previousPageUrl()">{{ __('comun.anteriores') }}</x-boton>
                @endif
                <span class="text-sm text-slate-500">{{ __('comun.pagina_de', ['actual' => $movimientos->currentPage(), 'total' => $movimientos->lastPage()]) }}</span>
                @if ($movimientos->hasMorePages())
                    <x-boton variante="secundario" :href="$movimientos->nextPageUrl()">{{ __('comun.siguientes') }}</x-boton>
                @else
                    <span></span>
                @endif
            </nav>
        @endif
    </section>

    <x-modal id="modal-recepcion" :titulo="__('bodega.recepcion_alimento')">
        <x-formulario :action="route('admin.bodega.store')">
            <x-campo nombre="fecha" :etiqueta="__('bodega.fecha_recepcion')" tipo="date" :valor="\App\Support\Region::ahora()->format('Y-m-d')" required />
            <x-campo nombre="proveedor" :etiqueta="__('bodega.proveedor')" :placeholder="__('bodega.ej_nombre_casa_comercial')" required />
            <x-campo nombre="tipo_concentrado" :etiqueta="__('bodega.tipo_concentrado')" :placeholder="__('bodega.ej_engorde_32')" list="concentrados" required
                     :ayuda="__('bodega.si_ya_existe_bodega_escribelo')" />
            <datalist id="concentrados">
                @foreach ($cobertura as $item)
                    <option value="{{ $item['stock']->tipo_concentrado }}"></option>
                @endforeach
            </datalist>
            <x-campo nombre="lote" :etiqueta="__('bodega.lote_produccion')" required />

            <div class="grid grid-cols-2 gap-4">
                <x-campo nombre="bultos" :etiqueta="__('bodega.bultos')" tipo="number" min="1" inputmode="numeric" required />
                <x-campo nombre="peso_bulto_kg" :etiqueta="__('bodega.kg_bulto')" tipo="number" step="0.1" min="1" inputmode="decimal" valor="40" required />
            </div>

            <div class="grid grid-cols-2 gap-3 pt-2">
                <x-boton tipo="button" variante="secundario" data-cerrar-modal>{{ __('comun.cancelar') }}</x-boton>
                <x-boton>{{ __('bodega.guardar_entrada') }}</x-boton>
            </div>
        </x-formulario>
    </x-modal>
@endsection
