@extends('layouts.app')

@section('titulo', __('lagos.lagos_siembras'))

@section('contenido')
    @php
        $esTecnico = auth()->user()->tieneRol('propietario') || auth()->user()->tieneRol('administrador');
        $numero = fn ($valor, $decimales = 0) => \App\Support\Formato::numero($valor, $decimales);
    @endphp

    <x-encabezado :titulo="__('lagos.lagos_siembras')" :subtitulo="__('lagos.estanques_finca_ciclos_productivos')">
        @if ($esTecnico)
            <x-boton tipo="button" data-abrir-modal="modal-lago">
                <x-icono nombre="mas" class="h-5 w-5" /> {{ __('lagos.nuevo_lago') }}
            </x-boton>
        @endif
    </x-encabezado>

    @unless ($puedeRegistrar)
        <div role="status" class="mb-5 flex items-start gap-3 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-base text-amber-900">
            <x-icono nombre="reloj" class="mt-0.5 h-5 w-5" />
            <span>{{ __('lagos.estas_fuera_tu_turno_puedes') }}</span>
        </div>
    @endunless

    @if ($lagos->isEmpty())
        <x-vacio icono="lagos" :mensaje="__('lagos.aun_no_hay_lagos_registrados')">
            @if ($esTecnico)
                <x-boton tipo="button" data-abrir-modal="modal-lago">
                    <x-icono nombre="mas" class="h-5 w-5" /> {{ __('lagos.registrar_primer_lago') }}
                </x-boton>
            @endif
        </x-vacio>
    @endif

    <div class="grid items-start gap-5 md:grid-cols-2 lg:grid-cols-3">
        @foreach ($lagos as $lago)
            @php
                $siembras = $lago->siembrasActivas;
                $foto = $siembras->first()?->foto_especie;
            @endphp

            <x-tarjeta class="flex flex-col overflow-hidden !p-0">
                <div class="flex items-center gap-4 border-b border-slate-100 p-5">
                    @if ($foto)
                        <img src="{{ $foto }}" alt="" loading="lazy" decoding="async"
                             class="h-14 w-14 shrink-0 rounded-2xl object-cover ring-1 ring-slate-200">
                    @else
                        <span class="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl bg-marca-50 text-marca-600">
                            <x-icono :nombre="$siembras->isNotEmpty() ? 'pez' : 'lagos'" class="h-7 w-7" />
                        </span>
                    @endif

                    <div class="min-w-0 flex-1">
                        <h2 class="truncate text-xl font-bold text-slate-900">
                            <a href="{{ route('admin.lagos.show', $lago) }}" class="hover:text-marca-700 hover:underline">{{ $lago->nombre }}</a>
                        </h2>
                        <p class="text-sm text-slate-500">
                            {{ __('lagos.lago_en_tipo', ['tipo' => __('lagos.tipos.'.$lago->tipo)]) }}@if ($siembras->count() > 1) · {{ __('lagos.policultivo') }} @endif
                        </p>
                        @if ($lago->en_retiro)
                            <p class="mt-1 inline-flex items-center gap-1 rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-semibold text-amber-900">
                                <x-icono nombre="alerta" class="h-3.5 w-3.5" /> {{ __('lagos.retiro_sanitario') }}
                            </p>
                        @endif
                    </div>

                    <span @class([
                        'shrink-0 rounded-full px-3 py-1 text-xs font-semibold',
                        'bg-marca-100 text-marca-800' => $siembras->isNotEmpty(),
                        'bg-slate-100 text-slate-600' => $siembras->isEmpty(),
                    ])>{{ $siembras->isNotEmpty() ? __('lagos.activo') : __('lagos.libre') }}</span>
                </div>

                @forelse ($siembras as $siembra)
                    <div class="border-b border-slate-100">
                        <div class="flex items-center justify-between gap-3 px-5 pt-4">
                            <h3 class="font-semibold text-slate-900">{{ $siembra->nombre_especie }}</h3>
                            <span class="text-sm text-slate-500">{{ __('lagos.desde_fecha', ['fecha' => $siembra->fecha->format('d/m/Y')]) }}</span>
                        </div>

                        <dl class="mt-3 grid grid-cols-2 gap-px bg-slate-100 text-center">
                            <div class="bg-white p-3">
                                <dt class="text-xs font-medium text-slate-500">{{ __('lagos.peces_vivos') }}</dt>
                                <dd class="text-lg font-bold tabular-nums text-slate-900">{{ $numero($siembra->peces_vivos) }}</dd>
                            </div>
                            <div class="bg-white p-3">
                                <dt class="text-xs font-medium text-slate-500">{{ __('lagos.biomasa') }}</dt>
                                <dd class="text-lg font-bold tabular-nums text-slate-900">{{ $numero($siembra->biomasa_kg, 2) }} <span class="text-sm font-medium text-slate-500">kg</span></dd>
                            </div>
                            <div class="bg-white p-3">
                                <dt class="text-xs font-medium text-slate-500">{{ __('lagos.peso_promedio') }}</dt>
                                <dd class="text-lg font-bold tabular-nums text-slate-900">{{ $numero($siembra->peso_actual_g, 2) }} <span class="text-sm font-medium text-slate-500">g</span></dd>
                            </div>
                            <div class="bg-white p-3">
                                <dt class="text-xs font-medium text-slate-500">{{ __('lagos.dias_cultivo') }}</dt>
                                <dd class="text-lg font-bold tabular-nums text-slate-900">{{ $siembra->dias_cultivo }}</dd>
                            </div>
                        </dl>

                        <div @class(['grid gap-2 p-4', 'grid-cols-3' => $esTecnico, 'grid-cols-1' => ! $esTecnico])>
                            <x-boton tipo="button" variante="secundario" class="min-h-14 flex-col !gap-1 !px-1 !py-2 !text-sm" data-abrir-modal="modal-mortalidad-{{ $siembra->id }}" :disabled="! $puedeRegistrar">
                                <x-icono nombre="mortalidad" /> {{ __('lagos.mortalidad') }}
                            </x-boton>
                            @if ($esTecnico)
                                <x-boton tipo="button" variante="secundario" class="min-h-14 flex-col !gap-1 !px-1 !py-2 !text-sm" data-abrir-modal="modal-muestreo-{{ $siembra->id }}">
                                    <x-icono nombre="biomasa" /> {{ __('lagos.muestreo') }}
                                </x-boton>
                                <x-boton tipo="button" variante="secundario" class="min-h-14 flex-col !gap-1 !px-1 !py-2 !text-sm" data-abrir-modal="modal-finalizar-{{ $siembra->id }}">
                                    <x-icono nombre="ok" /> {{ __('lagos.finalizar') }}
                                </x-boton>
                            @endif
                        </div>
                    </div>

                    <x-modal id="modal-mortalidad-{{ $siembra->id }}" :titulo="__('lagos.mortalidad_de', ['especie' => $siembra->nombre_especie, 'lago' => $lago->nombre])">
                        <x-formulario :action="route('admin.mortalidades.store', $siembra->id)">
                            <x-campo nombre="fecha" id="mortalidad-fecha-{{ $siembra->id }}" :etiqueta="__('comun.fecha')" tipo="date" :valor="\App\Support\Region::ahora()->format('Y-m-d')" required />
                            <x-campo nombre="cantidad" id="mortalidad-cantidad-{{ $siembra->id }}" :etiqueta="__('lagos.peces_muertos')" tipo="number" min="1" :max="$siembra->peces_vivos" inputmode="numeric" required
                                     :ayuda="__('lagos.hay_peces_vivos', ['cantidad' => $numero($siembra->peces_vivos)])" />

                            <div class="grid grid-cols-2 gap-3 pt-2">
                                <x-boton tipo="button" variante="secundario" data-cerrar-modal>{{ __('comun.cancelar') }}</x-boton>
                                <x-boton variante="peligro">{{ __('lagos.registrar') }}</x-boton>
                            </div>
                        </x-formulario>
                    </x-modal>

                    @if ($esTecnico)
                        <x-modal id="modal-muestreo-{{ $siembra->id }}" :titulo="__('lagos.muestreo_de', ['especie' => $siembra->nombre_especie, 'lago' => $lago->nombre])">
                            <x-formulario :action="route('admin.muestreos.store', $siembra->id)">
                                <x-campo nombre="fecha" id="muestreo-fecha-{{ $siembra->id }}" :etiqueta="__('comun.fecha')" tipo="date" :valor="\App\Support\Region::ahora()->format('Y-m-d')" required />
                                <div class="grid grid-cols-2 gap-4">
                                    <x-campo nombre="peces_pesados" id="muestreo-peces-{{ $siembra->id }}" :etiqueta="__('lagos.peces_pesados')" tipo="number" min="1" inputmode="numeric" required />
                                    <x-campo nombre="peso_total_g" id="muestreo-peso-{{ $siembra->id }}" :etiqueta="__('lagos.peso_total_g')" tipo="number" step="0.1" min="0.1" inputmode="decimal" required />
                                </div>

                                <div class="grid grid-cols-2 gap-3 pt-2">
                                    <x-boton tipo="button" variante="secundario" data-cerrar-modal>{{ __('comun.cancelar') }}</x-boton>
                                    <x-boton>{{ __('comun.guardar') }}</x-boton>
                                </div>
                            </x-formulario>
                        </x-modal>

                        <x-modal id="modal-finalizar-{{ $siembra->id }}" :titulo="__('lagos.finalizar_siembra')">
                            <x-formulario :action="route('admin.siembras.finalizar', $siembra->id)">
                                <p class="text-base text-slate-700">
                                    {!! __('lagos.confirmar_finalizar', [
                                        'especie' => '<strong>'.e($siembra->nombre_especie).'</strong>',
                                        'lago' => '<strong>'.e($lago->nombre).'</strong>',
                                        'peces' => e($numero($siembra->peces_vivos)),
                                        'kg' => e($numero($siembra->biomasa_kg, 2)),
                                    ]) !!}
                                </p>

                                <div class="grid grid-cols-2 gap-3 pt-2">
                                    <x-boton tipo="button" variante="secundario" data-cerrar-modal>{{ __('comun.cancelar') }}</x-boton>
                                    <x-boton variante="peligro">{{ __('lagos.finalizar') }}</x-boton>
                                </div>
                            </x-formulario>
                        </x-modal>
                    @endif
                @empty
                    <p class="p-5 text-base text-slate-500">{{ __('lagos.este_lago_no_tiene_siembras') }}</p>
                @endforelse

                <div @class(['mt-auto grid gap-2 p-5', 'grid-cols-2' => $esTecnico && $siembras->isNotEmpty()])>
                    @if ($siembras->isNotEmpty())
                        <x-boton tipo="button" data-abrir-modal="modal-racion-{{ $lago->id }}" :disabled="! $puedeRegistrar">
                            <x-icono nombre="alimentar" class="h-5 w-5" /> {{ __('lagos.alimentar') }}
                        </x-boton>
                    @endif
                    @if ($esTecnico)
                        <x-boton tipo="button" variante="secundario" data-abrir-modal="modal-siembra-{{ $lago->id }}">
                            <x-icono nombre="mas" class="h-5 w-5" /> {{ $siembras->isNotEmpty() ? __('lagos.especie') : __('lagos.registrar_siembra') }}
                        </x-boton>
                    @endif
                    <a href="{{ route('admin.lagos.show', $lago) }}" @class([
                        'flex min-h-11 items-center justify-center text-base font-semibold text-marca-700 hover:underline',
                        'col-span-2' => $esTecnico && $siembras->isNotEmpty(),
                    ])>{{ __('lagos.ver_detalle_e_historial') }}</a>
                </div>

                @if ($siembras->isNotEmpty())
                    <x-modal id="modal-racion-{{ $lago->id }}" :titulo="__('lagos.alimentar_lago', ['lago' => $lago->nombre])">
                        <x-formulario :action="route('admin.raciones.store', $lago->id)">
                            <x-campo nombre="fecha" id="racion-fecha-{{ $lago->id }}" :etiqueta="__('comun.fecha')" tipo="date" :valor="\App\Support\Region::ahora()->format('Y-m-d')" required />

                            @if ($stocksDisponibles->isEmpty())
                                <p class="flex items-start gap-2 rounded-xl bg-red-50 p-4 text-base text-red-700">
                                    <x-icono nombre="alerta" class="mt-0.5 h-5 w-5" /> {{ __('lagos.no_hay_alimento_bodega') }}
                                </p>
                            @else
                                <x-seleccion nombre="tipo_concentrado" id="racion-concentrado-{{ $lago->id }}" :etiqueta="__('lagos.concentrado')" required
                                             :opciones="$stocksDisponibles->mapWithKeys(fn ($stock) => [$stock->tipo_concentrado => __('lagos.concentrado_disponible', ['tipo' => $stock->tipo_concentrado, 'kg' => $numero($stock->cantidad_kg, 1)])])->all()" />
                            @endif

                            @php $sugerida = $raciones[$lago->id]; @endphp
                            <x-campo nombre="cantidad_kg" id="racion-cantidad-{{ $lago->id }}" :etiqueta="__('lagos.cantidad_esta_toma_kg')" tipo="number" step="0.1" min="0.1" inputmode="decimal" required
                                     :valor="$sugerida['kg_por_toma'] > 0 ? $sugerida['kg_por_toma'] : null"
                                     :ayuda="$sugerida['tomas'] > 0
                                         ? __('lagos.racion_sugerida_ayuda', ['kg' => $numero($sugerida['kg_por_toma'], 1), 'tomas' => $sugerida['tomas'], 'total' => $numero($sugerida['total_kg_dia'], 1)])
                                             .($sugerida['factor'] < 1 ? ' '.__('lagos.racion_reducida_agua') : '')
                                         : null" />

                            <div class="grid grid-cols-2 gap-3 pt-2">
                                <x-boton tipo="button" variante="secundario" data-cerrar-modal>{{ __('comun.cancelar') }}</x-boton>
                                <x-boton :disabled="$stocksDisponibles->isEmpty()">{{ __('comun.guardar') }}</x-boton>
                            </div>
                        </x-formulario>
                    </x-modal>
                @endif

                @if ($esTecnico)
                    <x-modal id="modal-siembra-{{ $lago->id }}" :titulo="$siembras->isNotEmpty() ? __('lagos.agregar_especie_a', ['lago' => $lago->nombre]) : __('lagos.siembra_en', ['lago' => $lago->nombre])">
                        <x-formulario :action="route('admin.siembras.store', $lago->id)">
                            @if ($siembras->isNotEmpty())
                                <p class="rounded-xl bg-marca-50 p-4 text-base text-marca-900">
                                    {{ __('lagos.ayuda_policultivo', ['especies' => $siembras->pluck('nombre_especie')->join(', ', ' '.__('comun.y').' ')]) }}
                                </p>
                            @endif
                            <x-seleccion nombre="especie" id="siembra-especie-{{ $lago->id }}" :etiqueta="__('lagos.especie')" :opciones="$especies" required />
                            <x-campo nombre="fecha" id="siembra-fecha-{{ $lago->id }}" :etiqueta="__('lagos.fecha_siembra')" tipo="date" :valor="\App\Support\Region::ahora()->format('Y-m-d')" required />
                            <div class="grid grid-cols-2 gap-4">
                                <x-campo nombre="cantidad_alevinos" id="siembra-alevinos-{{ $lago->id }}" :etiqueta="__('lagos.alevinos')" tipo="number" min="1" inputmode="numeric" required />
                                <x-campo nombre="peso_promedio_inicial_g" id="siembra-peso-{{ $lago->id }}" :etiqueta="__('lagos.peso_inicial_g')" tipo="number" step="0.1" min="0.1" inputmode="decimal" required />
                                <x-campo nombre="proveedor_alevinos" id="siembra-proveedor-{{ $lago->id }}" :etiqueta="__('lagos.proveedor_opcional')" />
                                <x-campo nombre="lote_alevinos" id="siembra-lote-{{ $lago->id }}" :etiqueta="__('lagos.lote_opcional')" />
                            </div>

                            <div class="grid grid-cols-2 gap-3 pt-2">
                                <x-boton tipo="button" variante="secundario" data-cerrar-modal>{{ __('comun.cancelar') }}</x-boton>
                                <x-boton>{{ __('comun.guardar') }}</x-boton>
                            </div>
                        </x-formulario>
                    </x-modal>
                @endif
            </x-tarjeta>
        @endforeach
    </div>

    @if ($esTecnico)
        <x-modal id="modal-lago" :titulo="__('lagos.nuevo_lago')">
            <x-formulario :action="route('admin.lagos.store')">
                <x-campo nombre="nombre" id="lago-nombre" :etiqueta="__('lagos.nombre_codigo')" required />
                <x-seleccion nombre="tipo" id="lago-tipo" :etiqueta="__('lagos.tipo')" :opciones="$tipos" required />

                <div class="grid grid-cols-2 gap-3 pt-2">
                    <x-boton tipo="button" variante="secundario" data-cerrar-modal>{{ __('comun.cancelar') }}</x-boton>
                    <x-boton>{{ __('comun.guardar') }}</x-boton>
                </div>
            </x-formulario>
        </x-modal>
    @endif
@endsection
