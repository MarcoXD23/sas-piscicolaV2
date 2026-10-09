@props(['titulo', 'subtitulo' => null, 'datos', 'unidad' => '', 'decimales' => 0])

{{--
    Columnas de una sola serie a lo largo del tiempo (sin leyenda: el título dice qué se grafica).
    $datos: lista de ['fecha' => Carbon, 'valor' => número]. Cada columna muestra su valor al pasar el
    puntero o con el teclado, se rotula solo el máximo y hay una tabla con todos los datos.
--}}
@php
    $numero = fn ($valor) => \App\Support\Formato::numero((float) $valor, $decimales);
    $maximo = (float) collect($datos)->max('valor');
    // Techo "redondo" del eje, lo más cercano posible al máximo para no desperdiciar altura
    $techo = 1;
    if ($maximo > 0) {
        $potencia = 10 ** floor(log10($maximo));
        foreach ([1, 1.5, 2, 2.5, 3, 4, 5, 6, 8, 10] as $factor) {
            if ($factor * $potencia >= $maximo) {
                $techo = $factor * $potencia;
                break;
            }
        }
    }
    $indiceMaximo = $maximo > 0 ? collect($datos)->search(fn ($dato) => (float) $dato['valor'] === $maximo) : null;
    $total = count($datos);
@endphp

<figure {{ $attributes->class('rounded-ficha bg-ficha p-5 shadow-ficha ring-1 ring-renglon') }}>
    <figcaption class="mb-4">
        <p class="text-base font-bold text-tinta">{{ $titulo }}</p>
        @if ($subtitulo)
            <p class="text-sm text-tinta-suave">{{ $subtitulo }}</p>
        @endif
    </figcaption>

    <div class="flex gap-2">
        {{-- Eje Y: solo el techo y el cero --}}
        <div class="flex h-40 flex-col justify-between pb-0 text-right text-xs tabular-nums text-tinta-suave" aria-hidden="true">
            <span class="-translate-y-1/2">{{ $numero($techo) }}</span>
            <span class="translate-y-1/2">0</span>
        </div>

        <div class="relative min-w-0 flex-1">
            {{-- Líneas guía finas y discretas --}}
            <div class="pointer-events-none absolute inset-x-0 top-0 h-40" aria-hidden="true">
                <div class="absolute inset-x-0 top-0 border-t border-renglon"></div>
                <div class="absolute inset-x-0 top-1/2 border-t border-renglon"></div>
                <div class="absolute inset-x-0 bottom-0 border-t border-renglon-fuerte"></div>
            </div>

            <div class="relative flex h-40 items-end gap-[2px]" role="list" aria-label="{{ $titulo }}">
                @foreach ($datos as $indice => $dato)
                    @php
                        $alto = $techo > 0 ? (float) $dato['valor'] / $techo * 100 : 0;
                        $texto = $dato['fecha']->translatedFormat('j M').': '.$numero($dato['valor']).($unidad ? ' '.$unidad : '');
                    @endphp
                    <div role="listitem" tabindex="0" data-tip="{{ $texto }}" aria-label="{{ $texto }}"
                         class="group relative flex h-full flex-1 cursor-default items-end justify-center rounded-sm outline-none focus-visible:bg-papel-hondo">
                        @if ($indice === $indiceMaximo)
                            <span class="absolute left-1/2 -translate-x-1/2 whitespace-nowrap text-xs font-bold tabular-nums text-tinta"
                                  style="bottom: calc({{ $alto }}% + 4px)">{{ $numero($dato['valor']) }}</span>
                        @endif
                        @if ($alto > 0)
                            <span class="block w-full max-w-6 rounded-t-[3px] bg-marca-600 transition-colors group-hover:bg-marca-800 group-focus-visible:bg-marca-800"
                                  style="height: {{ max($alto, 1.5) }}%"></span>
                        @endif
                    </div>
                @endforeach
            </div>

            {{-- Eje X: primer día, mitad y hoy --}}
            <div class="mt-1.5 flex justify-between text-xs text-tinta-suave" aria-hidden="true">
                <span>{{ $datos[0]['fecha']->translatedFormat('j M') }}</span>
                <span>{{ $datos[intdiv($total, 2)]['fecha']->translatedFormat('j M') }}</span>
                <span>{{ __('componentes.hoy') }}</span>
            </div>
        </div>
    </div>

    <details class="mt-3 text-sm">
        <summary class="min-h-12 cursor-pointer content-center font-semibold text-marca-700 underline decoration-renglon-fuerte underline-offset-4">{{ __('componentes.ver_datos') }}</summary>
        <table class="mt-2 w-full text-left">
            <thead class="text-tinta-suave"><tr><th class="py-1 font-medium">{{ __('comun.fecha') }}</th><th class="py-1 text-right font-medium">{{ $unidad ? ucfirst($unidad) : __('componentes.valor') }}</th></tr></thead>
            <tbody class="divide-y divide-renglon">
                @foreach (collect($datos)->reverse() as $dato)
                    <tr><td class="py-1">{{ $dato['fecha']->format('d/m/Y') }}</td><td class="py-1 text-right tabular-nums">{{ $numero($dato['valor']) }}</td></tr>
                @endforeach
            </tbody>
        </table>
    </details>
</figure>
