{{-- Campos de una especie del catálogo. Recibe $especie (o null), $climas y $alimentaciones. --}}
<x-campo nombre="nombre" :etiqueta="__('especies.catalogo_finca.nombre')" :valor="$especie?->nombre" maxlength="80" required />
<x-campo nombre="nombre_cientifico" :etiqueta="__('especies.catalogo_finca.nombre_cientifico')" :valor="$especie?->nombre_cientifico" maxlength="120" />
<x-seleccion nombre="clima" :etiqueta="__('especies.catalogo_finca.clima')" :opciones="$climas" :valor="$especie?->clima ?? \App\Models\Especie::CALIDO" required />

<fieldset class="space-y-4 border-t border-renglon pt-5">
    <legend class="text-seccion font-bold">{{ __('especies.catalogo_finca.rangos') }}</legend>
    <div class="grid grid-cols-2 gap-4">
        <x-campo nombre="temp_min" :etiqueta="__('especies.catalogo_finca.temp_min')" tipo="number" step="0.1" min="0" max="45" inputmode="decimal" :valor="$especie?->temp_min" />
        <x-campo nombre="temp_max" :etiqueta="__('especies.catalogo_finca.temp_max')" tipo="number" step="0.1" min="0" max="45" inputmode="decimal" :valor="$especie?->temp_max" />
        <x-campo nombre="ph_min" :etiqueta="__('especies.catalogo_finca.ph_min')" tipo="number" step="0.01" min="0" max="14" inputmode="decimal" :valor="$especie?->ph_min" />
        <x-campo nombre="ph_max" :etiqueta="__('especies.catalogo_finca.ph_max')" tipo="number" step="0.01" min="0" max="14" inputmode="decimal" :valor="$especie?->ph_max" />
        <x-campo nombre="oxigeno_min" :etiqueta="__('especies.catalogo_finca.oxigeno_min')" tipo="number" step="0.1" min="0" max="20" inputmode="decimal" :valor="$especie?->oxigeno_min" />
    </div>
</fieldset>

<x-seleccion nombre="alimentacion" :etiqueta="__('especies.catalogo_finca.alimentacion')" :opciones="$alimentaciones" :valor="$especie?->alimentacion ?? ''" />
<p class="text-sm text-tinta-suave">{{ __('especies.catalogo_finca.rangos_opcionales') }}</p>
