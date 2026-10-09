@props(['nombre', 'etiqueta', 'opciones' => [], 'valor' => null, 'ayuda' => null])

{{-- $opciones: lista de valores, o arreglo valor => texto. --}}
@php
    $id = $attributes->get('id', 'campo-'.$nombre);
    $tieneError = $errors->has($nombre);
    $seleccionado = (string) old($nombre, $valor);
    $esLista = array_is_list($opciones);
@endphp

<div>
    <label for="{{ $id }}" class="mb-1.5 block text-base font-semibold text-tinta">{{ $etiqueta }}@if ($attributes->get('required'))<span class="ml-0.5 font-bold text-alerta" aria-hidden="true">*</span>@endif</label>

    <div class="relative">
        <select
            id="{{ $id }}"
            name="{{ $nombre }}"
            @if ($tieneError) aria-invalid="true" aria-describedby="{{ $id }}-error" @endif
            {{ $attributes->except('id')->class([
                'block w-full min-h-12 cursor-pointer appearance-none rounded-ficha border bg-ficha py-3 pr-11 pl-4 text-base text-tinta',
                'transition-[border-color,box-shadow] duration-150 focus:outline-none focus:ring-4',
                'disabled:cursor-not-allowed disabled:bg-papel-hondo disabled:text-tinta-suave',
                'border-renglon-fuerte hover:border-tinta-suave focus:border-marca-700 focus:ring-marca-200/70' => ! $tieneError,
                'border-alerta bg-alerta-fondo/40 focus:border-alerta focus:ring-alerta-borde/60' => $tieneError,
            ]) }}
        >
            @foreach ($opciones as $clave => $texto)
                @php $opcion = (string) ($esLista ? $texto : $clave); @endphp
                <option value="{{ $opcion }}" @selected($opcion === $seleccionado)>{{ $esLista ? ucfirst($texto) : $texto }}</option>
            @endforeach
        </select>
        {{-- Flecha propia (el select va sin la del navegador para verse igual en todos los teléfonos) --}}
        <x-icono nombre="abajo" class="pointer-events-none absolute top-1/2 right-4 h-5 w-5 -translate-y-1/2 text-tinta-suave" />
    </div>

    @if ($ayuda && ! $tieneError)
        <p class="mt-1.5 text-sm text-tinta-suave">{{ $ayuda }}</p>
    @endif

    @error($nombre)
        <p id="{{ $id }}-error" class="mt-1.5 flex items-start gap-1.5 text-sm font-semibold text-alerta">
            <x-icono nombre="alerta" class="mt-px h-4 w-4" /> {{ $message }}
        </p>
    @enderror
</div>
