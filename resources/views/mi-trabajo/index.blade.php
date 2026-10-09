@extends('layouts.app')

@section('titulo', __('mi_trabajo.mi_trabajo'))

@section('contenido')
    @php
        $numero = fn ($valor, $decimales = 1) => \App\Support\Formato::numero((float) $valor, $decimales);
        $estados = ['pendiente' => ['atencion', 'reloj'], 'aprobado' => ['bien', 'ok'], 'rechazado' => ['alerta', 'cerrar']];
    @endphp

    <x-encabezado :titulo="__('mi_trabajo.mi_trabajo')" :subtitulo="__('mi_trabajo.tus_tareas_permisos_horas_extra')">
        <x-boton tipo="button" variante="secundario" data-abrir-modal="modal-permiso">{{ __('mi_trabajo.pedir_permiso') }}</x-boton>
        <x-boton tipo="button" variante="secundario" data-abrir-modal="modal-horas">{{ __('mi_trabajo.horas_extra') }}</x-boton>
    </x-encabezado>

    @unless ($enTurno)
        <x-aviso tono="atencion" icono="reloj" class="mb-6">{{ __('mi_trabajo.estas_fuera_tu_turno_puedes') }}</x-aviso>
    @endunless

    <section class="mb-8">
        <h2 class="mb-4 text-seccion font-bold">{{ __('mi_trabajo.tareas_pendientes') }}</h2>

        @forelse ($pendientes as $tarea)
            <x-tarjeta class="mb-3">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <h3 class="text-lg font-bold">{{ $tarea->titulo }}</h3>
                        <p @class(['mt-0.5 flex flex-wrap items-center gap-1 text-sm', 'font-bold text-alerta' => $tarea->estaVencida(), 'text-tinta-suave' => ! $tarea->estaVencida()])>
                            @if ($tarea->estaVencida())<x-icono nombre="alerta" class="h-4 w-4" />@endif
                            {{ __($tarea->estaVencida() ? 'mi_trabajo.vencida' : 'mi_trabajo.para_el', ['fecha' => $tarea->fecha->format('d/m/Y')]) }}
                            @if ($tarea->lago) · {{ __('lagos.lago_nombre', ['lago' => $tarea->lago->nombre]) }} @endif
                        </p>
                        @if ($tarea->descripcion)
                            <p class="mt-2 max-w-[65ch] text-base">{{ $tarea->descripcion }}</p>
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
            <details class="group mt-4 rounded-ficha bg-ficha px-5 py-2 shadow-ficha ring-1 ring-renglon">
                <summary class="flex min-h-12 cursor-pointer list-none items-center justify-between gap-2 text-base font-semibold text-marca-700 [&::-webkit-details-marker]:hidden">{{ __('mi_trabajo.completadas_recientemente') }} <x-icono nombre="abajo" class="h-5 w-5 transition-transform group-open:rotate-180" /></summary>
                <ul class="mb-2 divide-y divide-renglon border-t border-renglon">
                    @foreach ($completadas as $tarea)
                        <li class="py-2.5">
                            <p class="font-bold">{{ $tarea->titulo }}</p>
                            <p class="text-sm text-tinta-suave">{{ $tarea->completada_at->format('d/m/Y H:i') }}@if ($tarea->nota_cierre) · {{ $tarea->nota_cierre }}@endif</p>
                        </li>
                    @endforeach
                </ul>
            </details>
        @endif
    </section>

    @if ($liquidaciones->isNotEmpty())
        <x-tarjeta :titulo="__('mi_trabajo.mis_pagos_nomina')" class="mb-6">
            @foreach ($liquidaciones as $liquidacion)
                <div class="flex items-start justify-between gap-3 border-t border-renglon py-3 first:border-t-0 first:pt-0">
                    <div>
                        <p class="font-bold">{{ __('comun.semana_del', ['desde' => $liquidacion->semana_inicio->format('d/m'), 'hasta' => $liquidacion->semana_fin->format('d/m/Y')]) }}</p>
                        <p class="text-sm text-tinta-suave">
                            {{ __('mi_trabajo.horas_liquidadas', ['turno' => $numero($liquidacion->horas_turnos), 'extra' => $numero($liquidacion->horas_extra)]) }}
                            @if ($liquidacion->descuento_pescado > 0) · {{ __('mi_trabajo.descuento_pescado', ['valor' => \App\Support\Formato::dinero($liquidacion->descuento_pescado)]) }} @endif
                        </p>
                    </div>
                    <div class="shrink-0 text-right">
                        <p class="text-lg font-bold tabular-nums">{{ \App\Support\Formato::dinero($liquidacion->total_pagar) }}</p>
                        <x-insignia :tono="$liquidacion->estaPagada() ? 'bien' : 'atencion'" :icono="$liquidacion->estaPagada() ? 'ok' : 'reloj'" class="mt-1">
                            {{ $liquidacion->estaPagada() ? __('mi_trabajo.pagada') : __('nomina.por_pagar') }}
                        </x-insignia>
                    </div>
                </div>
            @endforeach
        </x-tarjeta>
    @endif

    @if ($creditosPescado->isNotEmpty())
        <x-tarjeta :titulo="__('mi_trabajo.mi_saldo_pescado')" :subtitulo="__('mi_trabajo.debes', ['valor' => \App\Support\Formato::dinero($creditosPescado->sum('saldo'))])" class="mb-6">
            @foreach ($creditosPescado as $credito)
                <div class="flex justify-between gap-3 border-t border-renglon py-3 first:border-t-0 first:pt-0">
                    <span>{{ $credito->fecha->format('d/m/Y') }} · {{ $numero($credito->kg) }} kg</span>
                    <span class="font-bold tabular-nums">{{ \App\Support\Formato::dinero($credito->saldo) }}</span>
                </div>
            @endforeach
        </x-tarjeta>
    @endif

    <div class="grid gap-6 md:grid-cols-2">
        <x-tarjeta :titulo="__('mi_trabajo.mis_permisos')">
            @forelse ($permisos as $permiso)
                <div class="flex items-start justify-between gap-3 border-t border-renglon py-3 first:border-t-0 first:pt-0">
                    <div>
                        <p class="font-bold">{{ __('comun.rango_dias', ['desde' => $permiso->fecha_inicio->format('d/m'), 'hasta' => $permiso->fecha_fin->format('d/m/Y'), 'dias' => $permiso->dias()]) }}</p>
                        <p class="text-sm text-tinta-suave">{{ $permiso->motivo }}@if ($permiso->respuesta) · {{ __('mi_trabajo.respuesta', ['texto' => $permiso->respuesta]) }}@endif</p>
                    </div>
                    <x-insignia :tono="$estados[$permiso->estado][0]" :icono="$estados[$permiso->estado][1]" class="shrink-0">{{ $permiso->textoEstado() }}</x-insignia>
                </div>
            @empty
                <p class="text-base text-tinta-suave">{{ __('mi_trabajo.no_has_pedido_permisos') }}</p>
            @endforelse
        </x-tarjeta>

        <x-tarjeta :titulo="__('mi_trabajo.mis_horas_extra')" :subtitulo="__('mi_trabajo.aprobadas_mes', ['horas' => $numero($horasAprobadasMes)])">
            @forelse ($horasExtras as $hora)
                <div class="flex items-start justify-between gap-3 border-t border-renglon py-3 first:border-t-0 first:pt-0">
                    <div>
                        <p class="font-bold">{{ $hora->fecha->format('d/m/Y') }} · {{ __('comun.horas_corto', ['horas' => $numero($hora->horas)]) }}</p>
                        <p class="text-sm text-tinta-suave">{{ $hora->motivo }}@if ($hora->respuesta) · {{ __('mi_trabajo.respuesta', ['texto' => $hora->respuesta]) }}@endif</p>
                    </div>
                    <x-insignia :tono="$estados[$hora->estado][0]" :icono="$estados[$hora->estado][1]" class="shrink-0">{{ $hora->textoEstado() }}</x-insignia>
                </div>
            @empty
                <p class="text-base text-tinta-suave">{{ __('mi_trabajo.no_has_reportado_horas_extra') }}</p>
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
