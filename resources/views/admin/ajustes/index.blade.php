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

            <fieldset class="space-y-5 border-t border-slate-200 pt-5">
                <legend class="mb-1 text-lg font-semibold text-slate-900">{{ __('alertas.ajustes.titulo') }}</legend>
                <x-campo nombre="alerta_stock_dias" :etiqueta="__('alertas.ajustes.stock_dias')" tipo="number" min="1" max="90" inputmode="numeric"
                         :valor="$finca->alerta_stock_dias" :ayuda="__('alertas.ajustes.stock_dias_ayuda')" required />
                <div class="grid grid-cols-2 gap-4">
                    <x-campo nombre="alerta_mortalidad_porcentaje" :etiqueta="__('alertas.ajustes.mortalidad_porcentaje')" tipo="number" step="0.1" min="0.1" max="100" inputmode="decimal"
                             :valor="$finca->alerta_mortalidad_porcentaje" required />
                    <x-campo nombre="alerta_mortalidad_dias" :etiqueta="__('alertas.ajustes.mortalidad_dias')" tipo="number" min="1" max="60" inputmode="numeric"
                             :valor="$finca->alerta_mortalidad_dias" required />
                </div>
                <p class="text-sm text-slate-500">{{ __('alertas.ajustes.mortalidad_ayuda') }}</p>
                <x-campo nombre="alerta_despacho_dias" :etiqueta="__('alertas.ajustes.despacho_dias')" tipo="number" min="1" max="30" inputmode="numeric"
                         :valor="$finca->alerta_despacho_dias" :ayuda="__('alertas.ajustes.despacho_dias_ayuda')" required />
            </fieldset>

            <x-boton>{{ __('region.ajustes.guardar') }}</x-boton>
        </x-formulario>
    </x-tarjeta>
@endsection
