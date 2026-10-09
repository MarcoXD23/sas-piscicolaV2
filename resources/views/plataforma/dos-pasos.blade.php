@extends('layouts.app')

@section('titulo', __('cuenta.dos_pasos.ingreso_titulo'))

@section('contenido')
    <div class="mb-8 text-center">
        <x-logo class="mx-auto mb-4 h-16 w-16 rounded-2xl shadow-lg ring-1 ring-white/20" />
        <h1 class="text-2xl font-bold text-white">{{ __('cuenta.dos_pasos.ingreso_titulo') }}</h1>
        <p class="mt-1 text-base text-marca-100">{{ __('plataforma.login.titulo') }}</p>
    </div>

    <x-tarjeta>
        <p class="mb-5 text-base text-slate-700">{{ __('cuenta.dos_pasos.ingreso_ayuda') }}</p>
        <x-formulario :action="route('plataforma.dos-pasos.store')">
            <x-campo nombre="codigo" :etiqueta="__('cuenta.dos_pasos.ingreso_codigo')" inputmode="numeric" autocomplete="one-time-code" maxlength="20" required autofocus />
            <x-boton class="w-full">{{ __('cuenta.dos_pasos.ingreso_entrar') }}</x-boton>
        </x-formulario>
        <p class="mt-4 text-center">
            <a href="{{ route('plataforma.login') }}" class="inline-flex min-h-12 items-center text-base font-medium text-marca-700 hover:underline">{{ __('cuenta.dos_pasos.ingreso_volver') }}</a>
        </p>
    </x-tarjeta>
@endsection
