@extends('layouts.app')

@section('titulo', __('datos.eliminar.pantalla_titulo'))

@section('contenido')
    <x-tarjeta class="mx-auto max-w-xl ring-2 ring-red-200" :titulo="__('datos.eliminar.pantalla_titulo')">
        <p class="rounded-xl bg-red-50 p-4 text-base text-red-900">
            {{ __('datos.eliminar.pantalla_texto', ['finca' => $finca->nombre, 'fecha' => $finca->eliminacion_programada_at->format('d/m/Y H:i')]) }}
        </p>
        <p class="mt-3 text-sm text-slate-500">{{ __('datos.eliminar.copias', ['dias' => config('respaldos.dias')]) }}</p>

        <x-formulario :action="route('admin.datos.cancelar')" class="mt-5">
            <x-campo nombre="actual" :etiqueta="__('datos.eliminar.actual')" tipo="password" autocomplete="current-password" required />
            <x-boton class="w-full">{{ __('datos.eliminar.cancelar_boton') }}</x-boton>
        </x-formulario>
    </x-tarjeta>
@endsection
