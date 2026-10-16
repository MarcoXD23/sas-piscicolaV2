@extends('layouts.app')

@section('titulo', __('fases.titulo'))

@section('contenido')
    <a href="{{ route('admin.ajustes.edit') }}" class="-ml-1 mb-2 inline-flex min-h-12 items-center gap-1 text-base font-semibold text-marca-700 underline decoration-renglon-fuerte underline-offset-4 hover:decoration-marca-700">
        <x-icono nombre="abajo" class="h-5 w-5 rotate-90" /> {{ __('fases.volver_ajustes') }}
    </a>

    <x-encabezado :titulo="__('fases.titulo')" :subtitulo="__('fases.subtitulo')" />

    @if ($errors->has('fase'))
        <x-aviso tono="alerta" class="mb-6">{{ $errors->first('fase') }}</x-aviso>
    @endif

    <x-tarjeta class="mb-6 max-w-2xl" :titulo="__('fases.nueva_fase')">
        <x-formulario :action="route('admin.fases.store')">
            <div class="grid gap-4 sm:grid-cols-3">
                <div class="sm:col-span-2">
                    <x-campo nombre="nombre" id="nueva-fase-nombre" :etiqueta="__('fases.nombre')" required />
                </div>
                <x-campo nombre="orden" id="nueva-fase-orden" :etiqueta="__('fases.orden')" tipo="number" min="1" max="999" inputmode="numeric" :valor="$siguienteOrden" required />
            </div>
            <x-boton>{{ __('fases.guardar_fase') }}</x-boton>
        </x-formulario>
    </x-tarjeta>

    @if ($fases->isEmpty())
        <x-vacio icono="lagos" :mensaje="__('fases.sin_fases')" />
    @endif

    <div class="space-y-6">
        @foreach ($fases as $fase)
            @php
                $reglasPorEspecie = $fase->reglas->keyBy('especie_id');
            @endphp
            <x-tarjeta>
                <div class="flex flex-wrap items-start justify-between gap-4 border-b border-renglon pb-4">
                    <div>
                        <h2 class="text-seccion font-bold text-tinta">{{ $fase->nombre }}</h2>
                        <p class="text-sm text-tinta-suave">{{ trans_choice('fases.lagos_en_fase', $fase->lagos_count, ['count' => $fase->lagos_count]) }}</p>
                    </div>
                    @if ($fase->lagos_count === 0)
                        <x-formulario :action="route('admin.fases.destroy', $fase)" metodo="DELETE" class="!space-y-0"
                                      :confirmar="__('fases.confirmar_eliminar', ['nombre' => $fase->nombre])">
                            <x-boton variante="peligro">{{ __('fases.eliminar') }}</x-boton>
                        </x-formulario>
                    @endif
                </div>

                <x-formulario :action="route('admin.fases.update', $fase)" metodo="PUT" class="mt-4">
                    <div class="grid gap-4 sm:grid-cols-3">
                        <div class="sm:col-span-2">
                            <x-campo nombre="nombre" id="fase-nombre-{{ $fase->id }}" :etiqueta="__('fases.nombre')" :valor="$fase->nombre" required />
                        </div>
                        <x-campo nombre="orden" id="fase-orden-{{ $fase->id }}" :etiqueta="__('fases.orden')" tipo="number" min="1" max="999" inputmode="numeric" :valor="$fase->orden" required />
                    </div>
                    <x-boton variante="secundario">{{ __('fases.guardar_fase') }}</x-boton>
                </x-formulario>

                <details class="group mt-6 border-t border-renglon pt-4">
                    <summary class="flex min-h-12 cursor-pointer list-none items-center justify-between gap-3 text-base font-bold text-tinta [&::-webkit-details-marker]:hidden">
                        <span>{{ __('fases.reglas.titulo', ['fase' => $fase->nombre]) }}</span>
                        <x-icono nombre="abajo" class="h-5 w-5 shrink-0 transition-transform group-open:rotate-180" />
                    </summary>
                    <p class="mt-1 text-sm text-tinta-suave">{{ __('fases.reglas.subtitulo') }} {{ __('fases.reglas.dias_ayuda') }}</p>

                    <div class="mt-4 divide-y divide-renglon">
                        @foreach ($especies as $especie)
                            @php
                                $regla = $reglasPorEspecie->get($especie->id);
                            @endphp
                            <div class="py-4 first:pt-0 last:pb-0">
                                <h3 class="mb-3 font-bold text-tinta">{{ $especie->nombre }}</h3>
                                <x-formulario :action="route('admin.fases.reglas.guardar', $fase)">
                                    <input type="hidden" name="especie_id" value="{{ $especie->id }}">
                                    <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
                                        <x-campo nombre="peso_entrada_g" id="regla-entrada-{{ $fase->id }}-{{ $especie->id }}" :etiqueta="__('fases.reglas.peso_entrada_g')" tipo="number" step="0.1" min="0.1" max="50000" inputmode="decimal" :valor="$regla?->peso_entrada_g" />
                                        <x-campo nombre="peso_salida_g" id="regla-salida-{{ $fase->id }}-{{ $especie->id }}" :etiqueta="__('fases.reglas.peso_salida_g')" tipo="number" step="0.1" min="0.1" max="50000" inputmode="decimal" :valor="$regla?->peso_salida_g" />
                                        <x-campo nombre="dias_esperados" id="regla-dias-{{ $fase->id }}-{{ $especie->id }}" :etiqueta="__('fases.reglas.dias_esperados')" tipo="number" min="1" max="3650" inputmode="numeric" :valor="$regla?->dias_esperados" />
                                        <x-campo nombre="supervivencia_porcentaje" id="regla-sup-{{ $fase->id }}-{{ $especie->id }}" :etiqueta="__('fases.reglas.supervivencia_porcentaje')" tipo="number" step="0.1" min="1" max="100" inputmode="decimal" :valor="$regla?->supervivencia_porcentaje" />
                                        <x-campo nombre="densidad_min_m2" id="regla-dmin-{{ $fase->id }}-{{ $especie->id }}" :etiqueta="__('fases.reglas.densidad_min_m2')" tipo="number" step="0.1" min="0.1" max="5000" inputmode="decimal" :valor="$regla?->densidad_min_m2" />
                                        <x-campo nombre="densidad_max_m2" id="regla-dmax-{{ $fase->id }}-{{ $especie->id }}" :etiqueta="__('fases.reglas.densidad_max_m2')" tipo="number" step="0.1" min="0.1" max="5000" inputmode="decimal" :valor="$regla?->densidad_max_m2" />
                                    </div>
                                    <x-seleccion nombre="concentrado_id" id="regla-concentrado-{{ $fase->id }}-{{ $especie->id }}" :etiqueta="__('fases.reglas.concentrado')"
                                                 :opciones="$opcionesConcentrados" :valor="$regla?->concentrado_id ?? ''" :ayuda="__('fases.reglas.concentrado_ayuda')" />
                                    <x-boton variante="secundario">{{ __('fases.reglas.guardar') }}</x-boton>
                                </x-formulario>
                            </div>
                        @endforeach
                    </div>
                </details>
            </x-tarjeta>
        @endforeach
    </div>
@endsection

