@props(['titulo', 'subtitulo' => null])

{{-- Título de la pantalla: tinta sólida, la jerarquía la dan tamaño y peso (sin degradados ni adornos) --}}
<div {{ $attributes->class('mb-8 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between') }}>
    <div class="min-w-0">
        <h1 class="text-titulo font-bold text-balance text-tinta">{{ $titulo }}</h1>
        @if ($subtitulo)
            <p class="mt-1 text-base text-tinta-suave">{{ $subtitulo }}</p>
        @endif
    </div>

    @if (trim($slot) !== '')
        <div class="flex flex-wrap gap-3">{{ $slot }}</div>
    @endif
</div>
