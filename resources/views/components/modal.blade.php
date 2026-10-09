@props(['id', 'titulo'])

{{--
    Ventana emergente. Se abre con cualquier botón que tenga data-abrir-modal="{{ $id }}".
    Si el formulario de adentro vuelve con errores de validación, se abre sola al cargar.
    Hoja de la libreta: en celular sube desde abajo (con su asa), en pantallas anchas aparece centrada.
--}}
<dialog id="{{ $id }}" aria-labelledby="{{ $id }}-titulo"
        @if ($errors->any() && old('_modal') === $id) data-abrir-al-cargar @endif
        {{ $attributes->class('modal') }}>
    <div class="sticky top-0 z-10 border-b border-renglon bg-ficha">
        <span class="mx-auto mt-2 block h-1.5 w-10 rounded-full bg-renglon md:hidden" aria-hidden="true"></span>
        <div class="flex items-center justify-between gap-3 px-5 py-2 md:py-3">
            <h2 id="{{ $id }}-titulo" class="text-seccion font-bold text-tinta">{{ $titulo }}</h2>
            <button type="button" data-cerrar-modal class="-mr-2 flex h-12 w-12 shrink-0 items-center justify-center rounded-ficha text-tinta-suave transition-colors hover:bg-papel-hondo hover:text-tinta" aria-label="{{ __('componentes.cerrar') }}">
                <x-icono nombre="cerrar" />
            </button>
        </div>
    </div>

    <div class="px-5 py-5">
        @if ($errors->any() && old('_modal') === $id)
            <x-aviso tono="alerta" class="mb-5 animate-sacudir">{{ $errors->first() }}</x-aviso>
        @endif

        {{ $slot }}
    </div>
</dialog>
