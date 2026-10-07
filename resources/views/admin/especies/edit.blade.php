@extends('layouts.app')

@section('titulo', __('especies.catalogo_finca.editar_titulo', ['nombre' => $especie->nombre]))

@section('contenido')
    <x-encabezado :titulo="__('especies.catalogo_finca.editar_titulo', ['nombre' => $especie->nombre])" :subtitulo="__('especies.catalogo_finca.subtitulo')" />

    <x-tarjeta class="max-w-2xl">
        <x-formulario :action="route('admin.especies.update', $especie)" metodo="PUT">
            @include('admin.especies._campos')
            @include('admin.especies._ficha')
            <div class="grid grid-cols-2 gap-3 pt-2">
                <x-boton variante="secundario" :href="route('admin.especies.index')">{{ __('comun.cancelar') }}</x-boton>
                <x-boton>{{ __('especies.catalogo_finca.guardar') }}</x-boton>
            </div>
        </x-formulario>
    </x-tarjeta>
@endsection
