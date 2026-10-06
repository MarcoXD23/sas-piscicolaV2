@extends('layouts.app')

@section('titulo', 'Ingresar')

@section('contenido')
    <div class="mb-8 text-center">
        <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-2xl bg-marca-600 shadow-md">
            <x-logo class="h-10 w-10 text-white" />
        </div>
        <h1 class="text-2xl font-bold text-slate-900">{{ config('app.name') }}</h1>
        <p class="mt-1 text-base text-slate-500">Gestión de tu finca piscícola</p>
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

    <p class="mt-6 text-center text-base text-slate-600">
        ¿Eres nuevo en la finca?
        <a href="{{ route('registro') }}" class="font-semibold text-marca-700 underline-offset-2 hover:underline">Solicita acceso</a>
    </p>
@endsection

