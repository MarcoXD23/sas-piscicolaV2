@props(['titulo', 'subtitulo' => null])

{{-- Título de la pantalla: tinta sólida, la jerarquía la dan tamaño y peso. En una pantalla con área (App\Support\Areas)
     lleva el ícono del área en un recuadro tenue de su color; sin área, solo el título (estilo neutro). --}}
@php $area = \App\Support\Areas::actual(); @endphp
<div {{ $attributes->class('mb-8 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between') }}>
    <div class="flex min-w-0 items-start gap-3">
        @if ($area)
            <span class="chip-area mt-1 flex h-11 w-11 shrink-0 items-center justify-center rounded-ficha" data-chip-area="{{ $area }}">
                <x-icono :nombre="\App\Support\Areas::ICONOS[$area]" class="h-6 w-6" />
            </span>
        @endif
        <div class="min-w-0">
        <h1 class="text-titulo font-bold text-balance text-tinta">{{ $titulo }}</h1>
        @if ($subtitulo)
            <p class="mt-1 text-base text-tinta-suave">{{ $subtitulo }}</p>
        @endif
        </div>
    </div>

    @if (trim($slot) !== '')
        <div class="flex flex-wrap gap-3">{{ $slot }}</div>
    @endif
</div>
