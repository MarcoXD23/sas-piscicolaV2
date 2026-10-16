{{-- Un lote (siembra activa) en el detalle del lago: cifras y botones. Va dentro de la tarjeta de su especie.
     Con $numero_lote (especie con varios lotes) muestra «Lote N» y su origen en lugar de la especie. --}}
<div class="flex items-center gap-4 px-5 py-4">
    @if ($siembra->foto_especie)
        <img src="{{ $siembra->foto_especie }}" alt="" loading="lazy" decoding="async" class="h-12 w-12 rounded-ficha object-cover ring-1 ring-renglon">
    @else
        <span class="flex h-12 w-12 items-center justify-center rounded-ficha bg-papel-hondo text-marca-700"><x-icono nombre="pez" /></span>
    @endif
    <div class="min-w-0 flex-1">
        @isset($numero_lote)
            <h4 class="text-lg font-bold">{{ __('lagos.lotes.lote_numero', ['numero' => $numero_lote]) }}</h4>
            <p class="text-sm text-tinta">{{ $siembra->textoOrigen() }}</p>
            @foreach ($siembra->trasladosSumados() as $traslado)
                <p class="text-sm text-tinta-suave">{{ __('lagos.lotes.recibio', ['peces' => $numero($traslado->cantidad), 'lago' => $traslado->lagoOrigen?->nombre, 'fecha' => $traslado->fecha->format('d/m/Y')]) }}</p>
            @endforeach
        @else
            <h3 class="text-lg font-bold">{{ $siembra->nombre_especie }}</h3>
        @endisset
        <p class="text-sm text-tinta-suave">{{ __('lagos.desde_dias', ['fecha' => $siembra->fecha->format('d/m/Y'), 'dias' => $siembra->dias_cultivo]) }}</p>
    </div>
</div>

{{-- Cifras de la siembra como renglones de la libreta --}}
{{-- En el teléfono, dos columnas (la biomasa en la fila de abajo) para que las cifras con su unidad no se partan; desde sm, tres --}}
<dl class="grid grid-cols-2 gap-px border-y border-renglon bg-renglon sm:grid-cols-3">
    @foreach ([
        [__('lagos.peces_vivos'), $numero($siembra->peces_vivos), null],
        [__('lagos.peso_promedio'), $numero($siembra->peso_actual_g, 2), 'g'],
        [__('lagos.biomasa'), $numero($siembra->biomasa_kg, 2), 'kg'],
    ] as $posicion => [$etiqueta, $valor, $unidad])
        <div @class(['bg-ficha px-4 py-3 sm:px-5', 'col-span-2 sm:col-span-1' => $posicion === 2])>
            <dt class="text-sm text-tinta-suave">{{ $etiqueta }}</dt>
            <dd class="whitespace-nowrap text-lg font-bold tabular-nums sm:text-xl">{{ $valor }}@if ($unidad) <span class="text-sm font-normal text-tinta-suave">{{ $unidad }}</span>@endif</dd>
        </div>
    @endforeach
</dl>

@php
    $estadoFase = \App\Support\EstadoFase::paraSiembra($lago, $siembra);
