@extends('layouts.app')

@section('titulo', __('cuenta.dos_pasos.activar_titulo'))

@section('contenido')
    <x-encabezado :titulo="__('cuenta.dos_pasos.activar_titulo')" :subtitulo="__('cuenta.dos_pasos.que_es')" />

    <x-tarjeta class="max-w-xl">
        <p class="text-base font-semibold text-slate-900">{{ __('cuenta.dos_pasos.paso_1') }}</p>
        {{-- Código QR en SVG generado en el servidor --}}
        <div class="mx-auto my-4 w-56 rounded-xl bg-white p-3 ring-1 ring-slate-200 [&_svg]:h-auto [&_svg]:w-full">{!! $qr !!}</div>

        <p class="text-sm text-slate-600">{{ __('cuenta.dos_pasos.paso_1_manual') }}</p>
        <p class="mt-1 select-all break-all rounded-xl bg-slate-100 p-3 font-mono text-lg tracking-wider text-slate-900">{{ $claveAgrupada }}</p>
        <p class="mt-2 text-sm text-amber-800">{{ __('cuenta.dos_pasos.clave_privada') }}</p>

        <x-formulario :action="route('mi-cuenta.dos-pasos.confirmar')" class="mt-6 border-t border-slate-200 pt-5">
            <p class="text-base font-semibold text-slate-900">{{ __('cuenta.dos_pasos.paso_2') }}</p>
            <x-campo nombre="codigo" :etiqueta="__('cuenta.dos_pasos.codigo')" inputmode="numeric" autocomplete="one-time-code" maxlength="6" required autofocus />
            <div class="grid grid-cols-2 gap-3">
                <x-boton variante="secundario" :href="route('mi-cuenta.index')">{{ __('comun.cancelar') }}</x-boton>
                <x-boton>{{ __('cuenta.dos_pasos.confirmar') }}</x-boton>
            </div>
        </x-formulario>
    </x-tarjeta>
@endsection
