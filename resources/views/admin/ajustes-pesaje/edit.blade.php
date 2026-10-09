@extends('layouts.app')

@section('titulo', __('cosechas.ajustes.titulo'))

@section('contenido')
    <x-encabezado :titulo="__('cosechas.ajustes.titulo')" :subtitulo="__('cosechas.ajustes.subtitulo')" />

    <x-tarjeta class="max-w-2xl">
        <x-formulario :action="route('admin.ajustes-pesaje.update')" metodo="PUT">
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <x-campo nombre="peso_canasta_kg" :etiqueta="__('cosechas.peso_canasta_vacia')" tipo="number" step="0.01" min="0.01" max="20" inputmode="decimal"
                         :valor="$finca->peso_canasta_kg" required />
                <x-campo nombre="peso_canastilla_kg" :etiqueta="__('cosechas.peso_canastilla_vacia')" tipo="number" step="0.01" min="0.01" max="20" inputmode="decimal"
                         :valor="$finca->peso_canastilla_kg" required />
            </div>
            <p class="text-sm text-tinta-suave">{{ __('cosechas.pesos_vacias_ayuda') }}</p>
            <x-boton>{{ __('cosechas.ajustes.guardar') }}</x-boton>
        </x-formulario>
    </x-tarjeta>
@endsection
