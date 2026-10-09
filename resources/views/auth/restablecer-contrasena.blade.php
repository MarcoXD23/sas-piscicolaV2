@extends('layouts.app')

@section('titulo', __('acceso.recuperar.nueva_titulo'))

@section('contenido')
    <div class="mb-8 text-center">
        <x-logo class="mx-auto mb-5 h-20 w-20 rounded-2xl shadow-levantada ring-1 ring-white/20" />
        <h1 class="text-3xl font-bold tracking-tight text-white drop-shadow-sm">{{ __('acceso.recuperar.nueva_titulo') }}</h1>
    </div>

    <x-tarjeta class="!shadow-levantada !ring-0">
        <x-formulario :action="route('password.update')">
            <input type="hidden" name="token" value="{{ $token }}">
            <x-campo nombre="email" :etiqueta="__('acceso.correo')" tipo="email" :valor="old('email', $email)" autocomplete="username" inputmode="email" required />
            <x-campo nombre="password" :etiqueta="__('acceso.recuperar.nueva')" tipo="password" autocomplete="new-password" :ayuda="__('acceso.minimo_8_caracteres')" required autofocus />
            <x-campo nombre="password_confirmation" :etiqueta="__('acceso.confirma_contrasena')" tipo="password" autocomplete="new-password" required />

            <x-boton class="w-full">{{ __('acceso.recuperar.guardar') }}</x-boton>
        </x-formulario>
    </x-tarjeta>
@endsection
