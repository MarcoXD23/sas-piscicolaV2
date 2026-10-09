@extends('layouts.app')

@section('titulo', __('acceso.recuperar.nueva_titulo'))

@section('contenido')
    <div class="mb-8 text-center">
        <div class="relative mx-auto mb-5 h-20 w-20">
            <span class="absolute -inset-2 rounded-[2rem] bg-marca-300/40 blur-xl" aria-hidden="true"></span>
            <x-logo class="relative h-20 w-20 rounded-3xl shadow-2xl ring-1 ring-white/30" />
        </div>
        <h1 class="text-3xl font-bold tracking-tight text-white drop-shadow-sm">{{ __('acceso.recuperar.nueva_titulo') }}</h1>
    </div>

    <x-tarjeta class="!bg-white/90 !shadow-2xl !shadow-marca-950/40 !ring-white/40 backdrop-blur-xl">
        <x-formulario :action="route('password.update')">
            <input type="hidden" name="token" value="{{ $token }}">
            <x-campo nombre="email" :etiqueta="__('acceso.correo')" tipo="email" :valor="old('email', $email)" autocomplete="username" inputmode="email" required />
            <x-campo nombre="password" :etiqueta="__('acceso.recuperar.nueva')" tipo="password" autocomplete="new-password" :ayuda="__('acceso.minimo_8_caracteres')" required autofocus />
            <x-campo nombre="password_confirmation" :etiqueta="__('acceso.confirma_contrasena')" tipo="password" autocomplete="new-password" required />

            <x-boton class="w-full">{{ __('acceso.recuperar.guardar') }}</x-boton>
        </x-formulario>
    </x-tarjeta>
@endsection
