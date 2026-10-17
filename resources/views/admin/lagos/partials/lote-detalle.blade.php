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
        {{-- Trazabilidad: número de lote de la finca, proveedor de los alevinos y lote del proveedor (lo que haya) --}}
        @php
            $trazabilidad = array_filter([
                $siembra->numero_lote ? __('lagos.trazabilidad.numero_lote', ['numero' => $siembra->numero_lote]) : null,
                $siembra->nombreProveedor() ? __('lagos.trazabilidad.proveedor', ['proveedor' => $siembra->nombreProveedor()]) : null,
                $siembra->lote_alevinos ? __('lagos.trazabilidad.lote_proveedor', ['lote' => $siembra->lote_alevinos]) : null,
            ]);
        @endphp
        @if ($trazabilidad !== [])
            <p class="text-sm text-tinta-suave">{{ implode(' · ', $trazabilidad) }}</p>
        @endif
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
            @php
                // Lo que la regla no tiene se muestra como «sin dato», nunca como un valor esperado inventado
                $corto = fn (float $valor) => $numero($valor, fmod($valor, 1.0) === 0.0 ? 0 : 1);
                $dato = fn (?float $valor) => $valor === null ? __('fases.comparacion.sin_dato') : $corto($valor);
                $gramos = fn (?float $valor) => $valor === null ? __('fases.comparacion.sin_dato') : __('fases.comparacion.gramos', ['valor' => $corto($valor)]);
                ['peso' => $peso, 'supervivencia' => $supervivencia, 'densidad' => $densidad] = $estadoFase;
            @endphp
            <dl class="mt-2.5 grid gap-2.5 sm:grid-cols-3">
                <div class="rounded-ficha bg-ficha px-3.5 py-2.5 ring-1 ring-renglon">
                    <dt class="text-sm text-tinta-suave">{{ __('fases.comparacion.peso') }}</dt>
                    <dd class="mt-0.5 font-bold tabular-nums text-tinta">{{ $gramos($peso['real_g']) }}</dd>
                    <p class="text-xs text-tinta-suave">{{ __('fases.comparacion.peso_rango', ['entrada' => $gramos($peso['entrada_g']), 'salida' => $gramos($peso['salida_g'])]) }}</p>
                    @if ($peso['estado'] !== null)
                        <p @class(['mt-0.5 text-sm font-semibold', 'text-bien' => $peso['estado'] === \App\Support\EstadoFase::PESO_EN_FASE, 'text-atencion' => $peso['estado'] !== \App\Support\EstadoFase::PESO_EN_FASE])>
                            {{ __('fases.comparacion.estados_peso.'.$peso['estado']) }}
                        </p>
                    @endif
                </div>

                <div class="rounded-ficha bg-ficha px-3.5 py-2.5 ring-1 ring-renglon">
                    <dt class="text-sm text-tinta-suave">{{ __('fases.comparacion.supervivencia') }}</dt>
                    <dd class="mt-0.5 font-bold tabular-nums text-tinta">{{ $supervivencia['real'] === null ? __('fases.comparacion.sin_dato') : __('fases.comparacion.porcentaje', ['valor' => $corto($supervivencia['real'])]) }}</dd>
                    <p class="text-xs text-tinta-suave">{{ __('fases.comparacion.supervivencia_esperada', ['esperada' => $supervivencia['esperada'] === null ? __('fases.comparacion.sin_dato') : __('fases.comparacion.porcentaje', ['valor' => $corto($supervivencia['esperada'])])]) }}</p>
                    @if ($supervivencia['baja'] !== null)
                        <p @class(['mt-0.5 text-sm font-semibold', 'text-atencion' => $supervivencia['baja'], 'text-bien' => ! $supervivencia['baja']])>
                            {{ $supervivencia['baja'] ? __('fases.comparacion.supervivencia_baja') : __('fases.comparacion.supervivencia_bien') }}
                        </p>
                    @endif
                </div>

                <div class="rounded-ficha bg-ficha px-3.5 py-2.5 ring-1 ring-renglon">
                    <dt class="text-sm text-tinta-suave">{{ __('fases.comparacion.densidad') }}</dt>
                    <dd class="mt-0.5 font-bold tabular-nums text-tinta">{{ $densidad['real'] === null ? __('fases.comparacion.sin_area') : __('fases.comparacion.densidad_valor', ['real' => $numero($densidad['real'], 2)]) }}</dd>
                    <p class="text-xs text-tinta-suave">{{ $densidad['min'] === null && $densidad['max'] === null
                        ? __('fases.comparacion.densidad_sin_referencia')
                        : __('fases.comparacion.densidad_rango', ['min' => $dato($densidad['min']), 'max' => $dato($densidad['max'])]) }}</p>
                    @if ($densidad['estado'] !== null)
                        <p @class(['mt-0.5 text-sm font-semibold', 'text-atencion' => $densidad['estado'] !== 'en_rango', 'text-bien' => $densidad['estado'] === 'en_rango'])>
                            {{ __('fases.comparacion.estados_densidad.'.$densidad['estado']) }}
                        </p>
                    @endif
                </div>
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
