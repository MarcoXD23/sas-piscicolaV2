@props(['nombre', 'etiqueta', 'ayuda' => null])

@php
    $id = $attributes->get('id', 'campo-'.$nombre);
    $tieneError = $errors->has($nombre);
@endphp

{{-- Campo de contraseña de la libreta con un botón de ojo para mostrar u ocultar lo escrito (resources/js/contrasena.js).
     Nunca devuelve lo escrito en el HTML. El botón no le quita el foco al campo; la contraseña vuelve a ocultarse
     al enviar el formulario, al salir de la pantalla y al volver con «atrás». --}}
<div>
    <label for="{{ $id }}" class="mb-1.5 block text-base font-semibold text-tinta">{{ $etiqueta }}@if ($attributes->get('required'))<span class="ml-0.5 font-bold text-alerta" aria-hidden="true">*</span>@endif</label>

    <div class="relative">
        <input
            id="{{ $id }}"
            name="{{ $nombre }}"
            type="password"
            data-contrasena
            autocapitalize="off"
            autocorrect="off"
            spellcheck="false"
            @if ($tieneError) aria-invalid="true" aria-describedby="{{ $id }}-error" @endif
            {{ $attributes->except('id')->class([
                'block w-full min-h-12 rounded-ficha border bg-ficha py-3 pr-14 pl-4 text-base text-tinta',
                'transition-[border-color,box-shadow] duration-150 placeholder:text-tinta-suave/80 focus:outline-none focus:ring-4',
                'disabled:cursor-not-allowed disabled:bg-papel-hondo disabled:text-tinta-suave',
                'border-renglon-fuerte hover:border-tinta-suave focus:border-marca-700 focus:ring-marca-200/70' => ! $tieneError,
                'border-alerta bg-alerta-fondo/40 focus:border-alerta focus:ring-alerta-borde/60' => $tieneError,
            ]) }}
        >
        {{-- 48 × 48 px dentro del borde del campo; aria-pressed dice si se está mostrando --}}
        <button type="button" data-ver-contrasena aria-controls="{{ $id }}" aria-pressed="false"
                aria-label="{{ __('acceso.mostrar_contrasena') }}"
                data-texto-mostrar="{{ __('acceso.mostrar_contrasena') }}" data-texto-ocultar="{{ __('acceso.ocultar_contrasena') }}"
                class="absolute inset-y-0 right-0 flex w-12 min-h-11 items-center justify-center rounded-r-ficha text-tinta-suave transition-colors duration-150 hover:text-tinta focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-marca-200/70 disabled:cursor-not-allowed"
                @disabled($attributes->get('disabled'))>
            <x-icono nombre="ojo" class="h-6 w-6" data-icono-mostrar />
            <x-icono nombre="ojo-tachado" class="h-6 w-6" data-icono-ocultar hidden />
        </button>
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
