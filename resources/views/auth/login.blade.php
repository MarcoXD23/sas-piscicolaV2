@extends('layouts.app')

@section('titulo', 'Ingresar')

@section('contenido')
    <div class="mb-8 text-center">
        <x-logo class="mx-auto mb-4 h-20 w-20 rounded-3xl shadow-lg ring-1 ring-white/20" />
        <h1 class="text-2xl font-bold text-white">{{ config('app.name') }}</h1>
        <p class="mt-1 text-base text-marca-100">Gestión de tu finca piscícola</p>
    </div>

    <x-tarjeta>
        <x-formulario :action="route('login')">
            <x-campo nombre="email" etiqueta="Correo" tipo="email" autocomplete="username" inputmode="email" required autofocus />
            <x-campo nombre="password" etiqueta="Contraseña" tipo="password" autocomplete="current-password" required />

            <label class="flex min-h-11 items-center gap-3 text-base text-slate-700">
                <input type="checkbox" name="recordarme" value="1" class="h-5 w-5 rounded border-slate-300 accent-marca-600">
                Mantener la sesión iniciada
            </label>

            <x-boton class="w-full">Ingresar</x-boton>
        </x-formulario>
    </x-tarjeta>

    <p class="mt-6 text-center text-base text-marca-50">
        ¿Eres nuevo en la finca?
        <a href="{{ route('registro') }}" class="font-semibold text-white underline underline-offset-2">Solicita acceso</a>
    </p>
@endsection

