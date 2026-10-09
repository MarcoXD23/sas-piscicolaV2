@extends('layouts.app')

@section('titulo', __('acceso.ingresar'))

@section('contenido')
    <div class="mb-8 text-center">
        <x-logo class="mx-auto mb-5 h-20 w-20 rounded-2xl shadow-levantada ring-1 ring-white/20" />
        <h1 class="text-3xl font-bold tracking-tight text-white drop-shadow-sm">{{ config('app.name') }}</h1>
        <p class="mt-2 text-base text-marca-100">{{ __('acceso.gestion_tu_finca_piscicola') }}</p>
    </div>

    <x-tarjeta class="!shadow-levantada !ring-0">
        <x-formulario :action="route('login')">
            <x-campo nombre="email" :etiqueta="__('acceso.correo')" tipo="email" autocomplete="username" inputmode="email" required autofocus />
            <x-campo nombre="password" :etiqueta="__('acceso.contrasena')" tipo="password" autocomplete="current-password" required />

            <label class="flex min-h-12 items-center gap-3 text-base text-tinta">
                <input type="checkbox" name="recordarme" value="1" class="h-5 w-5 rounded border-renglon-fuerte accent-marca-600">
                {{ __('acceso.mantener_sesion_iniciada') }}
            </label>

            <x-boton class="w-full">{{ __('acceso.ingresar') }}</x-boton>
        </x-formulario>

        <p class="mt-4 text-center">
            <a href="{{ route('password.request') }}" class="inline-flex min-h-12 items-center text-base font-medium text-marca-700 hover:underline">{{ __('acceso.recuperar.enlace_login') }}</a>
        </p>
    </x-tarjeta>

    <p class="mt-6 text-center text-base text-marca-50">
        {{ __('acceso.eres_nuevo_finca') }}
        <a href="{{ route('registro') }}" class="font-semibold text-white underline decoration-marca-300/60 underline-offset-4 transition-colors hover:decoration-white">{{ __('acceso.solicita_acceso') }}</a>
    </p>
    <p class="mt-4 text-center text-sm text-marca-100">
        <a href="{{ route('legal.terminos') }}" class="underline decoration-marca-300/50 underline-offset-4 transition-colors hover:text-white">{{ __('legal.terminos') }}</a>
        ·
        <a href="{{ route('legal.privacidad') }}" class="underline decoration-marca-300/50 underline-offset-4 transition-colors hover:text-white">{{ __('legal.privacidad') }}</a>
    </p>
@endsection

