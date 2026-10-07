@extends('layouts.app')

@php
    use App\Models\Especie;
    use App\Support\Formato;
@endphp

@section('titulo', __('especies.catalogo_finca.titulo'))

@section('contenido')
    <x-encabezado :titulo="__('especies.catalogo_finca.titulo')" :subtitulo="__('especies.catalogo_finca.subtitulo')">
        @if ($puedeEditar)
            <x-boton data-abrir-modal="modal-especie"><x-icono nombre="mas" class="h-5 w-5" /> {{ __('especies.catalogo_finca.nueva') }}</x-boton>
        @endif
    </x-encabezado>

    @unless ($puedeEditar)
        <p class="mb-4 text-sm text-slate-500">{{ __('especies.catalogo_finca.solo_lectura') }}</p>
    @endunless
    @error('especie')
        <p class="mb-4 rounded-xl bg-red-50 p-4 text-base text-red-800" role="alert">{{ $message }}</p>
    @enderror

    <x-tabla :columnas="[__('especies.catalogo_finca.nombre'), __('especies.clima'), __('especies.catalogo_finca.rangos'), __('especies.catalogo_finca.alimentacion'), __('especies.estado'), '']">
        @foreach ($especies as $especie)
            <tr @class(['opacity-60' => ! $especie->activo])>
                <td data-label="{{ __('especies.catalogo_finca.nombre') }}">
                    <a href="{{ route('especies.show', $especie) }}" class="font-semibold text-slate-900 hover:underline">{{ $especie->nombre }}</a>
                    @if ($especie->nombre_cientifico)
                        <span class="block text-sm italic text-slate-500">{{ $especie->nombre_cientifico }}</span>
                    @endif
                    <span class="block text-sm text-slate-500">{{ trans_choice('especies.catalogo_finca.siembras', $especie->siembras_count) }}</span>
                </td>
                <td data-label="{{ __('especies.clima') }}">{{ __($especie->esFria() ? 'especies.agua_fria' : 'especies.agua_calida') }}</td>
                <td data-label="{{ __('especies.catalogo_finca.rangos') }}" class="text-sm">
                    @if ($especie->rangoTemperatura() || $especie->oxigeno_min !== null || $especie->rangoPh())
                        <span class="block">{{ __('especies.temperatura') }}: {{ $especie->rangoTemperatura() ? __('especies.rango_temperatura', ['min' => Formato::sinCeros($especie->temp_min), 'max' => Formato::sinCeros($especie->temp_max)]) : __('especies.sin_dato') }}</span>
                        <span class="block">{{ __('especies.oxigeno_minimo') }}: {{ $especie->oxigeno_min !== null ? __('especies.valor_oxigeno', ['valor' => Formato::sinCeros($especie->oxigeno_min)]) : __('especies.sin_dato') }}</span>
                        <span class="block">{{ __('especies.ph') }}: {{ $especie->rangoPh() ? __('especies.rango', ['min' => Formato::sinCeros($especie->ph_min), 'max' => Formato::sinCeros($especie->ph_max)]) : __('especies.sin_dato') }}</span>
                    @else
                        <span class="text-slate-500">{{ __('especies.catalogo_finca.sin_rangos') }}</span>
                    @endif
                </td>
                <td data-label="{{ __('especies.catalogo_finca.alimentacion') }}">
                    {{ $especie->alimentacion ? __('especies.catalogo_finca.alimentaciones.'.$especie->alimentacion) : __('especies.catalogo_finca.sin_alimentacion') }}
                </td>
                <td data-label="{{ __('especies.estado') }}">
                    <span @class(['rounded-full px-2.5 py-0.5 text-sm font-semibold', 'bg-marca-100 text-marca-800' => $especie->activo, 'bg-slate-100 text-slate-700' => ! $especie->activo])>
                        {{ __($especie->activo ? 'especies.catalogo_finca.activa' : 'especies.catalogo_finca.inactiva') }}
                    </span>
                </td>
                <td>
                    @if ($puedeEditar)
                        <div class="flex flex-wrap gap-2">
                            <x-boton variante="secundario" class="!min-h-10 !px-3 !py-2 text-sm" :href="route('admin.especies.edit', $especie)">{{ __('especies.catalogo_finca.editar') }}</x-boton>
                            <form method="POST" action="{{ route('admin.especies.activo', $especie) }}">
                                @csrf
                                <x-boton variante="secundario" class="!min-h-10 !px-3 !py-2 text-sm">{{ __($especie->activo ? 'especies.catalogo_finca.desactivar' : 'especies.catalogo_finca.activar') }}</x-boton>
                            </form>
                            @if ($especie->siembras_count === 0)
                                <form method="POST" action="{{ route('admin.especies.destroy', $especie) }}" onsubmit="return confirm(@js(__('especies.catalogo_finca.confirmar_eliminar', ['nombre' => $especie->nombre])))">
                                    @csrf
                                    @method('DELETE')
                                    <x-boton variante="peligro" class="!min-h-10 !px-3 !py-2 text-sm">{{ __('especies.catalogo_finca.eliminar') }}</x-boton>
                                </form>
                            @endif
                        </div>
                    @endif
                </td>
            </tr>
        @endforeach
    </x-tabla>

    @if ($puedeEditar)
        <x-modal id="modal-especie" :titulo="__('especies.catalogo_finca.nueva')">
            <x-formulario :action="route('admin.especies.store')">
                @include('admin.especies._campos', ['especie' => null])
                <div class="grid grid-cols-2 gap-3 pt-2">
                    <x-boton tipo="button" variante="secundario" data-cerrar-modal>{{ __('comun.cancelar') }}</x-boton>
                    <x-boton>{{ __('especies.catalogo_finca.guardar') }}</x-boton>
                </div>
            </x-formulario>
        </x-modal>
    @endif
@endsection
