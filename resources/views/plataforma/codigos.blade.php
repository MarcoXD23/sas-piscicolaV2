@extends('layouts.app')

@section('titulo', __('cuenta.dos_pasos.codigos_titulo'))

@section('contenido')
    <x-encabezado :titulo="__('cuenta.dos_pasos.codigos_titulo')" />

    <x-tarjeta class="max-w-xl">
        <p class="mb-4 rounded-xl bg-amber-50 p-4 text-base text-amber-900">{{ __('cuenta.dos_pasos.codigos_ayuda') }}</p>
        <ol class="grid grid-cols-2 gap-2 font-mono text-lg text-slate-900">
            @foreach ($codigos as $codigo)
                <li class="select-all rounded-lg bg-slate-100 px-3 py-2 text-center tracking-wider">{{ $codigo }}</li>
            @endforeach
        </ol>
        <x-boton class="mt-6 w-full" :href="route('plataforma.fincas.index')">{{ __('plataforma.dos_pasos.codigos_listo') }}</x-boton>
    </x-tarjeta>
@endsection
