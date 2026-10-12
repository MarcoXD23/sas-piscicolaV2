{{-- Un lote (siembra activa) en la ficha del lago de la lista de Lagos: cifras y botones (los modales van aparte).
     Con $numero_lote (especie con varios lotes) muestra su origen en lugar de la especie. --}}
@isset($numero_lote)
    {{-- Dentro de una especie con varios lotes: el origen del lote (el título «Lote N» va en el desplegable) --}}
    <div class="space-y-0.5 px-5 pt-4">
        <p class="text-sm text-tinta">{{ $siembra->textoOrigen() }}</p>
        @foreach ($siembra->trasladosSumados() as $traslado)
            <p class="text-sm text-tinta-suave">{{ __('lagos.lotes.recibio', ['peces' => $numero($traslado->cantidad), 'lago' => $traslado->lagoOrigen?->nombre, 'fecha' => $traslado->fecha->format('d/m/Y')]) }}</p>
        @endforeach
    </div>
@else
    <div class="flex items-center justify-between gap-3 px-5 pt-4">
        <h3 class="font-bold">{{ $siembra->nombre_especie }}</h3>
        <span class="text-sm text-tinta-suave">{{ __('lagos.desde_fecha', ['fecha' => $siembra->fecha->format('d/m/Y')]) }}</span>
    </div>
@endisset

<dl class="mt-3 grid grid-cols-2 gap-px border-y border-renglon bg-renglon">
    <div class="bg-ficha px-5 py-2.5">
        <dt class="text-sm text-tinta-suave">{{ __('lagos.peces_vivos') }}</dt>
        <dd class="text-xl font-bold tabular-nums">{{ $numero($siembra->peces_vivos) }}</dd>
    </div>
    <div class="bg-ficha px-5 py-2.5">
        <dt class="text-sm text-tinta-suave">{{ __('lagos.biomasa') }}</dt>
        <dd class="text-xl font-bold tabular-nums">{{ $numero($siembra->biomasa_kg, 2) }} <span class="text-sm font-normal text-tinta-suave">kg</span></dd>
    </div>
    <div class="bg-ficha px-5 py-2.5">
        <dt class="text-sm text-tinta-suave">{{ __('lagos.peso_promedio') }}</dt>
        <dd class="text-xl font-bold tabular-nums">{{ $numero($siembra->peso_actual_g, 2) }} <span class="text-sm font-normal text-tinta-suave">g</span></dd>
    </div>
    <div class="bg-ficha px-5 py-2.5">
        <dt class="text-sm text-tinta-suave">{{ __('lagos.dias_cultivo') }}</dt>
        <dd class="text-xl font-bold tabular-nums">{{ $siembra->dias_cultivo }}</dd>
    </div>
</dl>

@unless ($soloLectura)
<div class="space-y-2 p-4">
    <x-boton tipo="button" variante="secundario" class="w-full" data-abrir-modal="modal-mortalidad-{{ $siembra->id }}" :disabled="! $puedeRegistrar">
        <x-icono nombre="mortalidad" class="h-5 w-5" /> {{ __('lagos.mortalidad') }}
    </x-boton>
    {{-- Muestreo y Finalizar quedan a un paso: finalizar casi no se deshace y no debe estar junto a la acción diaria --}}
    @if ($puedeOperar)
        <details class="group">
            <summary class="flex min-h-12 cursor-pointer list-none items-center justify-center gap-1.5 rounded-ficha text-base font-semibold text-marca-700 hover:bg-papel-hondo [&::-webkit-details-marker]:hidden">
                {{ __('lagos.ficha.mas_acciones') }}
                <x-icono nombre="abajo" class="h-5 w-5 transition-transform group-open:rotate-180" />
            </summary>
            <div class="mt-2 grid grid-cols-2 gap-2">
                <x-boton tipo="button" variante="secundario" data-abrir-modal="modal-muestreo-{{ $siembra->id }}">
                    <x-icono nombre="biomasa" class="h-5 w-5" /> {{ __('lagos.muestreo') }}
                </x-boton>
                <x-boton tipo="button" variante="secundario" class="!text-alerta" data-abrir-modal="modal-finalizar-{{ $siembra->id }}">
                    <x-icono nombre="ok" class="h-5 w-5" /> {{ __('lagos.finalizar') }}
                </x-boton>
            </div>
        </details>
    @endif
</div>
@endunless
