@props(['etiqueta', 'valor', 'unidad' => null, 'icono' => null])

<div {{ $attributes->class('flex items-center gap-4 rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200') }}>
    @if ($icono)
        <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-marca-50 text-marca-700">
            <x-icono :nombre="$icono" />
        </span>
    @endif
    <div class="min-w-0">
        <p class="text-sm font-medium text-slate-500">{{ $etiqueta }}</p>
        <p class="mt-0.5 text-2xl font-bold tabular-nums text-slate-900 sm:text-3xl">
            {{ $valor }}
            @if ($unidad)
                <span class="text-base font-medium text-slate-500">{{ $unidad }}</span>
            @endif
        </p>
    </div>
</div>
