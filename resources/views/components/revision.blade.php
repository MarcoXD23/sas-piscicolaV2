@props(['accion', 'id'])

{{-- Botones para aprobar o rechazar una solicitud. El rechazo pide un motivo. --}}
<div class="mt-4 grid grid-cols-2 gap-2">
    <x-formulario :action="$accion" class="!space-y-0">
        <input type="hidden" name="decision" value="aprobado">
        <x-boton class="w-full">Aprobar</x-boton>
    </x-formulario>
    <x-boton tipo="button" variante="secundario" data-abrir-modal="rechazo-{{ $id }}">Rechazar</x-boton>
</div>

<x-modal id="rechazo-{{ $id }}" titulo="Rechazar solicitud">
    <x-formulario :action="$accion">
        <input type="hidden" name="decision" value="rechazado">
        <x-campo nombre="respuesta" id="respuesta-{{ $id }}" etiqueta="Motivo del rechazo" required />
        <div class="grid grid-cols-2 gap-3 pt-2">
            <x-boton tipo="button" variante="secundario" data-cerrar-modal>Cancelar</x-boton>
            <x-boton variante="peligro">Rechazar</x-boton>
        </div>
    </x-formulario>
</x-modal>
