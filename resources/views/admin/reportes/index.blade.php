@extends('layouts.app')

@section('titulo', 'Reportes')

@section('contenido')
    <x-encabezado titulo="Reportes" subtitulo="Informes para imprimir o guardar como PDF." />

    <div class="grid gap-5 md:grid-cols-2">
        <x-tarjeta titulo="Informe mensual" subtitulo="Producción, alimento, mortalidad, sanidad y novedades del mes.">
            <x-formulario :action="route('admin.reportes.mensual')" metodo="GET">
                <x-campo nombre="mes" etiqueta="Mes" tipo="month" :valor="today()->format('Y-m')" :max="today()->format('Y-m')" required />
                <x-boton class="w-full"><x-icono nombre="reporte" class="h-5 w-5" /> Ver informe</x-boton>
            </x-formulario>
        </x-tarjeta>

        <x-tarjeta titulo="Libro de campo" subtitulo="Registro cronológico de un lago: siembras, alimento, sanidad, agua y mortalidad.">
            @if ($lagos === [])
                <p class="text-base text-slate-500">Primero registra un lago.</p>
            @else
                <x-formulario :action="route('admin.reportes.libro-campo')" metodo="GET">
                    <x-seleccion nombre="lago_id" etiqueta="Lago" :opciones="$lagos" required />
                    <div class="grid grid-cols-2 gap-4">
                        <x-campo nombre="desde" etiqueta="Desde" tipo="date" :valor="today()->subMonths(3)->toDateString()" required />
                        <x-campo nombre="hasta" etiqueta="Hasta" tipo="date" :valor="today()->toDateString()" required />
                    </div>
                    <x-boton class="w-full"><x-icono nombre="reporte" class="h-5 w-5" /> Ver libro de campo</x-boton>
                </x-formulario>
                <p class="mt-3 text-sm text-slate-500">
                    Formato de apoyo para la trazabilidad. Confirma con tu oficina del ICA los datos que exige la norma vigente.
                </p>
            @endif
        </x-tarjeta>
    </div>
@endsection
