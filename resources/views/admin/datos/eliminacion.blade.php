@extends('layouts.app')

@section('titulo', __('datos.eliminar.pantalla_titulo'))

@section('contenido')
    <x-tarjeta class="mx-auto max-w-xl ring-2 ring-alerta-borde" :titulo="__('datos.eliminar.pantalla_titulo')">
        <x-aviso tono="alerta">
            {{ __('datos.eliminar.pantalla_texto', ['finca' => $finca->nombre, 'fecha' => $finca->eliminacion_programada_at->formatoHora('d/m/Y H:i')]) }}
        </x-aviso>
        <p class="mt-3 text-sm text-tinta-suave">{{ __('datos.eliminar.copias', ['dias' => config('respaldos.dias')]) }}</p>

        <x-formulario :action="route('admin.datos.cancelar')" class="mt-5">
            <x-contrasena nombre="actual" :etiqueta="__('datos.eliminar.actual')" autocomplete="current-password" required />
            <x-boton class="w-full">{{ __('datos.eliminar.cancelar_boton') }}</x-boton>
        </x-formulario>
    </x-tarjeta>
@endsection
