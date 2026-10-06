@props(['accion', 'id'])

{{-- Botones para aprobar o rechazar una solicitud. El rechazo pide un motivo. --}}
<div class="mt-4 grid grid-cols-2 gap-2">
    <x-formulario :action="$accion" class="!space-y-0">
        <input type="hidden" name="decision" value="aprobado">
        <x-boton class="w-full">{{ __('componentes.aprobar') }}</x-boton>
    </x-formulario>
    <x-boton tipo="button" variante="secundario" data-abrir-modal="rechazo-{{ $id }}">{{ __('componentes.rechazar') }}</x-boton>
</div>

<x-modal id="rechazo-{{ $id }}" :titulo="__('componentes.rechazar_solicitud')">
    <x-formulario :action="$accion">
        <input type="hidden" name="decision" value="rechazado">
        <x-campo nombre="respuesta" id="respuesta-{{ $id }}" :etiqueta="__('componentes.motivo_rechazo')" required />
        <div class="grid grid-cols-2 gap-3 pt-2">
            <x-boton tipo="button" variante="secundario" data-cerrar-modal>{{ __('comun.cancelar') }}</x-boton>
            <x-boton variante="peligro">{{ __('componentes.rechazar') }}</x-boton>
        </div>
    </x-formulario>
</x-modal>
