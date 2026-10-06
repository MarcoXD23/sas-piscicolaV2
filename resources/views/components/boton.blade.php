@props(['variante' => 'primario', 'tipo' => 'submit', 'href' => null])

@php
    $estilos = [
        'primario' => 'bg-marca-600 text-white hover:bg-marca-700 focus-visible:outline-marca-600',
        'secundario' => 'bg-white text-marca-700 ring-1 ring-inset ring-marca-200 hover:bg-marca-50 focus-visible:outline-marca-600',
        'peligro' => 'bg-red-600 text-white hover:bg-red-700 focus-visible:outline-red-600',
    ];

    $clases = 'inline-flex min-h-12 items-center justify-center gap-2 rounded-xl px-5 py-3 text-base font-semibold shadow-sm transition '
        .'focus-visible:outline-2 focus-visible:outline-offset-2 disabled:cursor-not-allowed disabled:opacity-60 '
        .($estilos[$variante] ?? $estilos['primario']);
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->class($clases) }}>{{ $slot }}</a>
@else
    <button type="{{ $tipo }}" {{ $attributes->class($clases) }}>{{ $slot }}</button>
@endif

