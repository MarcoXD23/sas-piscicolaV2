@extends('layouts.app')

@section('titulo', __('acceso.recuperar.titulo'))

@section('contenido')
    <div class="mb-8 text-center">
        <x-logo class="mx-auto mb-5 h-20 w-20 rounded-2xl shadow-levantada ring-1 ring-white/20" />
        <h1 class="text-3xl font-bold tracking-tight text-white drop-shadow-sm">{{ __('acceso.recuperar.titulo') }}</h1>
        <p class="mt-2 text-base text-marca-100">{{ __('acceso.recuperar.subtitulo') }}</p>
    </div>

    <x-tarjeta class="!shadow-levantada !ring-0">
        <x-formulario :action="route('password.email')">
            <x-campo nombre="email" :etiqueta="__('acceso.correo')" tipo="email" autocomplete="username" inputmode="email" required autofocus />

            <x-boton class="w-full">{{ __('acceso.recuperar.enviar_enlace') }}</x-boton>
        </x-formulario>
    </x-tarjeta>

    <p class="mt-6 text-center text-base text-marca-50">
        <a href="{{ route('login') }}" class="font-semibold text-white underline decoration-marca-300/60 underline-offset-4 transition-colors hover:decoration-white">{{ __('acceso.recuperar.volver') }}</a>
    </p>
@endsection
