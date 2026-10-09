@extends('layouts.app')

@section('titulo', __('bodega.bodega_alimento'))

@section('contenido')
    @php
        $numero = fn ($valor, $decimales = 0) => \App\Support\Formato::numero((float) $valor, $decimales);
        $estilos = [
            \App\Support\CoberturaBodega::AGOTADO => ['icono' => 'bg-alerta-fondo text-alerta', 'cifra' => 'text-alerta', 'chip' => 'bg-alerta-fondo text-alerta', 'texto' => __('bodega.agotado')],
            \App\Support\CoberturaBodega::BAJO => ['icono' => 'bg-atencion-fondo text-atencion', 'cifra' => 'text-atencion', 'chip' => 'bg-atencion-fondo text-atencion', 'texto' => __('bodega.stock_bajo')],
            \App\Support\CoberturaBodega::SUFICIENTE => ['icono' => 'bg-bien-fondo text-marca-700', 'cifra' => 'text-marca-700', 'chip' => null, 'texto' => null],
        ];
    @endphp

    <x-encabezado :titulo="__('bodega.bodega_alimento')" :subtitulo="__('bodega.inventario_concentrados_movimientos_finca')">
        {{-- Dos pasos: el propietario despacha y el administrador recibe --}}
        @if ($puedeDespachar)
            <x-boton tipo="button" variante="secundario" data-abrir-modal="modal-despacho">
                <x-icono nombre="mas" class="h-5 w-5" /> {{ __('bodega.registrar_despacho') }}
            </x-boton>
        @endif
        @if ($puedeRecibir)
            <x-boton tipo="button" data-abrir-modal="modal-recepcion">
                <x-icono nombre="mas" class="h-5 w-5" /> {{ __('bodega.recibir_alimento') }}
            </x-boton>
        @endif
    </x-encabezado>

    @if ($cobertura->isEmpty())
        <x-vacio icono="bodega" :mensaje="__('bodega.bodega_esta_vacia_registra_primera')" />
    @endif

    <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ($cobertura as $item)
            @php
                $stock = $item['stock'];
                $estilo = $estilos[$item['estado']];
            @endphp
            <x-tarjeta>
                <div class="flex items-start gap-4">
                    <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-ficha {{ $estilo['icono'] }}">
                        <x-icono nombre="bodega" />
                    </span>
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <h2 class="text-lg font-semibold text-tinta">{{ $stock->tipo_concentrado }}</h2>
                            @if ($estilo['chip'])
                                <span class="rounded-full px-2.5 py-0.5 text-sm font-semibold {{ $estilo['chip'] }}">{{ $estilo['texto'] }}</span>
                            @endif
                        </div>
                        <p class="mt-1 text-3xl font-bold tabular-nums {{ $estilo['cifra'] }}">
                            {{ $numero($stock->cantidad_kg, 2) }} <span class="text-base font-medium text-tinta-suave">kg</span>
                        </p>
                        <p class="mt-1 text-sm text-tinta-suave">
                            @if ($item['dias'] !== null)
                                {!! __('bodega.alcanza_para', [
                                    'dias' => '<strong class="font-semibold text-tinta">'.e(__('bodega.dias', ['dias' => $numero($item['dias'], 1)])).'</strong>',
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
        <h2 class="mb-3 text-lg font-semibold text-tinta">{{ __('bodega.despachos') }}</h2>
        @php
            $chipsDespacho = [
                \App\Models\DespachoAlimento::SIN_RECEPCION => 'bg-papel-hondo text-tinta',
                \App\Models\DespachoAlimento::EXACTO => 'bg-bien-fondo text-bien',
                \App\Models\DespachoAlimento::FALTANTE => 'bg-alerta-fondo text-alerta',
                \App\Models\DespachoAlimento::SOBRANTE => 'bg-atencion-fondo text-atencion',
            ];
        @endphp
        <x-tabla :columnas="[__('comun.fecha'), __('bodega.concentrado'), __('bodega.despachado'), __('bodega.recibido'), __('bodega.estado')]" :vacio="__('bodega.sin_despachos')">
            @foreach ($despachos as $despacho)
                @php $estado = $despacho->estado(); @endphp
                <tr>
                    <td data-label="{{ __('comun.fecha') }}">{{ $despacho->fecha->format('d/m/Y') }}</td>
                    <td data-label="{{ __('bodega.concentrado') }}">
                        <p class="font-medium text-tinta">{{ $despacho->tipo_concentrado }}</p>
                        <p class="text-sm text-tinta-suave">{{ __('bodega.detalle_entrada', ['proveedor' => $despacho->proveedor, 'lote' => $despacho->lote, 'bultos' => $despacho->bultos]) }}</p>
                    </td>
                    <td data-label="{{ __('bodega.despachado') }}" class="tabular-nums">{{ $numero($despacho->cantidad_kg, 1) }} kg</td>
                    <td data-label="{{ __('bodega.recibido') }}" class="tabular-nums">{{ $despacho->tieneRecepciones() ? $numero($despacho->kgRecibidos(), 1).' kg' : '—' }}</td>
                    <td data-label="{{ __('bodega.estado') }}">
                        <span class="inline-block rounded-full px-2.5 py-0.5 text-sm font-semibold {{ $chipsDespacho[$estado] }}">
                            {{ __('bodega.estados_despacho.'.$estado, ['kg' => rtrim(rtrim($numero(abs($despacho->diferenciaKg()), 1), '0'), ',.')]) }}
                        </span>
                        @if ($despacho->revisado_at)
                            <p class="mt-1 text-sm text-tinta-suave">{{ __('bodega.revisado_el', ['fecha' => $despacho->revisado_at->format('d/m/Y')]) }}</p>
                        @elseif ($puedeDespachar && in_array($estado, [\App\Models\DespachoAlimento::FALTANTE, \App\Models\DespachoAlimento::SOBRANTE], true))
                            <x-formulario :action="route('admin.bodega.despachos.revisado', $despacho)" class="mt-1 !space-y-0">
                                <button class="min-h-12 text-sm font-semibold text-marca-700 hover:underline">{{ __('bodega.marcar_revisado') }}</button>
                            </x-formulario>
                        @endif
                    </td>
                </tr>
            @endforeach
        </x-tabla>
    </section>

    <section class="mt-8">
        <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
            <h2 class="text-lg font-semibold text-tinta">{{ __('bodega.movimientos') }}</h2>
            <nav class="flex flex-wrap gap-x-1 border-b border-renglon text-base" aria-label="{{ __('bodega.filtrar_movimientos') }}">
                @foreach ([null => __('bodega.todos'), 'entrada' => __('bodega.entradas'), 'salida' => __('bodega.salidas')] as $valor => $texto)
                    <a href="{{ route('admin.bodega.index', array_filter(['tipo' => $valor ?: null])) }}"
                       @if ($tipo === ($valor ?: null)) aria-current="page" @endif
                       @class([
                           '-mb-px flex min-h-12 items-center px-3 transition-colors',
                           'font-bold text-tinta shadow-[inset_0_-3px_0_var(--color-marca-700)]' => $tipo === ($valor ?: null),
                           'text-tinta-suave hover:bg-papel-hondo hover:text-tinta' => $tipo !== ($valor ?: null),
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
                            'bg-bien-fondo text-bien' => $entrada,
                            'bg-papel-hondo text-tinta' => ! $entrada,
                        ])>{{ $entrada ? __('bodega.entrada') : __('bodega.salida') }}</span>
                    </td>
                    <td data-label="{{ __('bodega.concentrado') }}">{{ $movimiento->tipo_concentrado }}</td>
                    <td data-label="{{ __('bodega.cantidad') }}" class="font-semibold tabular-nums">{{ $entrada ? '+' : '−' }}{{ $numero($movimiento->cantidad_kg, 1) }} kg</td>
                    <td data-label="{{ __('bodega.detalle') }}" class="text-sm text-tinta-suave">
                        @if ($entrada)
                            {{ __('bodega.detalle_entrada', ['proveedor' => $movimiento->proveedor, 'lote' => $movimiento->lote, 'bultos' => $movimiento->bultos]) }}
                            · {{ $movimiento->despacho ? __('bodega.despacho_del', ['fecha' => $movimiento->despacho->fecha->format('d/m/Y'), 'proveedor' => $movimiento->despacho->proveedor]) : __('bodega.sin_despacho') }}
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
                    <x-boton variante="secundario" :href="$movimientos->previousPageUrl()"><x-icono nombre="abajo" class="h-5 w-5 rotate-90" /> {{ __('comun.anteriores') }}</x-boton>
                @endif
                <span class="text-sm text-tinta-suave">{{ __('comun.pagina_de', ['actual' => $movimientos->currentPage(), 'total' => $movimientos->lastPage()]) }}</span>
                @if ($movimientos->hasMorePages())
                    <x-boton variante="secundario" :href="$movimientos->nextPageUrl()">{{ __('comun.siguientes') }} <x-icono nombre="abajo" class="h-5 w-5 -rotate-90" /></x-boton>
                @else
                    <span></span>
                @endif
            </nav>
        @endif
    </section>

    @if ($puedeRecibir)
        <x-modal id="modal-recepcion" :titulo="__('bodega.recepcion_alimento')">
            <x-formulario :action="route('admin.bodega.recepciones.store')">
                <x-seleccion nombre="despacho_id" :etiqueta="__('bodega.despacho')" :ayuda="__('bodega.despacho_ayuda')"
                             :opciones="['' => __('bodega.sin_despacho')] + $despachosPorRecibir->mapWithKeys(fn ($d) => [$d->id => __('bodega.opcion_despacho', [
                                 'fecha' => $d->fecha->format('d/m'), 'concentrado' => $d->tipo_concentrado, 'kg' => $numero($d->cantidad_kg, 1), 'proveedor' => $d->proveedor,
                             ])])->all()" />
                <x-campo nombre="fecha" id="recepcion-fecha" :etiqueta="__('bodega.fecha_recepcion')" tipo="date" :valor="\App\Support\Region::ahora()->format('Y-m-d')" required />

                <fieldset class="space-y-4 rounded-ficha border border-renglon p-4">
                    <legend class="px-1 text-sm font-medium text-tinta-suave">{{ __('bodega.solo_sin_despacho') }}</legend>
                    <x-campo nombre="proveedor" id="recepcion-proveedor" :etiqueta="__('bodega.proveedor')" :placeholder="__('bodega.ej_nombre_casa_comercial')" />
                    <x-campo nombre="tipo_concentrado" id="recepcion-tipo" :etiqueta="__('bodega.tipo_concentrado')" :placeholder="__('bodega.ej_engorde_32')" list="concentrados"
                             :ayuda="__('bodega.si_ya_existe_bodega_escribelo')" />
                    <x-campo nombre="lote" id="recepcion-lote" :etiqueta="__('bodega.lote_produccion')" />
                </fieldset>
                <datalist id="concentrados">
                    @foreach ($cobertura as $item)
                        <option value="{{ $item['stock']->tipo_concentrado }}"></option>
                    @endforeach
                </datalist>

                <div class="grid grid-cols-2 gap-4">
                    <x-campo nombre="bultos" id="recepcion-bultos" :etiqueta="__('bodega.bultos_recibidos')" tipo="number" min="1" inputmode="numeric" required />
                    <x-campo nombre="peso_bulto_kg" id="recepcion-peso" :etiqueta="__('bodega.kg_bulto')" tipo="number" step="0.1" min="1" inputmode="decimal" :valor="\App\Models\DespachoAlimento::PESO_BULTO_POR_DEFECTO" required />
                </div>

                <div class="grid grid-cols-2 gap-3 pt-2">
                    <x-boton tipo="button" variante="secundario" data-cerrar-modal>{{ __('comun.cancelar') }}</x-boton>
                    <x-boton>{{ __('bodega.guardar_entrada') }}</x-boton>
                </div>
            </x-formulario>
        </x-modal>
    @endif

    @if ($puedeDespachar)
        <x-modal id="modal-despacho" :titulo="__('bodega.registrar_despacho')">
            <x-formulario :action="route('admin.bodega.despachos.store')">
                <x-aviso tono="info" icono="bodega" class="text-sm">{{ __('bodega.despacho_explicacion') }}</x-aviso>
                <x-campo nombre="fecha" id="despacho-fecha" :etiqueta="__('bodega.fecha_despacho')" tipo="date" :valor="\App\Support\Region::ahora()->format('Y-m-d')" required />
                <x-campo nombre="proveedor" id="despacho-proveedor" :etiqueta="__('bodega.proveedor')" :placeholder="__('bodega.ej_nombre_casa_comercial')" required />
                <x-campo nombre="tipo_concentrado" id="despacho-tipo" :etiqueta="__('bodega.tipo_concentrado')" :placeholder="__('bodega.ej_engorde_32')" list="concentrados-despacho" required />
                <datalist id="concentrados-despacho">
                    @foreach ($cobertura as $item)
                        <option value="{{ $item['stock']->tipo_concentrado }}"></option>
                    @endforeach
                </datalist>
                <x-campo nombre="lote" id="despacho-lote" :etiqueta="__('bodega.lote_produccion')" required />
                <div class="grid grid-cols-2 gap-4">
                    <x-campo nombre="bultos" id="despacho-bultos" :etiqueta="__('bodega.bultos')" tipo="number" min="1" inputmode="numeric" required />
                    <x-campo nombre="peso_bulto_kg" id="despacho-peso" :etiqueta="__('bodega.kg_bulto')" tipo="number" step="0.1" min="1" inputmode="decimal" :valor="\App\Models\DespachoAlimento::PESO_BULTO_POR_DEFECTO" required />
                </div>

                <div class="grid grid-cols-2 gap-3 pt-2">
                    <x-boton tipo="button" variante="secundario" data-cerrar-modal>{{ __('comun.cancelar') }}</x-boton>
                    <x-boton>{{ __('bodega.guardar_despacho') }}</x-boton>
                </div>
            </x-formulario>
        </x-modal>
    @endif
@endsection
