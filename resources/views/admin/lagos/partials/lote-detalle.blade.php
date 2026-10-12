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
<dl class="grid grid-cols-3 gap-px border-y border-renglon bg-renglon">
    @foreach ([
        [__('lagos.peces_vivos'), $numero($siembra->peces_vivos), null],
        [__('lagos.peso_promedio'), $numero($siembra->peso_actual_g, 2), 'g'],
        [__('lagos.biomasa'), $numero($siembra->biomasa_kg, 2), 'kg'],
    ] as [$etiqueta, $valor, $unidad])
        <div class="bg-ficha px-4 py-3 sm:px-5">
            <dt class="text-sm text-tinta-suave">{{ $etiqueta }}</dt>
            <dd class="text-lg font-bold tabular-nums sm:text-xl">{{ $valor }}@if ($unidad) <span class="text-sm font-normal text-tinta-suave">{{ $unidad }}</span>@endif</dd>
        </div>
    @endforeach
</dl>

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
