@extends('layouts.app')

@section('titulo', __('precios.titulo'))

@php
    $clasesEntrada = 'block min-h-12 w-full rounded-ficha border border-renglon-fuerte bg-ficha px-3 text-base tabular-nums text-tinta focus:border-marca-700 focus:outline-none focus:ring-4 focus:ring-marca-200/70';
@endphp

@section('contenido')
    <x-encabezado :titulo="__('precios.titulo')" :subtitulo="__('precios.subtitulo')">
        <x-boton variante="secundario" :href="route('admin.ajustes.edit')">{{ __('precios.volver') }}</x-boton>
    </x-encabezado>

    <x-aviso tono="info" class="mb-6 max-w-3xl">
        {{ $cupoKg > 0 ? __('precios.cupo_activo', ['kg' => $cupoKg]) : __('precios.sin_cupo') }}
    </x-aviso>

    @if ($errors->any())
        <x-aviso tono="alerta" class="mb-6 max-w-3xl">
            <ul class="space-y-1">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </x-aviso>
    @endif

    @if ($especies->isEmpty())
        <x-vacio icono="pez" :mensaje="__('precios.sin_especies')" />
    @else
        <x-formulario :action="route('admin.ajustes.precios.update')" metodo="PUT" class="max-w-3xl">
            <x-tarjeta>
                <ul class="divide-y divide-renglon">
                    @foreach ($especies as $especie)
                        @php $precio = $precios[$especie->id] ?? null; @endphp
                        <li class="grid grid-cols-1 gap-3 py-4 sm:grid-cols-[1fr_10rem_auto_10rem] sm:items-end">
                            <p class="font-bold">{{ $especie->nombre }}</p>
                            <label class="block">
                                <span class="mb-1 block text-sm font-semibold text-tinta-suave">{{ __('precios.general') }}</span>
                                <input type="number" min="1" step="1" inputmode="numeric" name="precios[{{ $especie->id }}][general]" class="{{ $clasesEntrada }}"
                                       value="{{ old('precios.'.$especie->id.'.general', $precio?->precio_general) }}">
                            </label>
                            <label class="flex min-h-12 items-center gap-2">
                                <input type="hidden" name="precios[{{ $especie->id }}][entra_en_cupo]" value="0">
                                <input type="checkbox" name="precios[{{ $especie->id }}][entra_en_cupo]" value="1" class="h-5 w-5 accent-marca-700"
                                       @checked(old('precios.'.$especie->id.'.entra_en_cupo', $precio?->entra_en_cupo))>
                                {{ __('precios.entra_en_cupo') }}
                            </label>
                            <label class="block">
                                <span class="mb-1 block text-sm font-semibold text-tinta-suave">{{ __('precios.cupo') }}</span>
                                <input type="number" min="1" step="1" inputmode="numeric" name="precios[{{ $especie->id }}][cupo]" class="{{ $clasesEntrada }}"
                                       value="{{ old('precios.'.$especie->id.'.cupo', $precio?->precio_cupo) }}">
                            </label>
                        </li>
                    @endforeach
                </ul>
                <p class="mt-3 text-sm text-tinta-suave">{{ __('precios.ayuda') }}</p>
            </x-tarjeta>
            <x-boton>{{ __('precios.guardar') }}</x-boton>
        </x-formulario>
    @endif
@endsection
