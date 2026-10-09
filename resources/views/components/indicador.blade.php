@props(['etiqueta', 'valor', 'unidad' => null, 'icono' => null])

<div data-revelar {{ $attributes->class('elevable relative flex items-center gap-4 overflow-hidden rounded-2xl bg-white p-5 shadow-suave ring-1 ring-slate-200') }}>
    {{-- Reflejo de agua en la esquina --}}
    <span class="pointer-events-none absolute -top-10 -right-10 h-28 w-28 rounded-full bg-gradient-to-br from-marca-100 to-sky-100 opacity-60 blur-2xl" aria-hidden="true"></span>

    @if ($icono)
        <span class="relative flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-marca-100 to-marca-50 text-marca-700 ring-1 ring-marca-200/60">
            <x-icono :nombre="$icono" />
        </span>
    @endif
    <div class="relative min-w-0">
        <p class="text-sm font-medium text-texto-suave">{{ $etiqueta }}</p>
        <p class="mt-0.5 text-2xl font-bold tracking-tight tabular-nums text-slate-900 sm:text-3xl">
            {{-- data-contar: el número sube desde cero al verse (resources/js/app.js) --}}
            <span data-contar>{{ $valor }}</span>
            @if ($unidad)
                <span class="text-base font-medium tracking-normal text-texto-suave">{{ $unidad }}</span>
            @endif
        </p>
    </div>
</div>
