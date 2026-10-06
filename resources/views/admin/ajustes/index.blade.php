@extends('layouts.app')

@section('titulo', __('region.ajustes.titulo'))

@section('contenido')
    <x-encabezado :titulo="__('region.ajustes.titulo')" :subtitulo="__('region.ajustes.subtitulo')" />

    <x-tarjeta class="max-w-2xl">
        <x-formulario :action="route('admin.ajustes.update')" metodo="PUT">
            <x-campo nombre="nombre" :etiqueta="__('region.ajustes.nombre')" :valor="$finca->nombre" required />
            <x-seleccion nombre="pais" :etiqueta="__('region.ajustes.pais')" :opciones="$paises" :valor="$finca->pais"
                         :ayuda="__('region.ajustes.pais_ayuda')" required />
            <x-seleccion nombre="zona_horaria" :etiqueta="__('region.ajustes.zona_horaria')" :opciones="array_combine($zonas, $zonas)"
                         :valor="$finca->zona_horaria" :ayuda="__('region.ajustes.zona_ayuda')" required />
            <x-seleccion nombre="idioma" :etiqueta="__('region.ajustes.idioma')" :opciones="$idiomas" :valor="$finca->idioma" required />

            <p class="text-sm text-slate-500">{{ __('region.ajustes.hora_actual', ['hora' => \App\Support\Region::ahora()->format('d/m/Y H:i')]) }}</p>

            <x-boton>{{ __('region.ajustes.guardar') }}</x-boton>
        </x-formulario>
    </x-tarjeta>
@endsection
