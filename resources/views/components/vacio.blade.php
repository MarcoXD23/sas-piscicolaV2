@props(['icono' => 'pez', 'mensaje'])

{{-- Estado vacío: un ícono, qué falta y (en el slot) la acción que lo resuelve. Nunca una pantalla en blanco. --}}
<div {{ $attributes->class('flex flex-col items-center rounded-ficha bg-ficha px-6 py-12 text-center shadow-ficha ring-1 ring-renglon') }}>
    <span class="mb-4 flex h-14 w-14 items-center justify-center rounded-full bg-papel-hondo text-marca-700">
        <x-icono :nombre="$icono" class="h-7 w-7" />
    </span>
    <p class="max-w-sm text-base text-tinta">{{ $mensaje }}</p>

    @if (trim($slot) !== '')
        <div class="mt-6">{{ $slot }}</div>
    @endif
</div>