@endphp
@if ($estadoFase !== null)
    <div class="border-b border-renglon bg-papel-hondo/60 px-4 py-3.5 sm:px-5" data-comparacion-fase>
        <p class="text-sm font-bold text-tinta">{{ __('fases.comparacion.titulo', ['fase' => $estadoFase['fase']]) }}</p>

        @if (! $estadoFase['tiene_regla'])
            <p class="mt-1 text-sm text-tinta-suave">{{ __('fases.comparacion.sin_regla', ['fase' => $estadoFase['fase'], 'especie' => $siembra->nombre_especie]) }}</p>
        @else
            <dl class="mt-2.5 grid gap-2.5 sm:grid-cols-2">
                @if ($estadoFase['dias'] !== null)
                    <div class="rounded-ficha bg-ficha px-3.5 py-2.5 ring-1 ring-renglon">
                        <dt class="text-sm text-tinta-suave">{{ __('fases.comparacion.dias') }}</dt>
                        <dd class="mt-0.5 font-bold tabular-nums text-tinta">
                            {{ __('fases.comparacion.dias_valor', ['real' => $numero($estadoFase['dias']['real']), 'esperado' => $numero($estadoFase['dias']['esperado'])]) }}
                        </dd>
                        <p @class(['mt-0.5 text-sm font-semibold', 'text-atencion' => $estadoFase['dias']['atraso'] > 0, 'text-bien' => $estadoFase['dias']['atraso'] === 0])>
                            {{ $estadoFase['dias']['atraso'] > 0 ? __('fases.comparacion.dias_atraso', ['atraso' => $numero($estadoFase['dias']['atraso'])]) : __('fases.comparacion.dias_en_tiempo') }}
                        </p>
                    </div>
                @endif

                @if ($estadoFase['peso'] !== null)
                    <div class="rounded-ficha bg-ficha px-3.5 py-2.5 ring-1 ring-renglon">
                        <dt class="text-sm text-tinta-suave">{{ __('fases.comparacion.peso') }}</dt>
                        <dd class="mt-0.5 text-sm text-tinta">
                            {{ __('fases.comparacion.peso_meta', ['salida' => $numero($estadoFase['peso']['salida_g'], 1), 'esperado' => $numero($estadoFase['peso']['esperado_hoy_g'], 1)]) }}
                        </dd>
                        <p @class(['mt-0.5 text-sm font-semibold', 'text-atencion' => $estadoFase['peso']['atrasado'], 'text-bien' => ! $estadoFase['peso']['atrasado']])>
                            {{ $estadoFase['peso']['atrasado']
                                ? __('fases.comparacion.peso_atrasado', ['diferencia' => $numero(abs($estadoFase['peso']['diferencia_g']), 1)])
                                : __('fases.comparacion.peso_al_dia', ['diferencia' => ($estadoFase['peso']['diferencia_g'] >= 0 ? '+' : '−').$numero(abs($estadoFase['peso']['diferencia_g']), 1)]) }}
                        </p>
                    </div>
                @endif

                @if ($estadoFase['supervivencia'] !== null)
                    <div class="rounded-ficha bg-ficha px-3.5 py-2.5 ring-1 ring-renglon">
                        <dt class="text-sm text-tinta-suave">{{ __('fases.comparacion.supervivencia') }}</dt>
                        <dd class="mt-0.5 font-bold tabular-nums text-tinta">
                            {{ __('fases.comparacion.supervivencia_meta', ['real' => $numero($estadoFase['supervivencia']['real'], 1), 'esperada' => $numero($estadoFase['supervivencia']['esperada'], 1)]) }}
                        </dd>
                        <p @class(['mt-0.5 text-sm font-semibold', 'text-atencion' => $estadoFase['supervivencia']['baja'], 'text-bien' => ! $estadoFase['supervivencia']['baja']])>
                            {{ $estadoFase['supervivencia']['baja']
                                ? __('fases.comparacion.supervivencia_baja', ['diferencia' => $numero(abs($estadoFase['supervivencia']['diferencia']), 1)])
                                : __('fases.comparacion.supervivencia_bien') }}
                        </p>
                    </div>
                @endif

                @if ($estadoFase['densidad'] !== null)
                    <div class="rounded-ficha bg-ficha px-3.5 py-2.5 ring-1 ring-renglon">
                        <dt class="text-sm text-tinta-suave">{{ __('fases.comparacion.densidad') }}</dt>
                        <dd class="mt-0.5 font-bold tabular-nums text-tinta">
                            {{ __('fases.comparacion.densidad_valor', ['real' => $numero($estadoFase['densidad']['real'], 2)]) }}
                        </dd>
                        @if ($estadoFase['densidad']['min'] !== null && $estadoFase['densidad']['max'] !== null)
                            <p class="text-xs text-tinta-suave">
                                {{ __('fases.comparacion.densidad_rango', ['min' => $numero($estadoFase['densidad']['min'], 1), 'max' => $numero($estadoFase['densidad']['max'], 1)]) }}
                            </p>
                        @endif
                        <p @class(['mt-0.5 text-sm font-semibold', 'text-atencion' => $estadoFase['densidad']['estado'] !== 'en_rango', 'text-bien' => $estadoFase['densidad']['estado'] === 'en_rango'])>
                            {{ __('fases.comparacion.estados_densidad.'.$estadoFase['densidad']['estado']) }}
                        </p>
                    </div>
                @endif
            </dl>
        @endif
    </div>
@endif

@if ($puedeOperar)
    <div class="grid grid-cols-2 gap-2 p-4">
        @if ($lago->tratamientosEnRetiro->isNotEmpty())
            <x-boton tipo="button" class="!px-2" disabled :title="__('lagos.lago_en_retiro')">
                <x-icono nombre="pez" class="h-5 w-5" /> {{ __('lagos.cosechar') }}
            </x-boton>
        @else
            {{-- Página de pesaje, con este lago y esta especie ya elegidos --}}
            <x-boton class="!px-2" :href="route('admin.cosechas.create', ['siembra' => $siembra->id])">
                <x-icono nombre="pez" class="h-5 w-5" /> {{ __('lagos.cosechar') }}
            </x-boton>
        @endif
        <x-boton tipo="button" variante="secundario" class="!px-2" data-abrir-modal="modal-traslado-{{ $siembra->id }}" :disabled="$otrosLagos === []">
            <x-icono nombre="lagos" class="h-5 w-5" /> {{ __('lagos.trasladar') }}
        </x-boton>
    </div>
@endif
