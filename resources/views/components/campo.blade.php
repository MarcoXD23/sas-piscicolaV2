@props(['nombre', 'etiqueta', 'tipo' => 'text', 'valor' => null, 'ayuda' => null])

@php
    $id = $attributes->get('id', 'campo-'.$nombre);
    $tieneError = $errors->has($nombre);
@endphp

<div>
    <label for="{{ $id }}" class="mb-1.5 block text-base font-medium text-slate-700">{{ $etiqueta }}</label>

    <input
        id="{{ $id }}"
        name="{{ $nombre }}"
        type="{{ $tipo }}"
        @if ($tipo !== 'password') value="{{ old($nombre, $valor) }}" @endif
        @if ($tieneError) aria-invalid="true" aria-describedby="{{ $id }}-error" @endif
        {{ $attributes->except('id')->class([
            'block w-full min-h-12 rounded-xl border bg-white px-4 py-3 text-base text-slate-900 shadow-sm',
            'placeholder:text-slate-400 focus:outline-none focus:ring-2',
            'border-slate-300 focus:border-marca-500 focus:ring-marca-200' => ! $tieneError,
            'border-red-400 focus:border-red-500 focus:ring-red-200' => $tieneError,
        ]) }}
    >

    @if ($ayuda && ! $tieneError)
        <p class="mt-1.5 text-sm text-slate-500">{{ $ayuda }}</p>
    @endif

    @error($nombre)
        <p id="{{ $id }}-error" class="mt-1.5 text-sm font-medium text-red-600">{{ $message }}</p>
    @enderror
</div>

