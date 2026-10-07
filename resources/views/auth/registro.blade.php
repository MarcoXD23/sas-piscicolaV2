@extends('layouts.app')

@section('titulo', __('acceso.solicitar_acceso'))

@section('contenido')
    <div class="mb-6 text-center">
        <x-logo class="mx-auto mb-4 h-14 w-14 rounded-2xl shadow-lg ring-1 ring-white/20" />
        <h1 class="text-2xl font-bold text-white">{{ __('acceso.solicitar_acceso') }}</h1>
        <p class="mt-1 text-base text-marca-100">{{ __('acceso.propietario_finca_aprobara_tu_cuenta') }}</p>
    </div>

    <x-tarjeta>
        @error('registro')
            <div role="alert" class="mb-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-base text-red-800">
                <p>{{ $message }}</p>
                <a href="{{ route('password.request') }}" class="mt-1 inline-flex min-h-11 items-center font-semibold underline underline-offset-2">{{ __('acceso.recuperar.enlace_login') }}</a>
            </div>
        @enderror
        <x-formulario :action="route('registro')">
            <x-campo nombre="codigo_finca" :etiqueta="__('acceso.codigo_finca')" autocomplete="off" autocapitalize="none" required
                     :ayuda="__('acceso.pideselo_propietario_finca')" />
            <x-campo nombre="name" :etiqueta="__('acceso.nombre_completo')" autocomplete="name" required />
            <x-campo nombre="email" :etiqueta="__('acceso.correo')" tipo="email" autocomplete="email" inputmode="email" required />
            <x-campo nombre="password" :etiqueta="__('acceso.contrasena')" tipo="password" autocomplete="new-password" required
                     :ayuda="__('acceso.minimo_8_caracteres')" />
            <x-campo nombre="password_confirmation" :etiqueta="__('acceso.confirma_contrasena')" tipo="password" autocomplete="new-password" required />

            <x-boton class="w-full">{{ __('acceso.enviar_solicitud') }}</x-boton>
        </x-formulario>
    </x-tarjeta>

    <p class="mt-6 text-center text-base text-marca-50">
        {{ __('acceso.ya_tienes_cuenta') }}
        <a href="{{ route('login') }}" class="font-semibold text-white underline underline-offset-2">{{ __('acceso.ingresar') }}</a>
    </p>
@endsection

