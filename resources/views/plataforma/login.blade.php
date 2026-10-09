@extends('layouts.app')

@section('titulo', __('plataforma.login.titulo'))

@section('contenido')
    <div class="mb-8 text-center">
        <div class="relative mx-auto mb-5 h-20 w-20">
            <span class="absolute -inset-2 rounded-[2rem] bg-marca-300/40 blur-xl" aria-hidden="true"></span>
            <x-logo class="relative h-20 w-20 rounded-3xl shadow-2xl ring-1 ring-white/30" />
        </div>
        <h1 class="text-3xl font-bold tracking-tight text-white drop-shadow-sm">{{ __('plataforma.login.titulo') }}</h1>
        <p class="mt-2 text-base text-marca-100">{{ __('plataforma.login.subtitulo') }}</p>
    </div>

    <x-tarjeta class="!bg-white/90 !shadow-2xl !shadow-marca-950/40 !ring-white/40 backdrop-blur-xl">
        <x-formulario :action="route('plataforma.login')">
            <x-campo nombre="email" :etiqueta="__('acceso.correo')" tipo="email" autocomplete="username" inputmode="email" required autofocus />
            <x-campo nombre="password" :etiqueta="__('acceso.contrasena')" tipo="password" autocomplete="current-password" required />

            <x-boton class="w-full">{{ __('acceso.ingresar') }}</x-boton>
        </x-formulario>
    </x-tarjeta>
@endsection
