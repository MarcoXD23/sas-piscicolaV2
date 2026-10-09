@props(['nombre', 'etiqueta', 'tipo' => 'text', 'valor' => null, 'ayuda' => null])

@php
    $id = $attributes->get('id', 'campo-'.$nombre);
    $tieneError = $errors->has($nombre);
@endphp

<div class="group/campo">
    <label for="{{ $id }}" class="mb-1.5 block text-base font-medium text-slate-700 transition-colors group-focus-within/campo:text-marca-700">{{ $etiqueta }}@if ($attributes->get('required'))<span class="ml-0.5 font-bold text-peligro" aria-hidden="true">*</span>@endif</label>

    <input
        id="{{ $id }}"
        name="{{ $nombre }}"
        type="{{ $tipo }}"
        @if ($tipo !== 'password') value="{{ old($nombre, $valor) }}" @endif
        @if ($tieneError) aria-invalid="true" aria-describedby="{{ $id }}-error" @endif
        {{ $attributes->except('id')->class([
            'block w-full min-h-12 rounded-xl border bg-white px-4 py-3 text-base text-slate-900 shadow-suave',
            'transition duration-200 ease-salida placeholder:text-slate-500 focus:outline-none focus:ring-4',
            'border-slate-300 hover:border-slate-400 focus:border-marca-500 focus:ring-marca-100' => ! $tieneError,
            'border-red-400 bg-red-50/30 focus:border-red-500 focus:ring-red-100' => $tieneError,
        ]) }}
    >

    @if ($ayuda && ! $tieneError)
        <p class="mt-1.5 text-sm text-texto-suave">{{ $ayuda }}</p>
    @endif

    @error($nombre)
        <p id="{{ $id }}-error" class="mt-1.5 flex animate-aparecer items-start gap-1.5 text-sm font-medium text-red-600">
            <x-icono nombre="alerta" class="mt-px h-4 w-4" /> {{ $message }}
        </p>
    @enderror
</div>
