@extends('layouts.app')

@section('titulo', __('plataforma.login.titulo'))

@section('contenido')
    <div class="mb-8 text-center">
        <x-logo class="mx-auto mb-4 h-16 w-16 rounded-2xl shadow-lg ring-1 ring-white/20" />
        <h1 class="text-2xl font-bold text-white">{{ __('plataforma.login.titulo') }}</h1>
        <p class="mt-1 text-base text-marca-100">{{ __('plataforma.login.subtitulo') }}</p>
    </div>

    <x-tarjeta>
        <x-formulario :action="route('plataforma.login')">
            <x-campo nombre="email" :etiqueta="__('acceso.correo')" tipo="email" autocomplete="username" inputmode="email" required autofocus />
            <x-campo nombre="password" :etiqueta="__('acceso.contrasena')" tipo="password" autocomplete="current-password" required />

            <x-boton class="w-full">{{ __('acceso.ingresar') }}</x-boton>
        </x-formulario>
    </x-tarjeta>
@endsection
