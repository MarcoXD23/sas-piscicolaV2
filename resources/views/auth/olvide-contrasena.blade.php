@extends('layouts.app')

@section('titulo', __('acceso.recuperar.titulo'))

@section('contenido')
    <div class="mb-6 text-center">
        <x-logo class="mx-auto mb-4 h-14 w-14 rounded-2xl shadow-lg ring-1 ring-white/20" />
        <h1 class="text-2xl font-bold text-white">{{ __('acceso.recuperar.titulo') }}</h1>
        <p class="mt-1 text-base text-marca-100">{{ __('acceso.recuperar.subtitulo') }}</p>
    </div>

    <x-tarjeta>
        <x-formulario :action="route('password.email')">
            <x-campo nombre="email" :etiqueta="__('acceso.correo')" tipo="email" autocomplete="username" inputmode="email" required autofocus />

            <x-boton class="w-full">{{ __('acceso.recuperar.enviar_enlace') }}</x-boton>
        </x-formulario>
    </x-tarjeta>

    <p class="mt-6 text-center text-base text-marca-50">
        <a href="{{ route('login') }}" class="font-semibold text-white underline underline-offset-2">{{ __('acceso.recuperar.volver') }}</a>
    </p>
@endsection
