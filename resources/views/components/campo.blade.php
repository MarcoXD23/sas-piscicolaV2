@props(['nombre', 'etiqueta', 'tipo' => 'text', 'valor' => null, 'ayuda' => null])

@php
    $id = $attributes->get('id', 'campo-'.$nombre);
    $tieneError = $errors->has($nombre);
@endphp

{{-- Campo de la libreta: etiqueta siempre visible, borde que se ve al sol, foco con anillo de marca y error con ícono --}}
<div>
    <label for="{{ $id }}" class="mb-1.5 block text-base font-semibold text-tinta">{{ $etiqueta }}@if ($attributes->get('required'))<span class="ml-0.5 font-bold text-alerta" aria-hidden="true">*</span>@endif</label>

    <input
        id="{{ $id }}"
        name="{{ $nombre }}"
        type="{{ $tipo }}"
        @if ($tipo !== 'password') value="{{ old($nombre, $valor) }}" @endif
        @if ($tieneError) aria-invalid="true" aria-describedby="{{ $id }}-error" @endif
        {{ $attributes->except('id')->class([
            'block w-full min-h-12 rounded-ficha border bg-ficha px-4 py-3 text-base text-tinta',
            'transition-[border-color,box-shadow] duration-150 placeholder:text-tinta-suave/80 focus:outline-none focus:ring-4',
            'disabled:cursor-not-allowed disabled:bg-papel-hondo disabled:text-tinta-suave',
            'border-renglon-fuerte hover:border-tinta-suave focus:border-marca-700 focus:ring-marca-200/70' => ! $tieneError,
            'border-alerta bg-alerta-fondo/40 focus:border-alerta focus:ring-alerta-borde/60' => $tieneError,
        ]) }}
    >

    @if ($ayuda && ! $tieneError)
        <p class="mt-1.5 text-sm text-tinta-suave">{{ $ayuda }}</p>
    @endif

    @error($nombre)
        <p id="{{ $id }}-error" class="mt-1.5 flex items-start gap-1.5 text-sm font-semibold text-alerta">
            <x-icono nombre="alerta" class="mt-px h-4 w-4" /> {{ $message }}
        </p>
    @enderror
</div>
