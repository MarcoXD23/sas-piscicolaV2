@extends('layouts.app')

@section('titulo', __('cuenta.dos_pasos.activar_titulo'))

@section('contenido')
    <x-encabezado :titulo="__('cuenta.dos_pasos.activar_titulo')" :subtitulo="__('cuenta.dos_pasos.que_es')" />

    <x-tarjeta class="max-w-xl">
        <p class="text-base font-semibold text-tinta">{{ __('cuenta.dos_pasos.paso_1') }}</p>
        {{-- Código QR en SVG generado en el servidor --}}
        <div class="mx-auto my-4 w-56 rounded-ficha bg-ficha p-3 ring-1 ring-renglon [&_svg]:h-auto [&_svg]:w-full">{!! $qr !!}</div>

        <p class="text-sm text-tinta-suave">{{ __('cuenta.dos_pasos.paso_1_manual') }}</p>
        <p class="mt-1 select-all break-all rounded-ficha bg-papel-hondo p-3 font-mono text-lg tracking-wider text-tinta">{{ $claveAgrupada }}</p>
        <p class="mt-2 text-sm text-atencion">{{ __('cuenta.dos_pasos.clave_privada') }}</p>

        <x-formulario :action="route('mi-cuenta.dos-pasos.confirmar')" class="mt-6 border-t border-renglon pt-5">
            <p class="text-base font-semibold text-tinta">{{ __('cuenta.dos_pasos.paso_2') }}</p>
            <x-campo nombre="codigo" :etiqueta="__('cuenta.dos_pasos.codigo')" inputmode="numeric" autocomplete="one-time-code" maxlength="6" required autofocus />
            <div class="grid grid-cols-2 gap-3">
                <x-boton variante="secundario" :href="route('mi-cuenta.index')">{{ __('comun.cancelar') }}</x-boton>
                <x-boton>{{ __('cuenta.dos_pasos.confirmar') }}</x-boton>
            </div>
        </x-formulario>
    </x-tarjeta>
@endsection
