@props(['nombre'])

{{-- Íconos de trazo propios (24x24); heredan el color del texto. --}}
<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
     aria-hidden="true" {{ $attributes->class('h-6 w-6 shrink-0') }}>
    @switch($nombre)
        @case('inicio')
            <path d="M3 11l9-8 9 8"/><path d="M5 10v10h14V10"/><path d="M10 20v-6h4v6"/>
            @break
        @case('lagos')
            <path d="M2 7c2-2 4-2 6 0s4 2 6 0 4-2 6 0"/><path d="M2 12c2-2 4-2 6 0s4 2 6 0 4-2 6 0"/><path d="M2 17c2-2 4-2 6 0s4 2 6 0 4-2 6 0"/>
            @break
        @case('bodega')
            <path d="M3 7l9-4 9 4v10l-9 4-9-4z"/><path d="M3 7l9 4 9-4"/><path d="M12 11v10"/>
            @break
        @case('personal')
            <circle cx="9" cy="8" r="3.5"/><path d="M2.5 20c0-3.6 2.9-6 6.5-6s6.5 2.4 6.5 6"/><circle cx="17" cy="9" r="2.5"/><path d="M17 14c2.6 0 4.5 1.8 4.5 4.5"/>
            @break
        @case('salir')
            <path d="M9 4H5a1 1 0 0 0-1 1v14a1 1 0 0 0 1 1h4"/><path d="M16 16l4-4-4-4"/><path d="M20 12H10"/>
            @break
        @case('pez')
            <path d="M3 12c3-5 9-6 14-2l4-3v10l-4-3c-5 4-11 3-14-2z"/><circle cx="8" cy="11" r=".6" fill="currentColor"/>
            @break
        @case('biomasa')
            <path d="M12 3v18"/><path d="M5 21h14"/><path d="M5 7h14"/><path d="M5 7l-3 7a3 3 0 0 0 6 0z"/><path d="M19 7l-3 7a3 3 0 0 0 6 0z"/>
            @break
        @case('alimentar')
            <path d="M3 11h18a9 9 0 0 1-18 0z"/><path d="M8 7c0-1.2 1-2 2-2"/><path d="M13 7c0-1.2 1-2 2-2"/>
            @break
        @case('mortalidad')
            <path d="M12 3l10 18H2z"/><path d="M12 10v5"/><path d="M12 18v.01"/>
            @break
        @case('mas')
            <path d="M12 5v14"/><path d="M5 12h14"/>
            @break
        @case('cerrar')
            <path d="M6 6l12 12"/><path d="M18 6L6 18"/>
            @break
        @case('ok')
            <path d="M5 12l5 5 9-10"/>
            @break
        @case('reloj')
            <circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>
            @break
        @case('basura')
            <path d="M4 7h16"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M6 7l1 13h10l1-13"/><path d="M9 7V4h6v3"/>
            @break
        @case('alerta')
            <circle cx="12" cy="12" r="9"/><path d="M12 7v6"/><path d="M12 16.5v.01"/>
            @break
    @endswitch
</svg>
