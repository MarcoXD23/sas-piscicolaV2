@props(['titulo', 'subtitulo' => null, 'datos', 'unidad' => '', 'decimales' => 0, 'vacio' => null])

{{--
    Barras horizontales de una sola serie para comparar magnitudes entre categorías (por ejemplo, lagos).
    $datos: lista de ['etiqueta' => texto, 'valor' => número], ya ordenada. Cada barra lleva su valor en la punta.
--}}
@php
    $numero = fn ($valor) => \App\Support\Formato::numero((float) $valor, $decimales);
    $maximo = (float) collect($datos)->max('valor');
@endphp

<figure {{ $attributes->class('rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200') }}>
    <figcaption class="mb-4">
        <p class="text-base font-semibold text-slate-900">{{ $titulo }}</p>
        @if ($subtitulo)
            <p class="text-sm text-slate-500">{{ $subtitulo }}</p>
        @endif
    </figcaption>

    @if (count($datos) === 0)
        <p class="text-base text-slate-500">{{ $vacio ?? __('componentes.sin_datos') }}</p>
    @else
        <ul class="space-y-2" aria-label="{{ $titulo }}">
            @foreach ($datos as $dato)
                @php
                    $ancho = $maximo > 0 ? (float) $dato['valor'] / $maximo * 100 : 0;
                    $valor = $numero($dato['valor']).($unidad ? ' '.$unidad : '');
                @endphp
                <li class="group flex items-center gap-3" tabindex="0" data-tip="{{ $dato['etiqueta'] }}: {{ $valor }}">
                    <span class="w-16 shrink-0 truncate text-sm font-medium text-slate-700" title="{{ $dato['etiqueta'] }}">{{ $dato['etiqueta'] }}</span>
                    <span class="block min-w-0 flex-1 border-l border-slate-300">
                        <span class="block h-5 rounded-r-[4px] bg-marca-500 transition group-hover:bg-marca-400 group-focus-visible:bg-marca-400"
                              style="width: {{ max($ancho, 1) }}%"></span>
                    </span>
                    <span class="w-24 shrink-0 text-right text-sm font-semibold tabular-nums text-slate-900">{{ $valor }}</span>
                </li>
            @endforeach
        </ul>
    @endif
</figure>
