@props(['icono' => 'pez', 'mensaje'])

<div data-revelar {{ $attributes->class('flex flex-col items-center rounded-2xl border-2 border-dashed border-marca-200/70 bg-gradient-to-b from-white to-marca-50/40 px-6 py-12 text-center') }}>
    <span class="relative mb-4 flex h-16 w-16 items-center justify-center rounded-2xl bg-gradient-to-br from-marca-100 to-sky-100 text-marca-600 shadow-suave ring-1 ring-marca-200/60">
        <x-icono :nombre="$icono" class="h-8 w-8" />
    </span>
    <p class="max-w-sm text-base text-slate-600">{{ $mensaje }}</p>

    @if (trim($slot) !== '')
        <div class="mt-5">{{ $slot }}</div>
    @endif
</div>
