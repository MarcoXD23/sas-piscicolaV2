@props(['variante' => 'primario', 'tipo' => 'submit', 'href' => null])

@php
    $estilos = [
        'primario' => 'bg-gradient-to-b from-marca-500 to-marca-700 text-white shadow-brillo ring-1 ring-inset ring-white/10 '
            .'hover:-translate-y-px hover:from-marca-500 hover:to-marca-600 hover:shadow-lg focus-visible:outline-marca-600',
        'secundario' => 'bg-white text-marca-700 shadow-suave ring-1 ring-inset ring-marca-200 '
            .'hover:-translate-y-px hover:bg-marca-50 hover:ring-marca-300 focus-visible:outline-marca-600',
        'peligro' => 'bg-gradient-to-b from-red-500 to-red-700 text-white shadow-[0_6px_20px_-6px_rgb(220_38_38/0.55)] ring-1 ring-inset ring-white/10 '
            .'hover:-translate-y-px hover:to-red-600 focus-visible:outline-red-600',
    ];

    // presionable y data-onda: se hunde al tocar y suelta una onda (resources/css/app.css)
    $clases = 'presionable inline-flex min-h-12 items-center justify-center gap-2 rounded-xl px-5 py-3 text-base font-semibold '
        .'focus-visible:outline-2 focus-visible:outline-offset-2 disabled:cursor-not-allowed disabled:opacity-60 disabled:hover:translate-y-0 '
        .($estilos[$variante] ?? $estilos['primario']);
@endphp

@if ($href)
    <a href="{{ $href }}" data-onda {{ $attributes->class($clases) }}>{{ $slot }}</a>
@else
    <button type="{{ $tipo }}" data-onda {{ $attributes->class($clases) }}>{{ $slot }}</button>
@endif
