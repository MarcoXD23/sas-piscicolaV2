@props(['nombre', 'etiqueta', 'opciones' => [], 'valor' => null, 'ayuda' => null])

{{-- $opciones: lista de valores, o arreglo valor => texto. --}}
@php
    $id = $attributes->get('id', 'campo-'.$nombre);
    $tieneError = $errors->has($nombre);
    $seleccionado = (string) old($nombre, $valor);
    $esLista = array_is_list($opciones);
@endphp

<div class="group/campo">
    <label for="{{ $id }}" class="mb-1.5 block text-base font-medium text-slate-700 transition-colors group-focus-within/campo:text-marca-700">{{ $etiqueta }}@if ($attributes->get('required'))<span class="ml-0.5 font-bold text-peligro" aria-hidden="true">*</span>@endif</label>

    <div class="relative">
        <select
            id="{{ $id }}"
            name="{{ $nombre }}"
            @if ($tieneError) aria-invalid="true" aria-describedby="{{ $id }}-error" @endif
            {{ $attributes->except('id')->class([
                'block w-full min-h-12 cursor-pointer appearance-none rounded-xl border bg-white py-3 pr-11 pl-4 text-base text-slate-900 shadow-suave',
                'transition duration-200 ease-salida focus:outline-none focus:ring-4',
                'border-slate-300 hover:border-slate-400 focus:border-marca-500 focus:ring-marca-100' => ! $tieneError,
                'border-red-400 bg-red-50/30 focus:border-red-500 focus:ring-red-100' => $tieneError,
            ]) }}
        >
            @foreach ($opciones as $clave => $texto)
                @php $opcion = (string) ($esLista ? $texto : $clave); @endphp
                <option value="{{ $opcion }}" @selected($opcion === $seleccionado)>{{ $esLista ? ucfirst($texto) : $texto }}</option>
            @endforeach
        </select>
        {{-- Flecha propia (el select va sin la del navegador para verse igual en todos los teléfonos) --}}
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"
             class="pointer-events-none absolute top-1/2 right-4 h-5 w-5 -translate-y-1/2 text-slate-500 transition-colors group-focus-within/campo:text-marca-600">
            <path d="m6 9 6 6 6-6"/>
        </svg>
    </div>

    @if ($ayuda && ! $tieneError)
        <p class="mt-1.5 text-sm text-texto-suave">{{ $ayuda }}</p>
    @endif

    @error($nombre)
        <p id="{{ $id }}-error" class="mt-1.5 flex animate-aparecer items-start gap-1.5 text-sm font-medium text-red-600">
            <x-icono nombre="alerta" class="mt-px h-4 w-4" /> {{ $message }}
        </p>
    @enderror
</div>
