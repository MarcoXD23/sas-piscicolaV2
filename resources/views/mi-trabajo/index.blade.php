@extends('layouts.app')

@section('titulo', __('mi_trabajo.mi_trabajo'))

@section('contenido')
    @php
        $numero = fn ($valor, $decimales = 1) => \App\Support\Formato::numero((float) $valor, $decimales);
        $chips = ['pendiente' => 'bg-amber-100 text-amber-900', 'aprobado' => 'bg-marca-100 text-marca-800', 'rechazado' => 'bg-red-100 text-red-800'];
    @endphp

    <x-encabezado :titulo="__('mi_trabajo.mi_trabajo')" :subtitulo="__('mi_trabajo.tus_tareas_permisos_horas_extra')">
        <x-boton tipo="button" variante="secundario" data-abrir-modal="modal-permiso">{{ __('mi_trabajo.pedir_permiso') }}</x-boton>
        <x-boton tipo="button" variante="secundario" data-abrir-modal="modal-horas">{{ __('mi_trabajo.horas_extra') }}</x-boton>
    </x-encabezado>

    @unless ($enTurno)
        <div role="status" class="mb-5 flex items-start gap-3 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-base text-amber-900">
            <x-icono nombre="reloj" class="mt-0.5 h-5 w-5" />
            <span>{{ __('mi_trabajo.estas_fuera_tu_turno_puedes') }}</span>
        </div>
    @endunless

    <section class="mb-8">
        <h2 class="mb-3 text-lg font-semibold text-slate-900">{{ __('mi_trabajo.tareas_pendientes') }}</h2>

        @forelse ($pendientes as $tarea)
            <x-tarjeta class="mb-3">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <h3 class="text-lg font-semibold text-slate-900">{{ $tarea->titulo }}</h3>
                        <p @class(['text-sm', 'font-semibold text-red-700' => $tarea->estaVencida(), 'text-slate-500' => ! $tarea->estaVencida()])>
                            {{ __($tarea->estaVencida() ? 'mi_trabajo.vencida' : 'mi_trabajo.para_el', ['fecha' => $tarea->fecha->format('d/m/Y')]) }}
                            @if ($tarea->lago) · {{ __('lagos.lago_nombre', ['lago' => $tarea->lago->nombre]) }} @endif
                        </p>
                        @if ($tarea->descripcion)
                            <p class="mt-2 text-base text-slate-700">{{ $tarea->descripcion }}</p>
                        @endif
                    </div>
                </div>

                <x-boton tipo="button" class="mt-4 w-full" data-abrir-modal="modal-completar-{{ $tarea->id }}" :disabled="! $enTurno">
                    <x-icono nombre="ok" class="h-5 w-5" /> {{ __('mi_trabajo.marcar_como_hecha') }}
                </x-boton>
            </x-tarjeta>

            @if ($enTurno)
                <x-modal id="modal-completar-{{ $tarea->id }}" titulo="{{ $tarea->titulo }}">
                    <x-formulario :action="route('mi-trabajo.tareas.completar', $tarea)">
                        <x-campo nombre="nota_cierre" id="nota-{{ $tarea->id }}" :etiqueta="__('mi_trabajo.nota_opcional')" :placeholder="__('mi_trabajo.ej_cambio_malla_lago')" />
                        <div class="grid grid-cols-2 gap-3 pt-2">
                            <x-boton tipo="button" variante="secundario" data-cerrar-modal>{{ __('comun.cancelar') }}</x-boton>
                            <x-boton>{{ __('mi_trabajo.hecha') }}</x-boton>
                        </div>
                    </x-formulario>
                </x-modal>
            @endif
        @empty
            <x-vacio icono="tareas" :mensaje="__('mi_trabajo.no_tienes_tareas_pendientes')" />
        @endforelse

        @if ($completadas->isNotEmpty())
            <details class="mt-4 rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200">
                <summary class="min-h-11 cursor-pointer content-center text-base font-semibold text-marca-700">{{ __('mi_trabajo.completadas_recientemente') }}</summary>
                <ul class="mt-2 divide-y divide-slate-100">
                    @foreach ($completadas as $tarea)
                        <li class="py-2.5">
                            <p class="font-medium text-slate-900">{{ $tarea->titulo }}</p>
                            <p class="text-sm text-slate-500">{{ $tarea->completada_at->format('d/m/Y H:i') }}@if ($tarea->nota_cierre) · {{ $tarea->nota_cierre }}@endif</p>
                        </li>
                    @endforeach
                </ul>
            </details>
        @endif
    </section>

    @if ($liquidaciones->isNotEmpty())
        <x-tarjeta :titulo="__('mi_trabajo.mis_pagos_nomina')" class="mb-6">
            @foreach ($liquidaciones as $liquidacion)
                <div class="flex items-start justify-between gap-3 border-t border-slate-100 py-2.5 first:border-t-0 first:pt-0">
                    <div>
                        <p class="font-medium text-slate-900">{{ __('comun.semana_del', ['desde' => $liquidacion->semana_inicio->format('d/m'), 'hasta' => $liquidacion->semana_fin->format('d/m/Y')]) }}</p>
                        <p class="text-sm text-slate-500">
                            {{ __('mi_trabajo.horas_liquidadas', ['turno' => $numero($liquidacion->horas_turnos), 'extra' => $numero($liquidacion->horas_extra)]) }}
                            @if ($liquidacion->descuento_pescado > 0) · {{ __('mi_trabajo.descuento_pescado', ['valor' => \App\Support\Formato::dinero($liquidacion->descuento_pescado)]) }} @endif
                        </p>
                    </div>
                    <div class="shrink-0 text-right">
                        <p class="font-bold tabular-nums">{{ \App\Support\Formato::dinero($liquidacion->total_pagar) }}</p>
                        <p @class(['text-xs font-semibold', 'text-marca-700' => $liquidacion->estaPagada(), 'text-amber-700' => ! $liquidacion->estaPagada()])>
                            {{ $liquidacion->estaPagada() ? __('mi_trabajo.pagada') : __('nomina.por_pagar') }}
                        </p>
                    </div>
                </div>
            @endforeach
        </x-tarjeta>
    @endif

    @if ($creditosPescado->isNotEmpty())
        <x-tarjeta :titulo="__('mi_trabajo.mi_saldo_pescado')" :subtitulo="__('mi_trabajo.debes', ['valor' => \App\Support\Formato::dinero($creditosPescado->sum('saldo'))])" class="mb-6">
            @foreach ($creditosPescado as $credito)
                <div class="flex justify-between gap-3 border-t border-slate-100 py-2.5 first:border-t-0 first:pt-0">
                    <span>{{ $credito->fecha->format('d/m/Y') }} · {{ $numero($credito->kg) }} kg</span>
                    <span class="font-semibold tabular-nums">{{ \App\Support\Formato::dinero($credito->saldo) }}</span>
                </div>
            @endforeach
        </x-tarjeta>
    @endif

    <div class="grid gap-6 md:grid-cols-2">
        <x-tarjeta :titulo="__('mi_trabajo.mis_permisos')">
            @forelse ($permisos as $permiso)
                <div class="flex items-start justify-between gap-3 border-t border-slate-100 py-3 first:border-t-0 first:pt-0">
                    <div>
                        <p class="font-medium text-slate-900">{{ __('comun.rango_dias', ['desde' => $permiso->fecha_inicio->format('d/m'), 'hasta' => $permiso->fecha_fin->format('d/m/Y'), 'dias' => $permiso->dias()]) }}</p>
                        <p class="text-sm text-slate-500">{{ $permiso->motivo }}@if ($permiso->respuesta) · {{ __('mi_trabajo.respuesta', ['texto' => $permiso->respuesta]) }}@endif</p>
                    </div>
                    <span class="shrink-0 rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $chips[$permiso->estado] }}">{{ $permiso->textoEstado() }}</span>
                </div>
            @empty
                <p class="text-base text-slate-500">{{ __('mi_trabajo.no_has_pedido_permisos') }}</p>
            @endforelse
        </x-tarjeta>

        <x-tarjeta :titulo="__('mi_trabajo.mis_horas_extra')" :subtitulo="__('mi_trabajo.aprobadas_mes', ['horas' => $numero($horasAprobadasMes)])">
            @forelse ($horasExtras as $hora)
                <div class="flex items-start justify-between gap-3 border-t border-slate-100 py-3 first:border-t-0 first:pt-0">
                    <div>
                        <p class="font-medium text-slate-900">{{ $hora->fecha->format('d/m/Y') }} · {{ __('comun.horas_corto', ['horas' => $numero($hora->horas)]) }}</p>
                        <p class="text-sm text-slate-500">{{ $hora->motivo }}@if ($hora->respuesta) · {{ __('mi_trabajo.respuesta', ['texto' => $hora->respuesta]) }}@endif</p>
                    </div>
                    <span class="shrink-0 rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $chips[$hora->estado] }}">{{ $hora->textoEstado() }}</span>
                </div>
            @empty
                <p class="text-base text-slate-500">{{ __('mi_trabajo.no_has_reportado_horas_extra') }}</p>
            @endforelse
        </x-tarjeta>
    </div>

    <x-modal id="modal-permiso" :titulo="__('mi_trabajo.pedir_permiso')">
        <x-formulario :action="route('mi-trabajo.permisos.store')">
            <div class="grid grid-cols-2 gap-4">
                <x-campo nombre="fecha_inicio" :etiqueta="__('comun.desde')" tipo="date" :min="\App\Support\Region::hoy()->toDateString()" required />
                <x-campo nombre="fecha_fin" :etiqueta="__('comun.hasta')" tipo="date" :min="\App\Support\Region::hoy()->toDateString()" required />
            </div>
            <x-campo nombre="motivo" id="permiso-motivo" :etiqueta="__('mi_trabajo.motivo')" :placeholder="__('mi_trabajo.ej_cita_medica')" required />
            <div class="grid grid-cols-2 gap-3 pt-2">
                <x-boton tipo="button" variante="secundario" data-cerrar-modal>{{ __('comun.cancelar') }}</x-boton>
                <x-boton>{{ __('mi_trabajo.enviar') }}</x-boton>
            </div>
        </x-formulario>
    </x-modal>

    <x-modal id="modal-horas" :titulo="__('mi_trabajo.reportar_horas_extra')">
        <x-formulario :action="route('mi-trabajo.horas-extras.store')">
            <div class="grid grid-cols-2 gap-4">
                <x-campo nombre="fecha" :etiqueta="__('comun.fecha')" tipo="date" :valor="\App\Support\Region::hoy()->toDateString()" :max="\App\Support\Region::hoy()->toDateString()" required />
                <x-campo nombre="horas" :etiqueta="__('mi_trabajo.horas')" tipo="number" step="0.5" min="0.5" max="12" inputmode="decimal" required />
            </div>
            <x-campo nombre="motivo" id="horas-motivo" :etiqueta="__('mi_trabajo.trabajaste')" :placeholder="__('mi_trabajo.ej_cosecha_lago_l02')" required />
            <div class="grid grid-cols-2 gap-3 pt-2">
                <x-boton tipo="button" variante="secundario" data-cerrar-modal>{{ __('comun.cancelar') }}</x-boton>
                <x-boton>{{ __('mi_trabajo.enviar') }}</x-boton>
            </div>
        </x-formulario>
    </x-modal>
@endsection
