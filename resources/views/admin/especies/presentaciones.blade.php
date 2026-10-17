@extends('layouts.app')

@section('titulo', __('presentaciones.titulo'))

@php
    use App\Support\Formato;

    $clasesEntrada = 'block min-h-12 w-full rounded-ficha border border-renglon-fuerte bg-ficha px-3 text-base text-tinta focus:border-marca-700 focus:outline-none focus:ring-4 focus:ring-marca-200/70';
    $procesosActivos = $procesos->where('activo', true);
@endphp

@section('contenido')
    <x-encabezado :titulo="__('presentaciones.titulo')" :subtitulo="__('presentaciones.subtitulo')" />
    <x-pestanas-especies />

    @unless ($puedeEditar)
        <x-aviso tono="info" class="mb-6">{{ __('presentaciones.solo_lectura') }}</x-aviso>
    @endunless
    @error('catalogo')
        <x-aviso tono="alerta" class="mb-6">{{ $message }}</x-aviso>
    @enderror
    <x-aviso tono="info" icono="pez" class="mb-6">{{ __('presentaciones.ejemplo') }}</x-aviso>

    {{-- Procesos de la finca --}}
    <x-tarjeta :titulo="__('presentaciones.procesos')" :subtitulo="__('presentaciones.procesos_ayuda')">
        @if ($procesos->isEmpty())
            <p class="text-base text-tinta-suave">{{ __('presentaciones.sin_procesos') }}</p>
        @endif
        <ul class="divide-y divide-renglon">
            @foreach ($procesos as $proceso)
                <li class="py-3">
                    @if ($puedeEditar)
                        <div class="flex flex-wrap items-end gap-3">
                            <x-formulario :action="route('admin.procesos.update', $proceso)" metodo="PUT" class="flex flex-1 flex-wrap items-end gap-3 space-y-0">
                                <label class="block min-w-48 flex-1">
                                    <span class="mb-1 block text-sm font-semibold text-tinta-suave">{{ __('presentaciones.nombre_campo') }}</span>
                                    <input type="text" name="nombre" value="{{ $proceso->nombre }}" maxlength="60" required class="{{ $clasesEntrada }}">
                                </label>
                                <label class="flex min-h-12 items-center gap-2">
                                    <input type="hidden" name="activo" value="0">
                                    <input type="checkbox" name="activo" value="1" class="h-5 w-5 accent-marca-700" @checked($proceso->activo)> {{ __('presentaciones.activo') }}
                                </label>
                                <x-boton variante="secundario">{{ __('presentaciones.guardar') }}</x-boton>
                            </x-formulario>
                            <x-formulario :action="route('admin.procesos.destroy', $proceso)" metodo="DELETE" :confirmar="__('presentaciones.confirmar_eliminar', ['nombre' => $proceso->nombre])" class="space-y-0">
                                <x-boton variante="secundario" :aria-label="__('presentaciones.eliminar_nombre', ['nombre' => $proceso->nombre])"><x-icono nombre="basura" class="h-5 w-5" /></x-boton>
                            </x-formulario>
                        </div>
                    @else
                        <p @class(['font-semibold', 'opacity-60' => ! $proceso->activo])>{{ $proceso->nombre }}@unless ($proceso->activo) · {{ __('presentaciones.inactivo') }}@endunless</p>
                    @endif
                </li>
            @endforeach
        </ul>
        @if ($puedeEditar)
            <x-formulario :action="route('admin.procesos.store')" class="mt-4 flex flex-wrap items-end gap-3 space-y-0">
                <x-campo nombre="nombre" :id="'proceso-nuevo'" :etiqueta="__('presentaciones.nuevo_proceso')" maxlength="60" required class="min-w-48" />
                <x-boton><x-icono nombre="mas" class="h-5 w-5" /> {{ __('presentaciones.agregar') }}</x-boton>
            </x-formulario>
        @endif
    </x-tarjeta>

    {{-- Por especie: sus tamaños y la tabla tamaño × proceso con las presentaciones --}}
    @foreach ($especies as $especie)
        @php $tamanosActivos = $especie->tamanos->where('activo', true); @endphp
        <x-tarjeta class="mt-6" :titulo="$especie->nombre" :subtitulo="__('presentaciones.tamanos_ayuda')">
            @if ($especie->tamanos->isEmpty())
                <p class="text-base text-tinta-suave">{{ __('presentaciones.sin_tamanos') }}</p>
            @endif
            <ul class="divide-y divide-renglon">
                @foreach ($especie->tamanos as $tamano)
                    <li class="py-3">
                        @if ($puedeEditar)
                            <div class="flex flex-wrap items-end gap-3">
                                <x-formulario :action="route('admin.tamanos.update', $tamano)" metodo="PUT" class="flex flex-1 flex-wrap items-end gap-3 space-y-0">
                                    <label class="block min-w-40 flex-1">
                                        <span class="mb-1 block text-sm font-semibold text-tinta-suave">{{ __('presentaciones.nombre_campo') }}</span>
                                        <input type="text" name="nombre" value="{{ $tamano->nombre }}" maxlength="60" required class="{{ $clasesEntrada }}">
                                    </label>
                                    <label class="block w-28">
                                        <span class="mb-1 block text-sm font-semibold text-tinta-suave">{{ __('presentaciones.peso_min') }}</span>
                                        <input type="number" name="peso_min_g" value="{{ $tamano->peso_min_g }}" step="0.1" min="0" inputmode="decimal" class="{{ $clasesEntrada }}">
                                    </label>
                                    <label class="block w-28">
                                        <span class="mb-1 block text-sm font-semibold text-tinta-suave">{{ __('presentaciones.peso_max') }}</span>
                                        <input type="number" name="peso_max_g" value="{{ $tamano->peso_max_g }}" step="0.1" min="0" inputmode="decimal" class="{{ $clasesEntrada }}">
                                    </label>
                                    <label class="flex min-h-12 items-center gap-2">
                                        <input type="hidden" name="activo" value="0">
                                        <input type="checkbox" name="activo" value="1" class="h-5 w-5 accent-marca-700" @checked($tamano->activo)> {{ __('presentaciones.activo') }}
                                    </label>
                                    <x-boton variante="secundario">{{ __('presentaciones.guardar') }}</x-boton>
                                </x-formulario>
                                <x-formulario :action="route('admin.tamanos.destroy', $tamano)" metodo="DELETE" :confirmar="__('presentaciones.confirmar_eliminar', ['nombre' => $tamano->nombre])" class="space-y-0">
                                    <x-boton variante="secundario" :aria-label="__('presentaciones.eliminar_nombre', ['nombre' => $tamano->nombre])"><x-icono nombre="basura" class="h-5 w-5" /></x-boton>
                                </x-formulario>
                            </div>
                        @else
                            <p @class(['font-semibold', 'opacity-60' => ! $tamano->activo])>
                                {{ $tamano->nombre }}
                                @if ($tamano->peso_min_g !== null || $tamano->peso_max_g !== null)
                                    <span class="font-normal text-tinta-suave">· {{ __('presentaciones.rango', ['min' => $tamano->peso_min_g !== null ? Formato::sinCeros($tamano->peso_min_g) : '—', 'max' => $tamano->peso_max_g !== null ? Formato::sinCeros($tamano->peso_max_g) : '—']) }}</span>
                                @endif
                                @unless ($tamano->activo) · {{ __('presentaciones.inactivo') }}@endunless
                            </p>
                        @endif
                    </li>
                @endforeach
            </ul>
            @if ($puedeEditar)
                <x-formulario :action="route('admin.tamanos.store')" class="mt-4 flex flex-wrap items-end gap-3 space-y-0">
                    <input type="hidden" name="especie_id" value="{{ $especie->id }}">
                    <x-campo nombre="nombre" :id="'tamano-nuevo-'.$especie->id" :etiqueta="__('presentaciones.nuevo_tamano')" maxlength="60" required class="min-w-40" />
                    <x-boton><x-icono nombre="mas" class="h-5 w-5" /> {{ __('presentaciones.agregar') }}</x-boton>
                </x-formulario>
            @endif

            @if ($tamanosActivos->isNotEmpty() && $procesosActivos->isNotEmpty())
                <section class="mt-6">
                    <h3 class="text-base font-bold">{{ __('presentaciones.tabla') }}</h3>
                    <p class="mb-3 text-sm text-tinta-suave">{{ __('presentaciones.tabla_ayuda') }}</p>
                    <x-formulario :action="route('admin.presentaciones.update', $especie)" metodo="PUT" class="space-y-4">
                        <div class="overflow-x-auto">
                            <table class="min-w-full text-left text-base">
                                <thead>
                                    <tr>
                                        <th class="p-2 text-sm text-tinta-suave">{{ __('presentaciones.tamano') }}</th>
                                        @foreach ($procesosActivos as $proceso)
                                            <th class="p-2 text-sm font-bold">{{ $proceso->nombre }}</th>
                                        @endforeach
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-renglon">
                                    @foreach ($tamanosActivos as $tamano)
                                        <tr>
                                            <th class="p-2 font-bold">{{ $tamano->nombre }}</th>
                                            @foreach ($procesosActivos as $proceso)
                                                @php $presentacion = $presentaciones[$tamano->id.'-'.$proceso->id] ?? null; @endphp
                                                <td class="p-2 align-top">
                                                    @if ($puedeEditar)
                                                        <label class="flex min-h-11 items-center gap-2">
                                                            <input type="checkbox" name="marcadas[{{ $tamano->id }}][{{ $proceso->id }}]" value="1" class="h-5 w-5 accent-marca-700"
                                                                   @checked($presentacion?->activo)
                                                                   aria-label="{{ __('presentaciones.nombre', ['especie' => $especie->nombre, 'tamano' => $tamano->nombre, 'proceso' => $proceso->nombre]) }}">
                                                            <span class="sr-only">{{ __('presentaciones.activa') }}</span>
                                                        </label>
                                                        <input type="text" name="cabuya[{{ $tamano->id }}][{{ $proceso->id }}]" value="{{ $presentacion?->color_cabuya }}" maxlength="40"
                                                               placeholder="{{ __('presentaciones.cabuya') }}" aria-label="{{ __('presentaciones.cabuya_de', ['tamano' => $tamano->nombre, 'proceso' => $proceso->nombre]) }}"
                                                               class="mt-1 block min-h-10 w-36 rounded-ficha border border-renglon-fuerte bg-ficha px-2 text-sm text-tinta focus:border-marca-700 focus:outline-none">
                                                    @elseif ($presentacion?->activo)
                                                        <span class="font-bold text-bien">✓</span>
                                                        @if ($presentacion->color_cabuya)<span class="block text-sm text-tinta-suave">{{ __('presentaciones.cabuya_texto', ['color' => $presentacion->color_cabuya]) }}</span>@endif
                                                    @else
                                                        <span class="text-tinta-suave">—</span>
                                                    @endif
                                                </td>
                                            @endforeach
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        @if ($puedeEditar)
                            <x-boton>{{ __('presentaciones.guardar_tabla') }}</x-boton>
                        @endif
                    </x-formulario>
                </section>
            @endif
        </x-tarjeta>
    @endforeach
@endsection
