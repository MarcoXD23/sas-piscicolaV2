{{-- Registrar un obsequio de la jornada (F6c): quien lo registra elige quién lo autorizó; nunca él mismo. Recibe
     $jornada, $venta (con autorizadores y especies) y $presentaciones (id => nombre). --}}
@php $esteVolvio = old('_formulario') === 'obsequio'; @endphp

<details class="mt-4 rounded-ficha bg-papel ring-1 ring-renglon" @if ($esteVolvio) open @endif>
    <summary class="flex min-h-12 cursor-pointer items-center gap-2 px-4 font-semibold text-marca-700">
        <x-icono nombre="mas" class="h-5 w-5" /> {{ __('obsequios.nuevo') }}
    </summary>

    @if ($venta['autorizadores'] === [])
        <x-aviso tono="atencion" class="m-4">{{ __('obsequios.sin_autorizadores') }}</x-aviso>
    @else
        <x-formulario :action="route('admin.jornadas.obsequios.store', $jornada)" class="p-4">
            <input type="hidden" name="_formulario" value="obsequio">
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <x-seleccion nombre="especie_id" id="obsequio-especie" :etiqueta="__('obsequios.especie')" :opciones="$venta['especies']->all()" required />
                <x-seleccion nombre="presentacion_id" id="obsequio-presentacion" :etiqueta="__('obsequios.presentacion')" :opciones="['' => __('obsequios.sin_presentacion')] + $presentaciones->all()" />
                <x-campo nombre="kg" id="obsequio-kg" :etiqueta="__('obsequios.kg')" tipo="number" step="0.01" min="0.1" inputmode="decimal" required />
            </div>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <x-campo nombre="destinatario" id="obsequio-destinatario" :etiqueta="__('obsequios.destinatario')" maxlength="120" required />
                <x-seleccion nombre="autorizado_por" id="obsequio-autoriza" :etiqueta="__('obsequios.autorizo')" :opciones="$venta['autorizadores']" required />
            </div>
            <x-campo nombre="nota" id="obsequio-nota" :etiqueta="__('obsequios.nota')" maxlength="300" />
            <x-boton variante="secundario">{{ __('obsequios.nuevo') }}</x-boton>
        </x-formulario>
    @endif
</details>
