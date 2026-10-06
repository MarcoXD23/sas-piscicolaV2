@props(['nombre', 'etiqueta', 'opciones' => [], 'valor' => null, 'ayuda' => null])

{{-- $opciones: lista de valores, o arreglo valor => texto. --}}
@php
    $id = $attributes->get('id', 'campo-'.$nombre);
    $tieneError = $errors->has($nombre);
    $seleccionado = (string) old($nombre, $valor);
    $esLista = array_is_list($opciones);
@endphp

<div>
    <label for="{{ $id }}" class="mb-1.5 block text-base font-medium text-slate-700">{{ $etiqueta }}</label>

    <select
        id="{{ $id }}"
        name="{{ $nombre }}"
        @if ($tieneError) aria-invalid="true" aria-describedby="{{ $id }}-error" @endif
        {{ $attributes->except('id')->class([
            'block w-full min-h-12 rounded-xl border bg-white px-4 py-3 text-base text-slate-900 shadow-sm focus:outline-none focus:ring-2',
            'border-slate-300 focus:border-marca-500 focus:ring-marca-200' => ! $tieneError,
            'border-red-400 focus:border-red-500 focus:ring-red-200' => $tieneError,
        ]) }}
    >
        @foreach ($opciones as $clave => $texto)
            @php $opcion = (string) ($esLista ? $texto : $clave); @endphp
            <option value="{{ $opcion }}" @selected($opcion === $seleccionado)>{{ $esLista ? ucfirst($texto) : $texto }}</option>
        @endforeach
    </select>

    @if ($ayuda && ! $tieneError)
        <p class="mt-1.5 text-sm text-slate-500">{{ $ayuda }}</p>
    @endif

    @error($nombre)
        <p id="{{ $id }}-error" class="mt-1.5 text-sm font-medium text-red-600">{{ $message }}</p>
    @enderror
</div>
