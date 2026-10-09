@extends('layouts.app')

@section('titulo', __('plataforma.login.titulo'))

@section('contenido')
    <div class="mb-8 text-center">
        <x-logo class="mx-auto mb-5 h-20 w-20 rounded-2xl shadow-levantada ring-1 ring-white/20" />
        <h1 class="text-3xl font-bold tracking-tight text-white drop-shadow-sm">{{ __('plataforma.login.titulo') }}</h1>
        <p class="mt-2 text-base text-marca-100">{{ __('plataforma.login.subtitulo') }}</p>
    </div>

    <x-tarjeta class="!shadow-levantada !ring-0">
        <x-formulario :action="route('plataforma.login')">
            <x-campo nombre="email" :etiqueta="__('acceso.correo')" tipo="email" autocomplete="username" inputmode="email" required autofocus />
            <x-campo nombre="password" :etiqueta="__('acceso.contrasena')" tipo="password" autocomplete="current-password" required />

            <x-boton class="w-full">{{ __('acceso.ingresar') }}</x-boton>
        </x-formulario>
    </x-tarjeta>
@endsection
