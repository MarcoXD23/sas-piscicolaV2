@props(['renglones' => 3, 'cifra' => false])

{{--
    Esqueleto de carga: renglones grises que respiran mientras llega el dato, en vez de una pantalla en blanco.
    cifra: agrega arriba un bloque alto, del tamaño de una lectura grande (oxígeno, biomasa).
    El lector de pantalla oye «Cargando…»; los renglones son solo dibujo.
--}}
<div role="status" {{ $attributes->class('space-y-3') }}>
    <span class="sr-only">{{ __('componentes.cargando') }}</span>
    @if ($cifra)
        <div class="esqueleto h-10 w-32" aria-hidden="true"></div>
    @endif
    @for ($i = 0; $i < $renglones; $i++)
        {{-- Largos distintos, como renglones escritos de verdad --}}
        <div class="esqueleto h-4" style="width: {{ [92, 76, 84, 64][$i % 4] }}%" aria-hidden="true"></div>
    @endfor
</div>
