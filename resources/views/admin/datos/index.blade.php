@extends('layouts.app')

@section('titulo', __('datos.titulo'))

@section('contenido')
    <x-encabezado :titulo="__('datos.titulo')" :subtitulo="__('datos.subtitulo')" />

    <div class="grid max-w-3xl gap-6">
        <x-tarjeta :titulo="__('datos.exportar.titulo')" :subtitulo="__('datos.exportar.ayuda')">
            @error('exportar')
                <p class="mb-4 rounded-xl bg-amber-50 p-4 text-base text-amber-900" role="alert">{{ $message }}</p>
            @enderror
            <x-formulario :action="route('admin.datos.exportar')">
                <x-boton>{{ __('datos.exportar.boton') }}</x-boton>
            </x-formulario>

            <h3 class="mt-6 mb-2 text-base font-semibold text-slate-900">{{ __('datos.exportar.recientes') }}</h3>
            @forelse ($exportaciones as $exportacion)
                <div class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 py-3 text-base">
                    <div>
                        <p class="font-medium text-slate-900">{{ __('datos.exportar.pedida', ['fecha' => $exportacion->created_at->setTimezone(\App\Support\Region::zona())->format('d/m/Y H:i')]) }}</p>
                        <p class="text-sm text-slate-500">
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
                <p class="text-base text-slate-500">{{ __('datos.exportar.ninguna') }}</p>
            @endforelse
        </x-tarjeta>

        <x-tarjeta :titulo="__('datos.eliminar.titulo')" class="ring-red-200">
            <p class="rounded-xl bg-red-50 p-4 text-base text-red-900">{{ __('datos.eliminar.ayuda', ['dias' => config('datos.dias_gracia')]) }}</p>
            <p class="mt-3 text-sm text-slate-500">{{ __('datos.eliminar.copias', ['dias' => config('respaldos.dias')]) }}</p>
            <x-formulario :action="route('admin.datos.eliminar')" class="mt-5">
                <x-campo nombre="codigo" :etiqueta="__('datos.eliminar.codigo', ['codigo' => $finca->codigo])" autocomplete="off" autocapitalize="none" required />
                <x-campo nombre="actual" :etiqueta="__('datos.eliminar.actual')" tipo="password" autocomplete="current-password" required />
                <x-boton variante="peligro">{{ __('datos.eliminar.boton') }}</x-boton>
            </x-formulario>
        </x-tarjeta>
    </div>
@endsection
