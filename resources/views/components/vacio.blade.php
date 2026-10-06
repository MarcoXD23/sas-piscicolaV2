@props(['icono' => 'pez', 'mensaje'])

<div {{ $attributes->class('flex flex-col items-center rounded-2xl border-2 border-dashed border-slate-200 bg-white px-6 py-10 text-center') }}>
    <span class="mb-3 flex h-14 w-14 items-center justify-center rounded-2xl bg-marca-50 text-marca-600">
        <x-icono :nombre="$icono" class="h-7 w-7" />
    </span>
    <p class="max-w-sm text-base text-slate-600">{{ $mensaje }}</p>

    @if (trim($slot) !== '')
        <div class="mt-5">{{ $slot }}</div>
    @endif
</div>
