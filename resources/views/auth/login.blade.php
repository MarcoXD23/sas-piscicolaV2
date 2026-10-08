@extends('layouts.app')

@section('titulo', __('acceso.ingresar'))

@section('contenido')
    <div class="mb-8 text-center">
        <x-logo class="mx-auto mb-4 h-20 w-20 rounded-3xl shadow-lg ring-1 ring-white/20" />
        <h1 class="text-2xl font-bold text-white">{{ config('app.name') }}</h1>
        <p class="mt-1 text-base text-marca-100">{{ __('acceso.gestion_tu_finca_piscicola') }}</p>
    </div>

    <x-tarjeta>
        <x-formulario :action="route('login')">
            <x-campo nombre="email" :etiqueta="__('acceso.correo')" tipo="email" autocomplete="username" inputmode="email" required autofocus />
            <x-campo nombre="password" :etiqueta="__('acceso.contrasena')" tipo="password" autocomplete="current-password" required />

            <label class="flex min-h-11 items-center gap-3 text-base text-slate-700">
                <input type="checkbox" name="recordarme" value="1" class="h-5 w-5 rounded border-slate-300 accent-marca-600">
                {{ __('acceso.mantener_sesion_iniciada') }}
            </label>

            <x-boton class="w-full">{{ __('acceso.ingresar') }}</x-boton>
        </x-formulario>

        <p class="mt-4 text-center">
            <a href="{{ route('password.request') }}" class="inline-flex min-h-11 items-center text-base font-medium text-marca-700 hover:underline">{{ __('acceso.recuperar.enlace_login') }}</a>
        </p>
    </x-tarjeta>

    <p class="mt-6 text-center text-base text-marca-50">
        {{ __('acceso.eres_nuevo_finca') }}
        <a href="{{ route('registro') }}" class="font-semibold text-white underline underline-offset-2">{{ __('acceso.solicita_acceso') }}</a>
    </p>
    <p class="mt-4 text-center text-sm text-marca-100">
        <a href="{{ route('legal.terminos') }}" class="underline underline-offset-2">{{ __('legal.terminos') }}</a>
        ·
        <a href="{{ route('legal.privacidad') }}" class="underline underline-offset-2">{{ __('legal.privacidad') }}</a>
    </p>
@endsection

