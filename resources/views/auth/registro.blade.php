@extends('layouts.app')

@section('titulo', 'Solicitar acceso')

@section('contenido')
    <div class="mb-6 text-center">
        <h1 class="text-2xl font-bold text-slate-900">Solicitar acceso</h1>
        <p class="mt-1 text-base text-slate-500">El propietario de la finca aprobará tu cuenta y te asignará tu rol.</p>
    </div>

    <x-tarjeta>
        <x-formulario :action="route('registro')">
            <x-campo nombre="codigo_finca" etiqueta="Código de la finca" autocomplete="off" autocapitalize="none" required
                     ayuda="Pídeselo al propietario de la finca." />
            <x-campo nombre="name" etiqueta="Nombre completo" autocomplete="name" required />
            <x-campo nombre="email" etiqueta="Correo" tipo="email" autocomplete="email" inputmode="email" required />
            <x-campo nombre="password" etiqueta="Contraseña" tipo="password" autocomplete="new-password" required
                     ayuda="Mínimo 8 caracteres." />
            <x-campo nombre="password_confirmation" etiqueta="Confirma la contraseña" tipo="password" autocomplete="new-password" required />

            <x-boton class="w-full">Enviar solicitud</x-boton>
        </x-formulario>
    </x-tarjeta>

    <p class="mt-6 text-center text-base text-slate-600">
        ¿Ya tienes cuenta?
        <a href="{{ route('login') }}" class="font-semibold text-marca-700 underline-offset-2 hover:underline">Ingresar</a>
    </p>
@endsection

