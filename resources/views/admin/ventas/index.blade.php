@extends('layouts.app')
@use('App\Support\Formato')

@section('titulo', __('ventas.ventas'))

@section('contenido')

    <x-encabezado :titulo="__('ventas.ventas_pescado')" :subtitulo="__('ventas.pescado_cosechado_disponible_ventas_creditos')">
        @if ($puedeVender)
            @if ($disponibles->isEmpty())
                {{-- Desactivado a la vista y para lectores de pantalla, con el motivo enlazado --}}
                <x-boton tipo="button" disabled aria-disabled="true" aria-describedby="motivo-sin-cosecha" data-nueva-venta>
                    <x-icono nombre="mas" class="h-5 w-5" /> {{ __('ventas.nueva_venta') }}
                </x-boton>
            @else
                <x-boton tipo="button" data-abrir-modal="modal-venta" data-nueva-venta>
                    <x-icono nombre="mas" class="h-5 w-5" /> {{ __('ventas.nueva_venta') }}
                </x-boton>
            @endif
        @endif
    </x-encabezado>

    @if ($puedeVender && $disponibles->isEmpty())
        <p id="motivo-sin-cosecha" role="status" class="mb-6 flex flex-wrap items-center gap-x-2 gap-y-1 rounded-ficha border border-atencion-borde bg-atencion-fondo px-4 py-3 text-base text-atencion">
            <x-icono nombre="alerta" class="h-5 w-5" />
            <span>{{ __('ventas.sin_cosecha_disponible') }}</span>
            <a href="{{ route('admin.lagos.index') }}" class="font-semibold underline underline-offset-2">{{ __('ventas.ir_a_lagos') }}</a>
        </p>
    @endif

    <div class="mb-6 grid grid-cols-2 gap-4 lg:grid-cols-3">
        <x-indicador :etiqueta="__('ventas.vendido_este_mes')" :valor="Formato::dinero($vendidoMes)" />
        <x-indicador :etiqueta="__('ventas.kg_vendidos_este_mes')" :valor="Formato::numero($kgMes, 1)" unidad="kg" />
        <x-indicador :etiqueta="__('ventas.cobrar')" :valor="Formato::dinero($cartera)" class="col-span-2 lg:col-span-1" />
    </div>

    <div class="grid gap-6 lg:grid-cols-2">
        <x-tarjeta :titulo="__('ventas.pescado_disponible')" :subtitulo="__('ventas.kg_cosechados_aun_no_han')">
            @forelse ($disponibles as $cosecha)
                <div class="flex items-center justify-between gap-3 border-t border-renglon py-3 first:border-t-0 first:pt-0">
                    <div class="min-w-0">
                        <p class="font-semibold text-tinta">{{ __('ventas.especie_lago', ['especie' => $cosecha->siembra->nombre_especie, 'lago' => $cosecha->lago->nombre]) }}</p>
                        <p class="text-sm text-tinta-suave">{{ __('ventas.cosechado_el', ['fecha' => $cosecha->fecha->format('d/m/Y'), 'gramos' => Formato::numero($cosecha->peso_promedio_g)]) }}</p>
                    </div>
                    <p class="shrink-0 text-lg font-bold tabular-nums text-tinta">{{ Formato::numero($cosecha->kgDisponibles(), 1) }} <span class="text-sm font-medium text-tinta-suave">kg</span></p>
                </div>
            @empty
                <p class="text-base text-tinta-suave">{{ __('ventas.no_hay_pescado_cosechado_sin') }}</p>
            @endforelse
        </x-tarjeta>

        <x-tarjeta :titulo="__('ventas.creditos_cobrar')">
            @forelse ($creditos as $credito)
                <div class="flex items-center justify-between gap-3 border-t border-renglon py-3 first:border-t-0 first:pt-0">
                    <div class="min-w-0">
                        <p class="font-semibold text-tinta">{{ $credito->cliente }}@if ($credito->comprador_id) <span class="text-sm font-normal text-tinta-suave">{{ __('ventas.empleado') }}</span>@endif</p>
                        <p class="text-sm text-tinta-suave">{{ __('ventas.detalle_credito', ['fecha' => $credito->fecha->format('d/m/Y'), 'kg' => Formato::numero($credito->kg, 1), 'total' => Formato::dinero($credito->total)]) }}</p>
                    </div>
                    <div class="shrink-0 text-right">
                        <p class="font-bold tabular-nums text-tinta">{{ Formato::dinero($credito->saldo) }}</p>
                        @if ($puedeVender)
                            <button type="button" data-abrir-modal="abono-{{ $credito->id }}" class="min-h-12 text-sm font-semibold text-marca-700 hover:underline">{{ __('ventas.registrar_abono') }}</button>
                        @endif
                    </div>
                </div>

                @if ($puedeVender)
                <x-modal id="abono-{{ $credito->id }}" :titulo="__('ventas.abono_de', ['cliente' => $credito->cliente])">
                    <p class="mb-4 text-base text-tinta-suave">{{ __('ventas.saldo_pendiente') }} <strong class="text-tinta">{{ Formato::dinero($credito->saldo) }}</strong></p>
                    <x-formulario :action="route('admin.ventas.abonar', $credito)">
                        <div class="grid grid-cols-2 gap-4">
                            <x-campo nombre="valor" id="abono-valor-{{ $credito->id }}" :etiqueta="__('ventas.valor')" tipo="number" min="1" :max="$credito->saldo" inputmode="numeric" required />
                            <x-campo nombre="fecha" id="abono-fecha-{{ $credito->id }}" :etiqueta="__('comun.fecha')" tipo="date" :valor="\App\Support\Region::hoy()->toDateString()" required />
                        </div>
                        <x-seleccion nombre="medio" id="abono-medio-{{ $credito->id }}" :etiqueta="__('ventas.medio_pago')" :opciones="$medios" required />
                        <div class="grid grid-cols-2 gap-3 pt-2">
                            <x-boton tipo="button" variante="secundario" data-cerrar-modal>{{ __('comun.cancelar') }}</x-boton>
                            <x-boton>{{ __('comun.guardar') }}</x-boton>
                        </div>
                    </x-formulario>
                </x-modal>
                @endif
            @empty
                <p class="text-base text-tinta-suave">{{ __('ventas.no_hay_creditos_pendientes') }}</p>
            @endforelse
        </x-tarjeta>
    </div>

    <section class="mt-8">
        <h2 class="mb-3 text-lg font-semibold text-tinta">{{ __('ventas.ventas') }}</h2>
        <x-tabla :columnas="[__('comun.fecha'), __('ventas.cliente'), __('ventas.pescado'), __('ventas.kg'), __('ventas.precio_por_kg'), __('ventas.total'), __('ventas.pago')]" :vacio="__('ventas.aun_no_hay_ventas')">
            @foreach ($ventas as $venta)
                <tr>
                    <td data-label="{{ __('comun.fecha') }}">{{ $venta->fecha->format('d/m/Y') }}</td>
                    <td data-label="{{ __('ventas.cliente') }}" class="font-medium">{{ $venta->cliente }}</td>
                    <td data-label="{{ __('ventas.pescado') }}">{{ __('lagos.lago_nombre', ['lago' => $venta->cosecha->lago->nombre]) }}</td>
                    <td data-label="{{ __('ventas.kg') }}" class="tabular-nums">{{ Formato::numero($venta->kg, 1) }}</td>
                    <td data-label="{{ __('ventas.precio_por_kg') }}" class="tabular-nums">{{ Formato::dinero($venta->precio_kg) }}</td>
                    <td data-label="{{ __('ventas.total') }}" class="font-semibold tabular-nums">{{ Formato::dinero($venta->total) }}</td>
                    <td data-label="{{ __('ventas.pago') }}">
                        @if (! $venta->esCredito())
                            <span class="rounded-full bg-bien-fondo px-2.5 py-0.5 text-sm font-semibold text-bien">{{ __('ventas.contado') }}</span>
                        @elseif ($venta->saldo > 0)
                            <span class="rounded-full bg-atencion-fondo px-2.5 py-0.5 text-sm font-semibold text-atencion">{{ __('ventas.debe', ['saldo' => Formato::dinero($venta->saldo)]) }}</span>
                        @else
                            <span class="rounded-full bg-papel-hondo px-2.5 py-0.5 text-sm font-semibold text-tinta">{{ __('ventas.credito_pagado') }}</span>
                        @endif
                    </td>
                </tr>
            @endforeach
        </x-tabla>

        @if ($ventas->hasPages())
            <nav class="mt-4 flex items-center justify-between gap-3" aria-label="{{ __('comun.paginas') }}">
                @if ($ventas->onFirstPage()) <span></span> @else <x-boton variante="secundario" :href="$ventas->previousPageUrl()"><x-icono nombre="abajo" class="h-5 w-5 rotate-90" /> {{ __('comun.anteriores') }}</x-boton> @endif
                <span class="text-sm text-tinta-suave">{{ __('comun.pagina_de', ['actual' => $ventas->currentPage(), 'total' => $ventas->lastPage()]) }}</span>
                @if ($ventas->hasMorePages()) <x-boton variante="secundario" :href="$ventas->nextPageUrl()">{{ __('comun.siguientes') }} <x-icono nombre="abajo" class="h-5 w-5 -rotate-90" /></x-boton> @else <span></span> @endif
            </nav>
        @endif
    </section>

    @if ($puedeVender && $disponibles->isNotEmpty())
        <x-modal id="modal-venta" :titulo="__('ventas.nueva_venta')">
            <x-formulario :action="route('admin.ventas.store')">
                <x-seleccion nombre="cosecha_id" :etiqueta="__('ventas.pescado_de')" required
                             :opciones="$disponibles->mapWithKeys(fn ($c) => [$c->id => __('ventas.opcion_cosecha', ['especie' => $c->siembra->nombre_especie, 'lago' => $c->lago->nombre, 'kg' => Formato::numero($c->kgDisponibles(), 1)])])->all()" />
                <x-campo nombre="cliente" :etiqueta="__('ventas.cliente')" :placeholder="__('ventas.nombre_comprador')" :ayuda="__('ventas.si_compra_empleado_eligelo_abajo')" />
                <x-seleccion nombre="comprador_id" :etiqueta="__('ventas.empleado_compra_opcional')" :opciones="['' => __('ventas.no_es_empleado')] + $empleados" />
                <div class="grid grid-cols-2 gap-4">
                    <x-campo nombre="kg" :etiqueta="__('ventas.kg')" tipo="number" step="0.01" min="0.1" inputmode="decimal" required />
                    <x-campo nombre="precio_kg" :etiqueta="__('ventas.precio_kg')" tipo="number" min="1" inputmode="numeric" required />
                    <x-campo nombre="fecha" :etiqueta="__('comun.fecha')" tipo="date" :valor="\App\Support\Region::hoy()->toDateString()" required />
                    <x-seleccion nombre="forma_pago" :etiqueta="__('ventas.pago')" :opciones="$formasPago" required />
                </div>

                <div class="grid grid-cols-2 gap-3 pt-2">
                    <x-boton tipo="button" variante="secundario" data-cerrar-modal>{{ __('comun.cancelar') }}</x-boton>
                    <x-boton>{{ __('ventas.registrar_venta') }}</x-boton>
                </div>
            </x-formulario>
        </x-modal>
    @endif
@endsection
