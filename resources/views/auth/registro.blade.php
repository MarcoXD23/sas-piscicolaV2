@extends('layouts.app')

@section('titulo', 'Solicitar acceso')

@section('contenido')
    <div class="mb-6 text-center">
        <x-logo class="mx-auto mb-4 h-14 w-14 rounded-2xl shadow-lg ring-1 ring-white/20" />
        <h1 class="text-2xl font-bold text-white">Solicitar acceso</h1>
        <p class="mt-1 text-base text-marca-100">El propietario de la finca aprobará tu cuenta y te asignará tu rol.</p>
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

    <p class="mt-6 text-center text-base text-marca-50">
        ¿Ya tienes cuenta?
        <a href="{{ route('login') }}" class="font-semibold text-white underline underline-offset-2">Ingresar</a>
    </p>
@endsection

