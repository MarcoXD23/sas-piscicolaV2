@props(['etiqueta', 'valor', 'unidad' => null, 'icono' => null])

{{-- Una cifra de la libreta: etiqueta arriba, número grande con cifras de ancho fijo. --}}
<div {{ $attributes->class('flex items-center gap-4 rounded-ficha bg-ficha p-4 shadow-ficha ring-1 ring-renglon sm:p-5') }}>
    @if ($icono)
        <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-ficha bg-papel-hondo text-marca-700">
            <x-icono :nombre="$icono" />
        </span>
    @endif
    <div class="min-w-0">
        <p class="text-sm text-tinta-suave">{{ $etiqueta }}</p>
        <p class="mt-1 text-2xl font-bold tabular-nums text-tinta sm:text-3xl">
            {{ $valor }}
            @if ($unidad)
                <span class="text-base font-normal text-tinta-suave">{{ $unidad }}</span>
            @endif
        </p>
    </div>
</div>
