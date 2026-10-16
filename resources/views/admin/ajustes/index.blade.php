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

            <p class="text-sm text-tinta-suave">{{ __('region.ajustes.hora_actual', ['hora' => \App\Support\Region::ahora()->formatoHora('d/m/Y H:i')]) }}</p>

            <fieldset class="space-y-5 border-t border-renglon pt-5">
                <legend class="mb-1 text-lg font-semibold text-tinta">{{ __('alertas.ajustes.titulo') }}</legend>
                <x-campo nombre="alerta_stock_dias" :etiqueta="__('alertas.ajustes.stock_dias')" tipo="number" min="1" max="90" inputmode="numeric"
                         :valor="$finca->alerta_stock_dias" :ayuda="__('alertas.ajustes.stock_dias_ayuda')" required />
                <div class="grid grid-cols-2 gap-4">
                    <x-campo nombre="alerta_mortalidad_porcentaje" :etiqueta="__('alertas.ajustes.mortalidad_porcentaje')" tipo="number" step="0.1" min="0.1" max="100" inputmode="decimal"
                             :valor="$finca->alerta_mortalidad_porcentaje" required />
                    <x-campo nombre="alerta_mortalidad_dias" :etiqueta="__('alertas.ajustes.mortalidad_dias')" tipo="number" min="1" max="60" inputmode="numeric"
                             :valor="$finca->alerta_mortalidad_dias" required />
                </div>
                <p class="text-sm text-tinta-suave">{{ __('alertas.ajustes.mortalidad_ayuda') }}</p>
                <x-campo nombre="alerta_despacho_dias" :etiqueta="__('alertas.ajustes.despacho_dias')" tipo="number" min="1" max="30" inputmode="numeric"
                         :valor="$finca->alerta_despacho_dias" :ayuda="__('alertas.ajustes.despacho_dias_ayuda')" required />
            </fieldset>

            <x-seleccion nombre="formato_hora" :etiqueta="__('region.ajustes.formato_hora')" :valor="$finca->formato_hora"
                         :opciones="$formatosHora" :ayuda="__('region.ajustes.formato_hora_ayuda')" />

            <x-seleccion nombre="unidad_peso_muestreo" :etiqueta="__('region.ajustes.unidad_peso_muestreo')" :valor="$finca->unidad_peso_muestreo"
                         :opciones="$unidadesPeso" :ayuda="__('region.ajustes.unidad_peso_muestreo_ayuda')" required />

            <x-boton>{{ __('region.ajustes.guardar') }}</x-boton>
        </x-formulario>
    </x-tarjeta>

    {{-- Operación de la finca: ajustes del catálogo (App\Support\Ajustes\CatalogoAjustes), cada uno con su valor por defecto --}}
    <x-tarjeta class="mt-6 max-w-2xl" :titulo="__('ajustes_finca.titulo')" :subtitulo="__('ajustes_finca.subtitulo')">
        <x-formulario :action="route('admin.ajustes.operacion')" metodo="PUT">
            @foreach ($seccionesOperacion as $seccion => $claves)
                <fieldset class="space-y-5">
                    <legend class="mb-1 text-lg font-semibold text-tinta">{{ __('ajustes_finca.secciones.'.$seccion) }}</legend>
                    @foreach ($claves as $clave)
                        @php $definicion = \App\Support\Ajustes\CatalogoAjustes::definicion($clave); @endphp
                        @if ($definicion['tipo'] === \App\Support\Ajustes\CatalogoAjustes::TIPO_ENTERO)
                            <x-campo :nombre="\App\Support\Ajustes\CatalogoAjustes::campo($clave)" :etiqueta="__('ajustes_finca.'.$clave.'.etiqueta')"
                                     tipo="number" :min="$definicion['min']" :max="$definicion['max']" inputmode="numeric"
                                     :valor="$ajustesOperacion[$clave]" :ayuda="__('ajustes_finca.'.$clave.'.ayuda')" required />
                        @else
                            <x-seleccion :nombre="\App\Support\Ajustes\CatalogoAjustes::campo($clave)" :etiqueta="__('ajustes_finca.'.$clave.'.etiqueta')"
                                         :valor="$ajustesOperacion[$clave]" :ayuda="__('ajustes_finca.'.$clave.'.ayuda')" required
                                         :opciones="collect($definicion['opciones'])->mapWithKeys(fn ($opcion) => [$opcion => \App\Support\Ajustes\AjustesFinca::textoOpcion($clave, $opcion)])->all()" />
                        @endif
                    @endforeach
                </fieldset>
            @endforeach

            <x-boton>{{ __('ajustes_finca.guardar') }}</x-boton>
        </x-formulario>
    </x-tarjeta>

    {{-- Fases de los lagos y reglas por especie (F2) --}}
    <x-tarjeta class="mt-6 max-w-2xl" :titulo="__('fases.titulo')" :subtitulo="__('fases.subtitulo')">
        <x-boton variante="secundario" :href="route('admin.fases.index')">{{ __('fases.enlace') }}</x-boton>
    </x-tarjeta>

    {{-- Catálogo de concentrados y tabla de ración por especie (F3) --}}
    <x-tarjeta class="mt-6 max-w-2xl" :titulo="__('concentrados.titulo')" :subtitulo="__('concentrados.subtitulo')">
        <x-boton variante="secundario" :href="route('admin.concentrados.index')">{{ __('concentrados.enlace') }}</x-boton>
    </x-tarjeta>

    {{-- Exportar o eliminar los datos de la finca --}}
    <x-tarjeta class="mt-6 max-w-2xl" :titulo="__('datos.titulo')" :subtitulo="__('datos.subtitulo')">
        <x-boton variante="secundario" :href="route('admin.datos.index')">{{ __('datos.enlace') }}</x-boton>
    </x-tarjeta>

    {{-- Pesos de las vacías: los cambia el administrador; aquí solo se consultan --}}
    <x-tarjeta class="mt-6 max-w-2xl" :titulo="__('cosechas.ajustes.titulo')" :subtitulo="__('cosechas.ajustes.subtitulo')">
        <dl class="grid grid-cols-2 gap-4 text-base">
            <div>
                <dt class="text-sm text-tinta-suave">{{ __('cosechas.peso_canasta_vacia') }}</dt>
                <dd class="font-semibold tabular-nums text-tinta">{{ \App\Support\CalculoPesaje::kg($finca->peso_canasta_kg) }}</dd>
            </div>
            <div>
                <dt class="text-sm text-tinta-suave">{{ __('cosechas.peso_canastilla_vacia') }}</dt>
                <dd class="font-semibold tabular-nums text-tinta">{{ \App\Support\CalculoPesaje::kg($finca->peso_canastilla_kg) }}</dd>
            </div>
        </dl>
        @if (auth()->user()->tieneRol(\App\Models\Rol::ADMINISTRADOR))
            <x-boton variante="secundario" class="mt-4" :href="route('admin.ajustes-pesaje.edit')">{{ __('cosechas.ajustes.enlace') }}</x-boton>
        @else
            <p class="mt-4 text-sm text-tinta-suave">{{ __('cosechas.ajustes.solo_lectura') }}</p>
        @endif
    </x-tarjeta>
@endsection
