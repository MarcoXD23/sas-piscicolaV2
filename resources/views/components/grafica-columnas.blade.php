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

<figure {{ $attributes->class('rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200') }}>
    <figcaption class="mb-4">
        <p class="text-base font-semibold text-slate-900">{{ $titulo }}</p>
        @if ($subtitulo)
            <p class="text-sm text-slate-500">{{ $subtitulo }}</p>
        @endif
    </figcaption>

    <div class="flex gap-2">
        {{-- Eje Y: solo el techo y el cero --}}
        <div class="flex h-40 flex-col justify-between pb-0 text-right text-xs tabular-nums text-slate-500" aria-hidden="true">
            <span class="-translate-y-1/2">{{ $numero($techo) }}</span>
            <span class="translate-y-1/2">0</span>
        </div>

        <div class="relative min-w-0 flex-1">
            {{-- Líneas guía finas y discretas --}}
            <div class="pointer-events-none absolute inset-x-0 top-0 h-40" aria-hidden="true">
                <div class="absolute inset-x-0 top-0 border-t border-slate-100"></div>
                <div class="absolute inset-x-0 top-1/2 border-t border-slate-100"></div>
                <div class="absolute inset-x-0 bottom-0 border-t border-slate-300"></div>
            </div>

            <div class="relative flex h-40 items-end gap-[2px]" role="list" aria-label="{{ $titulo }}">
                @foreach ($datos as $indice => $dato)
                    @php
                        $alto = $techo > 0 ? (float) $dato['valor'] / $techo * 100 : 0;
                        $texto = $dato['fecha']->translatedFormat('j M').': '.$numero($dato['valor']).($unidad ? ' '.$unidad : '');
                    @endphp
                    <div role="listitem" tabindex="0" data-tip="{{ $texto }}" aria-label="{{ $texto }}"
                         class="group relative flex h-full flex-1 cursor-default items-end justify-center rounded-sm outline-none focus-visible:bg-marca-50">
                        @if ($indice === $indiceMaximo)
                            <span class="absolute left-1/2 -translate-x-1/2 whitespace-nowrap text-xs font-semibold tabular-nums text-slate-700"
                                  style="bottom: calc({{ $alto }}% + 4px)">{{ $numero($dato['valor']) }}</span>
                        @endif
                        @if ($alto > 0)
                            <span class="block w-full max-w-6 rounded-t-[4px] bg-marca-500 transition group-hover:bg-marca-400 group-focus-visible:bg-marca-400"
                                  style="height: {{ max($alto, 1.5) }}%"></span>
                        @endif
                    </div>
                @endforeach
            </div>

            {{-- Eje X: primer día, mitad y hoy --}}
            <div class="mt-1.5 flex justify-between text-xs text-slate-500" aria-hidden="true">
                <span>{{ $datos[0]['fecha']->translatedFormat('j M') }}</span>
                <span>{{ $datos[intdiv($total, 2)]['fecha']->translatedFormat('j M') }}</span>
                <span>{{ __('componentes.hoy') }}</span>
            </div>
        </div>
    </div>

    <details class="mt-3 text-sm">
        <summary class="min-h-11 cursor-pointer content-center font-medium text-marca-700">{{ __('componentes.ver_datos') }}</summary>
        <table class="mt-2 w-full text-left">
            <thead class="text-slate-500"><tr><th class="py-1 font-medium">{{ __('comun.fecha') }}</th><th class="py-1 text-right font-medium">{{ $unidad ? ucfirst($unidad) : __('componentes.valor') }}</th></tr></thead>
            <tbody class="divide-y divide-slate-100">
                @foreach (collect($datos)->reverse() as $dato)
                    <tr><td class="py-1">{{ $dato['fecha']->format('d/m/Y') }}</td><td class="py-1 text-right tabular-nums">{{ $numero($dato['valor']) }}</td></tr>
                @endforeach
            </tbody>
        </table>
    </details>
</figure>
