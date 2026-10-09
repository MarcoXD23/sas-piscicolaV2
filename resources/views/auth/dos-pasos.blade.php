@extends('layouts.app')

@section('titulo', __('cuenta.dos_pasos.ingreso_titulo'))

@section('contenido')
    <div class="mb-8 text-center">
        <x-logo class="mx-auto mb-5 h-20 w-20 rounded-2xl shadow-levantada ring-1 ring-white/20" />
        <h1 class="text-3xl font-bold tracking-tight text-white drop-shadow-sm">{{ __('cuenta.dos_pasos.ingreso_titulo') }}</h1>
    </div>

    <x-tarjeta class="!shadow-levantada !ring-0">
        <p class="mb-5 text-base text-tinta">{{ __('cuenta.dos_pasos.ingreso_ayuda') }}</p>
        <x-formulario :action="route('dos-pasos.store')">
            <x-campo nombre="codigo" :etiqueta="__('cuenta.dos_pasos.ingreso_codigo')" inputmode="numeric" autocomplete="one-time-code" maxlength="20" required autofocus />
            <x-boton class="w-full">{{ __('cuenta.dos_pasos.ingreso_entrar') }}</x-boton>
        </x-formulario>
        <p class="mt-4 text-center">
            <a href="{{ route('login') }}" class="inline-flex min-h-12 items-center text-base font-medium text-marca-700 hover:underline">{{ __('cuenta.dos_pasos.ingreso_volver') }}</a>
        </p>
    </x-tarjeta>
@endsection
