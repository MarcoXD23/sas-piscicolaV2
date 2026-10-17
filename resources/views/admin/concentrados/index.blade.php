@extends('layouts.app')

@section('titulo', __('concentrados.titulo'))

@section('contenido')
    @php
        $numero = fn ($valor, $decimales = 1) => \App\Support\Formato::numero((float) $valor, $decimales);
        $proteinas = \App\Models\Concentrado::opcionesProteina();
        $proteinasConcentrado = ['' => __('concentrados.proteina_elige')] + $proteinas;
        $proteinasTramo = ['' => __('concentrados.sin_proteina')] + $proteinas;
    @endphp

    <a href="{{ route('admin.ajustes.edit') }}" class="-ml-1 mb-2 inline-flex min-h-12 items-center gap-1 text-base font-semibold text-marca-700 underline decoration-renglon-fuerte underline-offset-4 hover:decoration-marca-700">
        <x-icono nombre="abajo" class="h-5 w-5 rotate-90" /> {{ __('fases.volver_ajustes') }}
    </a>

    <x-encabezado :titulo="__('concentrados.titulo')" :subtitulo="__('concentrados.subtitulo')" />

    <x-tarjeta class="mb-8 max-w-3xl" :titulo="__('concentrados.nuevo_concentrado')">
        <x-formulario :action="route('admin.concentrados.store')">
            <div class="grid gap-4 sm:grid-cols-2">
                <x-campo nombre="nombre" id="nuevo-concentrado-nombre" :etiqueta="__('concentrados.nombre')" :placeholder="__('concentrados.nombre_ejemplo')" required />
                <x-campo nombre="nombre_corto" id="nuevo-concentrado-corto" :etiqueta="__('concentrados.nombre_corto')" :placeholder="__('concentrados.nombre_corto_ejemplo')" :ayuda="__('concentrados.nombre_corto_ayuda')" />
            </div>
            <div class="grid grid-cols-3 gap-4">
                <x-seleccion nombre="proteina_porcentaje" id="nuevo-concentrado-proteina" :etiqueta="__('concentrados.proteina_porcentaje')" :opciones="$proteinasConcentrado" required :ayuda="__('concentrados.proteina_ayuda')" />
                <x-campo nombre="pellet_mm" id="nuevo-concentrado-pellet" :etiqueta="__('concentrados.pellet_mm')" :placeholder="__('concentrados.pellet_ejemplo')" />
                <x-campo nombre="peso_bulto_kg" id="nuevo-concentrado-bulto" :etiqueta="__('concentrados.peso_bulto_kg')" tipo="number" step="0.1" min="1" max="1000" inputmode="decimal" :valor="\App\Models\Concentrado::PESO_BULTO_POR_DEFECTO" required />
            </div>
            <x-boton>{{ __('comun.guardar') }}</x-boton>
        </x-formulario>
    </x-tarjeta>

    <section class="mb-10">
        <h2 class="mb-4 text-seccion font-bold text-tinta">{{ __('concentrados.catalogo_titulo') }}</h2>

        @if ($concentrados->isEmpty())
            <x-vacio icono="bodega" :mensaje="__('concentrados.catalogo_vacio')" />
        @else
            <div class="space-y-4">
                @foreach ($concentrados as $concentrado)
                    <x-tarjeta>
                        <x-formulario :action="route('admin.concentrados.update', $concentrado)" metodo="PUT">
                            <div class="grid gap-4 sm:grid-cols-3">
                                <x-campo nombre="nombre" id="concentrado-nombre-{{ $concentrado->id }}" :etiqueta="__('concentrados.nombre')" :valor="$concentrado->nombre" required />
                                <x-campo nombre="nombre_corto" id="concentrado-corto-{{ $concentrado->id }}" :etiqueta="__('concentrados.nombre_corto')" :valor="$concentrado->nombre_corto" />
                                <x-seleccion nombre="activo" id="concentrado-activo-{{ $concentrado->id }}" :etiqueta="__('concentrados.estado')"
                                             :opciones="[1 => __('concentrados.activo'), 0 => __('concentrados.inactivo')]" :valor="$concentrado->activo ? 1 : 0" />
                            </div>
                            <div class="grid grid-cols-3 gap-4">
                                <x-seleccion nombre="proteina_porcentaje" id="concentrado-proteina-{{ $concentrado->id }}" :etiqueta="__('concentrados.proteina_porcentaje')" :opciones="$proteinasConcentrado" :valor="\App\Models\Concentrado::proteinaEntera($concentrado->proteina_porcentaje)" required />
                                <x-campo nombre="pellet_mm" id="concentrado-pellet-{{ $concentrado->id }}" :etiqueta="__('concentrados.pellet_mm')" :valor="$concentrado->pellet_mm" />
                                <x-campo nombre="peso_bulto_kg" id="concentrado-bulto-{{ $concentrado->id }}" :etiqueta="__('concentrados.peso_bulto_kg')" tipo="number" step="0.1" min="1" max="1000" inputmode="decimal" :valor="$concentrado->peso_bulto_kg" required />
                            </div>
                            <x-boton variante="secundario">{{ __('comun.guardar') }}</x-boton>
                        </x-formulario>
                    </x-tarjeta>
                @endforeach
            </div>
        @endif
    </section>

    <section>
        <h2 class="mb-1 text-seccion font-bold text-tinta">{{ __('concentrados.tabla_titulo') }}</h2>
        <p class="mb-4 text-sm text-tinta-suave">{{ __('concentrados.tabla_subtitulo') }}</p>

        <div class="space-y-6">
            @foreach ($especies as $especie)
                <x-tarjeta>
                    <div class="mb-4 flex flex-wrap items-center justify-between gap-3 border-b border-renglon pb-3">
                        <h3 class="text-lg font-bold text-tinta">{{ $especie->nombre }}</h3>
                        @if ($especie->alimentacion !== null)
                            <x-formulario :action="route('admin.concentrados.racion.restaurar', $especie)" class="!space-y-0">
                                <x-boton variante="secundario">{{ __('concentrados.restaurar_especie') }}</x-boton>
                            </x-formulario>
                        @endif
                    </div>

                    <x-tabla :columnas="[
                        __('concentrados.peso_desde_g').' – '.__('concentrados.peso_hasta_g'),
                        __('concentrados.porcentaje_biomasa'),
                        __('concentrados.tomas_dia'),
                        __('concentrados.proteina_porcentaje'),
                        __('concentrados.pellet_mm'),
                        __('concentrados.concentrado_sugerido'),
                        '',
                    ]" :vacio="__('concentrados.sin_tramos')">
                        @foreach ($especie->tablaRacion as $tramo)
                            <tr>
                                <td data-label="{{ __('concentrados.peso_hasta_g') }}" class="font-semibold tabular-nums">
                                    {{ __('concentrados.rango_peso_g', ['desde' => $numero($tramo->peso_desde_g, 1), 'hasta' => $numero($tramo->peso_hasta_g, 1)]) }}
                                </td>
                                <td data-label="{{ __('concentrados.porcentaje_biomasa') }}" class="tabular-nums">{{ $numero($tramo->porcentaje_biomasa, 1) }} %</td>
                                <td data-label="{{ __('concentrados.tomas_dia') }}" class="tabular-nums">{{ $tramo->tomas_dia }}</td>
                                <td data-label="{{ __('concentrados.proteina_porcentaje') }}" class="tabular-nums">{{ $tramo->proteina_porcentaje !== null ? __('concentrados.opcion_proteina', ['proteina' => \App\Models\Concentrado::proteinaEntera($tramo->proteina_porcentaje)]) : '—' }}</td>
                                <td data-label="{{ __('concentrados.pellet_mm') }}">{{ $tramo->pellet ?? '—' }}</td>
                                <td data-label="{{ __('concentrados.concentrado_sugerido') }}">{{ $tramo->concentrado?->etiqueta() ?? __('concentrados.sin_concentrado_fijo') }}</td>
                                <td class="text-right">
                                    <x-formulario :action="route('admin.concentrados.racion.eliminar', $tramo)" metodo="DELETE" class="!space-y-0">
                                        <button class="min-h-12 text-sm font-semibold text-alerta hover:underline">{{ __('concentrados.eliminar_tramo') }}</button>
                                    </x-formulario>
                                </td>
                            </tr>
                        @endforeach
                    </x-tabla>

                    <details class="group mt-4 border-t border-renglon pt-3">
                        <summary class="flex min-h-12 cursor-pointer list-none items-center justify-between gap-3 text-base font-semibold text-marca-700 [&::-webkit-details-marker]:hidden">
                            <span>{{ __('concentrados.nuevo_tramo') }}</span>
                            <x-icono nombre="abajo" class="h-5 w-5 shrink-0 transition-transform group-open:rotate-180" />
                        </summary>
                        <x-formulario :action="route('admin.concentrados.racion.guardar')" class="mt-3">
                            <input type="hidden" name="especie_id" value="{{ $especie->id }}">
                            <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
                                <x-campo nombre="peso_desde_g" id="tramo-desde-{{ $especie->id }}" :etiqueta="__('concentrados.peso_desde_g')" tipo="number" step="0.01" min="0.1" max="99999" inputmode="decimal" required />
                                <x-campo nombre="peso_hasta_g" id="tramo-hasta-{{ $especie->id }}" :etiqueta="__('concentrados.peso_hasta_g')" tipo="number" step="0.01" min="0.1" max="99999" inputmode="decimal" required />
                                <x-campo nombre="porcentaje_biomasa" id="tramo-porc-{{ $especie->id }}" :etiqueta="__('concentrados.porcentaje_biomasa')" tipo="number" step="0.1" min="0.1" max="30" inputmode="decimal" required />
                                <x-campo nombre="tomas_dia" id="tramo-tomas-{{ $especie->id }}" :etiqueta="__('concentrados.tomas_dia')" tipo="number" min="1" max="12" inputmode="numeric" required />
                                <x-seleccion nombre="proteina_porcentaje" id="tramo-prot-{{ $especie->id }}" :etiqueta="__('concentrados.proteina_porcentaje')" :opciones="$proteinasTramo" />
                                <x-campo nombre="pellet" id="tramo-pellet-{{ $especie->id }}" :etiqueta="__('concentrados.pellet_mm')" />
                            </div>
                            <x-seleccion nombre="concentrado_id" id="tramo-conc-{{ $especie->id }}" :etiqueta="__('concentrados.concentrado_sugerido')" :opciones="$opcionesConcentrados" />
                            <x-boton variante="secundario">{{ __('concentrados.nuevo_tramo') }}</x-boton>
                        </x-formulario>
                    </details>
                </x-tarjeta>
            @endforeach
        </div>
    </section>
@endsection

