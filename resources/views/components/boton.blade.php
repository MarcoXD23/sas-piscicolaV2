@props(['variante' => 'primario', 'tipo' => 'submit', 'href' => null])

@php
    // Libreta de campo: botones planos y firmes. Al tocar se oscurecen y bajan 1 px; sin degradados ni brillos.
    $estilos = [
        'primario' => 'bg-marca-700 text-white shadow-ficha hover:bg-marca-800 active:bg-marca-900 focus-visible:outline-marca-700',
        'secundario' => 'bg-ficha text-tinta ring-1 ring-inset ring-renglon-fuerte hover:bg-papel-hondo active:bg-renglon focus-visible:outline-marca-700',
        'peligro' => 'bg-alerta text-white shadow-ficha hover:bg-[#7f1717] active:bg-[#661212] focus-visible:outline-alerta',
    ];

    $clases = 'inline-flex min-h-12 items-center justify-center gap-2 rounded-ficha px-5 py-3 text-base font-semibold '
        .'transition-[background-color,translate] duration-150 active:translate-y-px '
        .'focus-visible:outline-2 focus-visible:outline-offset-2 disabled:cursor-not-allowed disabled:opacity-50 disabled:active:translate-y-0 '
        .($estilos[$variante] ?? $estilos['primario']);
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->class($clases) }}>{{ $slot }}</a>
@else
    <button type="{{ $tipo }}" {{ $attributes->class($clases) }}>{{ $slot }}</button>
@endif
