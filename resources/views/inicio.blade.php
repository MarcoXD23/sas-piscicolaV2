@extends('layouts.app')

@section('titulo', 'Inicio')

@section('contenido')
    <h1 class="mb-1 text-2xl font-bold text-slate-900">Hola, {{ $usuario->name }}</h1>
    <p class="mb-6 text-base text-slate-500">{{ $usuario->tenant->nombre }}</p>

    <x-tarjeta titulo="Tus roles" class="mb-6">
        <ul class="flex flex-wrap gap-2">
            @foreach ($usuario->roles as $rol)
                <li class="rounded-full bg-marca-100 px-3 py-1 text-sm font-semibold capitalize text-marca-800">{{ $rol->nombre }}</li>
            @endforeach
        </ul>
    </x-tarjeta>

    @if($usuario->tieneRol('propietario') || $usuario->tieneRol('administrador'))
        <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
            <x-tarjeta>
                <h3 class="text-sm font-medium text-slate-500">Biomasa Total</h3>
                <p class="mt-2 text-3xl font-bold text-slate-900">{{ number_format($biomasaTotal, 2, ',', '.') }} <span class="text-lg text-slate-500">kg</span></p>
            </x-tarjeta>
            
            <x-tarjeta>
                <h3 class="text-sm font-medium text-slate-500">Total Peces Vivos</h3>
                <p class="mt-2 text-3xl font-bold text-slate-900">{{ number_format($pecesVivos, 0, ',', '.') }}</p>
            </x-tarjeta>

            <x-tarjeta>
                <h3 class="text-sm font-medium text-slate-500">Lagos Activos</h3>
                <p class="mt-2 text-3xl font-bold text-slate-900">{{ $lagosActivos }}</p>
            </x-tarjeta>
        </div>
    @endif
@endsection

