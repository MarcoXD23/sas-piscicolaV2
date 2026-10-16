@extends('layouts.app')
@use('App\Support\Formato')

@section('titulo', __('nomina.nomina'))

@section('contenido')
    <x-encabezado :titulo="__('comun.personal')" :subtitulo="__('nomina.nomina_semanal_personal_campo')" />
    <x-pestanas-personal />

    <div class="mb-4 flex items-center justify-between gap-2">
        <x-boton variante="secundario" class="w-12 !px-0" :href="route('admin.nomina.index', ['semana' => $lunes->subWeek()->toDateString()])" :aria-label="__('nomina.semana_anterior')"><x-icono nombre="abajo" class="h-5 w-5 rotate-90" /></x-boton>
        <h2 class="text-center text-lg font-semibold text-tinta">
            {{ __('comun.semana_del', ['desde' => $lunes->translatedFormat('j M'), 'hasta' => $lunes->addDays(6)->translatedFormat('j M Y')]) }}
        </h2>
        <x-boton variante="secundario" class="w-12 !px-0" :href="route('admin.nomina.index', ['semana' => $lunes->addWeek()->toDateString()])" :aria-label="__('nomina.semana_siguiente')"><x-icono nombre="abajo" class="h-5 w-5 -rotate-90" /></x-boton>
    </div>

    <p class="mb-4 rounded-ficha bg-papel-hondo p-3 text-sm text-tinta">
        {{ __('nomina.explicacion') }}
        @unless ($semanaTerminada)
            <strong class="font-semibold">{{ __('nomina.esta_semana_aun_no_termina') }}</strong> {{ __('nomina.solo_turnos_terminados') }}
        @endunless
    </p>

    @if ($personal->isEmpty())
        <x-vacio icono="personal" :mensaje="__('nomina.no_hay_trabajadores_ni_celadores')" />
    @endif

    <div class="space-y-4">
        @foreach ($personal as $persona)
            @php
                $liquidacion = $liquidaciones->get($persona->id);
                $previa = $previas->get($persona->id);
                $saldoPescado = (int) ($saldosPescado[$persona->id] ?? 0);
                $sinTarifas = $persona->valor_hora === null || $persona->valor_hora_extra === null;
            @endphp

            <x-tarjeta>
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h3 class="text-lg font-semibold text-tinta">{{ $persona->name }}</h3>
                        <p class="text-sm capitalize text-tinta-suave">{{ $persona->roles->pluck('nombre')->intersect(\App\Models\Turno::ROLES)->join(' y ') }}</p>
                        <p class="mt-1 text-sm text-tinta-suave">
                            @if ($sinTarifas)
                                <span class="font-semibold text-atencion">{{ __('nomina.sin_tarifas_configuradas') }}</span>
                            @else
                                {{ __('nomina.tarifas_resumen', ['hora' => Formato::dinero($persona->valor_hora), 'extra' => Formato::dinero($persona->valor_hora_extra)]) }}
                            @endif
                            · <button type="button" data-abrir-modal="tarifas-{{ $persona->id }}" class="min-h-12 font-semibold text-marca-700 hover:underline">{{ $sinTarifas ? __('nomina.configurar') : __('nomina.cambiar') }}</button>
                        </p>
                    </div>

                    @if ($liquidacion)
                        <span @class([
                            'rounded-full px-3 py-1 text-sm font-semibold',
                            'bg-bien-fondo text-bien' => $liquidacion->estaPagada(),
                            'bg-atencion-fondo text-atencion' => ! $liquidacion->estaPagada(),
                        ])>{{ $liquidacion->estaPagada() ? __('nomina.pagada_el', ['fecha' => $liquidacion->pagada_at->format('d/m')]) : __('nomina.por_pagar') }}</span>
                    @endif
                </div>

                @php $fila = $liquidacion ?? $previa; @endphp
                <dl class="mt-4 grid grid-cols-2 gap-x-6 gap-y-2 text-base sm:grid-cols-4">
                    <div><dt class="text-sm text-tinta-suave">{{ __('nomina.horas_turno') }}</dt><dd class="font-semibold tabular-nums">{{ __('comun.horas_corto', ['horas' => Formato::numero($fila['horas_turnos'], 1)]) }}</dd></div>
                    <div><dt class="text-sm text-tinta-suave">{{ __('nomina.horas_extra') }}</dt><dd class="font-semibold tabular-nums">{{ __('comun.horas_corto', ['horas' => Formato::numero($fila['horas_extra'], 1)]) }}</dd></div>
                    <div><dt class="text-sm text-tinta-suave">{{ __('nomina.bruto') }}</dt><dd class="font-semibold tabular-nums">{{ $sinTarifas && ! $liquidacion ? '—' : Formato::dinero($fila['bruto']) }}</dd></div>
                    <div>
                        <dt class="text-sm text-tinta-suave">{{ $liquidacion ? __('nomina.total_a_pagar') : __('nomina.pescado_por_descontar') }}</dt>
                        <dd class="text-lg font-bold tabular-nums text-tinta">{{ Formato::dinero($liquidacion ? $liquidacion->total_pagar : $saldoPescado) }}</dd>
                    </div>
                </dl>

                @if ($liquidacion)
                    @if ($liquidacion->descuento_pescado > 0)
                        <p class="mt-2 text-sm text-tinta-suave">{{ __('nomina.incluye_descuento', ['valor' => Formato::dinero($liquidacion->descuento_pescado)]) }}</p>
                    @endif
                    @unless ($liquidacion->estaPagada())
                        <div class="mt-4 grid grid-cols-2 gap-2">
                            <x-formulario :action="route('admin.nomina.destroy', $liquidacion)" metodo="DELETE" class="!space-y-0">
                                <x-boton variante="secundario" class="w-full" data-confirmar="{{ __('nomina.confirmar_anular') }}">{{ __('nomina.anular') }}</x-boton>
                            </x-formulario>
                            <x-formulario :action="route('admin.nomina.pagar', $liquidacion)" class="!space-y-0">
                                <x-boton class="w-full"><x-icono nombre="ok" class="h-5 w-5" /> {{ __('nomina.marcar_pagada') }}</x-boton>
                            </x-formulario>
                        </div>
                    @endunless
                @else
                    @if ($previa['turnos_en_permiso']->isNotEmpty() || $previa['turnos_sin_terminar'] > 0)
                        <p class="mt-2 text-sm text-tinta-suave">
                            @if ($previa['turnos_en_permiso']->isNotEmpty()) {{ __('nomina.turnos_en_permiso', ['cantidad' => $previa['turnos_en_permiso']->count()]) }} @endif
                            @if ($previa['turnos_sin_terminar'] > 0) {{ __('nomina.turnos_sin_terminar', ['cantidad' => $previa['turnos_sin_terminar']]) }} @endif
                        </p>
                    @endif

                    @if (! $sinTarifas && $previa['bruto'] > 0 && $lunes->lte(\App\Support\Region::hoy()))
                        <x-formulario :action="route('admin.nomina.store')" class="mt-4 !space-y-3">
                            <input type="hidden" name="user_id" value="{{ $persona->id }}">
                            <input type="hidden" name="semana" value="{{ $lunes->toDateString() }}">
                            @if ($saldoPescado > 0)
                                <label class="flex min-h-12 items-center gap-3 text-base text-tinta">
                                    <input type="checkbox" name="descontar_pescado" value="1" class="h-5 w-5 rounded accent-marca-600">
                                    {{ __('nomina.descontar_pescado', ['valor' => Formato::dinero(min($saldoPescado, $previa['bruto']))]) }}
                                </label>
                            @endif
                            <x-boton class="w-full sm:w-auto">{{ __('nomina.liquidar_semana') }}</x-boton>
                        </x-formulario>
                    @endif
                @endif
            </x-tarjeta>

            <x-modal id="tarifas-{{ $persona->id }}" :titulo="__('nomina.tarifas_de', ['nombre' => $persona->name])">
                <x-formulario :action="route('admin.nomina.tarifas', $persona)" metodo="PUT">
                    <div class="grid grid-cols-2 gap-4">
                        <x-campo nombre="valor_hora" id="hora-{{ $persona->id }}" :etiqueta="__('nomina.hora_ordinaria')" tipo="number" min="1" inputmode="numeric" :valor="$persona->valor_hora" required />
                        <x-campo nombre="valor_hora_extra" id="extra-{{ $persona->id }}" :etiqueta="__('nomina.hora_extra')" tipo="number" min="1" inputmode="numeric" :valor="$persona->valor_hora_extra" required />
                    </div>
                    <p class="text-sm text-tinta-suave">{{ __('nomina.valores_moneda_finca_sin_decimales') }}</p>
                    <div class="grid grid-cols-2 gap-3 pt-2">
                        <x-boton tipo="button" variante="secundario" data-cerrar-modal>{{ __('comun.cancelar') }}</x-boton>
                        <x-boton>{{ __('comun.guardar') }}</x-boton>
                    </div>
                </x-formulario>
            </x-modal>
        @endforeach
    </div>
@endsection
