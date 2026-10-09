@extends('layouts.app')

@section('titulo', __('datos.titulo'))

@section('contenido')
    <x-encabezado :titulo="__('datos.titulo')" :subtitulo="__('datos.subtitulo')" />

    <div class="grid max-w-3xl gap-6">
        <x-tarjeta :titulo="__('datos.exportar.titulo')" :subtitulo="__('datos.exportar.ayuda')">
            @error('exportar')
                <x-aviso tono="atencion" role="alert" class="mb-4">{{ $message }}</x-aviso>
            @enderror
            <x-formulario :action="route('admin.datos.exportar')">
                <x-boton>{{ __('datos.exportar.boton') }}</x-boton>
            </x-formulario>

            <h3 class="mt-6 mb-2 text-base font-semibold text-tinta">{{ __('datos.exportar.recientes') }}</h3>
            @forelse ($exportaciones as $exportacion)
                <div class="flex flex-wrap items-center justify-between gap-3 border-t border-renglon py-3 text-base">
                    <div>
                        <p class="font-medium text-tinta">{{ __('datos.exportar.pedida', ['fecha' => $exportacion->created_at->setTimezone(\App\Support\Region::zona())->format('d/m/Y H:i')]) }}</p>
                        <p class="text-sm text-tinta-suave">
                            {{ __('datos.exportar.estados.'.$exportacion->estado) }}
                            @if ($exportacion->vence_at)
                                · {{ $exportacion->descargable() ? __('datos.exportar.disponible_hasta', ['fecha' => $exportacion->vence_at->format('d/m/Y H:i')]) : __('datos.exportar.vencio') }}
                            @endif
                        </p>
                    </div>
                    @if ($exportacion->descargable())
                        <x-boton variante="secundario" :href="route('admin.datos.descargar', $exportacion)">
                            {{ __('datos.exportar.descargar', ['tamano' => \App\Support\Formato::numero($exportacion->bytes / 1048576, 2)]) }}
                        </x-boton>
                    @endif
                </div>
            @empty
                <p class="text-base text-tinta-suave">{{ __('datos.exportar.ninguna') }}</p>
            @endforelse
        </x-tarjeta>

        <x-tarjeta :titulo="__('datos.eliminar.titulo')" class="ring-alerta-borde">
            <x-aviso tono="alerta">{{ __('datos.eliminar.ayuda', ['dias' => config('datos.dias_gracia')]) }}</x-aviso>
            <p class="mt-3 text-sm text-tinta-suave">{{ __('datos.eliminar.copias', ['dias' => config('respaldos.dias')]) }}</p>
            <x-formulario :action="route('admin.datos.eliminar')" class="mt-5">
                <x-campo nombre="codigo" :etiqueta="__('datos.eliminar.codigo', ['codigo' => $finca->codigo])" autocomplete="off" autocapitalize="none" required />
                <x-campo nombre="actual" :etiqueta="__('datos.eliminar.actual')" tipo="password" autocomplete="current-password" required />
                <x-boton variante="peligro">{{ __('datos.eliminar.boton') }}</x-boton>
            </x-formulario>
        </x-tarjeta>
    </div>
@endsection
