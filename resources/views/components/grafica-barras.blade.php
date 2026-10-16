@props(['titulo', 'subtitulo' => null, 'datos', 'unidad' => '', 'decimales' => 0, 'vacio' => null])

{{--
    Barras horizontales de una sola serie para comparar magnitudes entre categorías (por ejemplo, lagos).
    $datos: lista de ['etiqueta' => texto, 'valor' => número], ya ordenada. Cada barra lleva su valor en la punta.
--}}
@php
    $numero = fn ($valor) => \App\Support\Formato::numero((float) $valor, $decimales);
    $maximo = (float) collect($datos)->max('valor');
@endphp

<figure {{ $attributes->class('rounded-ficha bg-ficha p-5 shadow-ficha ring-1 ring-renglon') }}>
    <figcaption class="mb-4">
        <p class="text-base font-bold text-tinta">{{ $titulo }}</p>
        @if ($subtitulo)
            <p class="text-sm text-tinta-suave">{{ $subtitulo }}</p>
        @endif
    </figcaption>

    @if (count($datos) === 0)
        <p class="text-base text-tinta-suave">{{ $vacio ?? __('componentes.sin_datos') }}</p>
    @else
        <ul class="space-y-2" aria-label="{{ $titulo }}">
            @foreach ($datos as $dato)
                @php
                    $ancho = $maximo > 0 ? (float) $dato['valor'] / $maximo * 100 : 0;
                    $valor = $numero($dato['valor']).($unidad ? ' '.$unidad : '');
                @endphp
                <li class="group flex items-center gap-3 rounded-lg outline-none focus-visible:bg-papel-hondo" tabindex="0" data-tip="{{ $dato['etiqueta'] }}: {{ $valor }}">
                    <span class="w-16 shrink-0 truncate text-sm text-tinta" title="{{ $dato['etiqueta'] }}">{{ $dato['etiqueta'] }}</span>
                    <span class="block min-w-0 flex-1 border-l border-renglon-fuerte">
                        {{-- SVG con el ancho como atributo (la Content-Security-Policy no permite style en el HTML) --}}
                        <svg class="block h-5 w-full" aria-hidden="true">
                            <rect height="100%" rx="3" width="{{ round(max($ancho, 1), 2) }}%" class="fill-marca-600 transition-colors group-hover:fill-marca-800 group-focus-visible:fill-marca-800" />
                        </svg>
                    </span>
                    <span class="w-24 shrink-0 text-right text-sm font-bold tabular-nums text-tinta">{{ $valor }}</span>
                </li>
            @endforeach
        </ul>
    @endif
</figure>
