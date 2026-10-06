@extends('layouts.app')

@section('titulo', __('reportes.reportes'))

@section('contenido')
    <x-encabezado :titulo="__('reportes.reportes')" :subtitulo="__('reportes.informes_imprimir_guardar_como_pdf')" />

    <div class="grid gap-5 md:grid-cols-2">
        <x-tarjeta :titulo="__('reportes.informe_mensual')" :subtitulo="__('reportes.produccion_alimento_mortalidad_sanidad_novedades')">
            <x-formulario :action="route('admin.reportes.mensual')" metodo="GET">
                <x-campo nombre="mes" :etiqueta="__('reportes.mes')" tipo="month" :valor="\App\Support\Region::hoy()->format('Y-m')" :max="\App\Support\Region::hoy()->format('Y-m')" required />
                <x-boton class="w-full"><x-icono nombre="reporte" class="h-5 w-5" /> {{ __('reportes.ver_informe') }}</x-boton>
            </x-formulario>
        </x-tarjeta>

        <x-tarjeta :titulo="__('reportes.libro_campo')" :subtitulo="__('reportes.registro_cronologico_lago_siembras_alimento')">
            @if ($lagos === [])
                <p class="text-base text-slate-500">{{ __('reportes.primero_registra_lago') }}</p>
            @else
                <x-formulario :action="route('admin.reportes.libro-campo')" metodo="GET">
                    <x-seleccion nombre="lago_id" :etiqueta="__('reportes.lago')" :opciones="$lagos" required />
                    <div class="grid grid-cols-2 gap-4">
                        <x-campo nombre="desde" :etiqueta="__('comun.desde')" tipo="date" :valor="\App\Support\Region::hoy()->subMonths(3)->toDateString()" required />
                        <x-campo nombre="hasta" :etiqueta="__('comun.hasta')" tipo="date" :valor="\App\Support\Region::hoy()->toDateString()" required />
                    </div>
                    <x-boton class="w-full"><x-icono nombre="reporte" class="h-5 w-5" /> {{ __('reportes.ver_libro_campo') }}</x-boton>
                </x-formulario>
                <p class="mt-3 text-sm text-slate-500">
                    {{ __('region.aviso_trazabilidad') }}
                </p>
            @endif
        </x-tarjeta>
    </div>
@endsection
