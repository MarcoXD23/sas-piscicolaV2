@props(['id', 'titulo'])

{{--
    Ventana emergente. Se abre con cualquier botón que tenga data-abrir-modal="{{ $id }}".
    Si el formulario de adentro vuelve con errores de validación, se abre sola al cargar.
--}}
<dialog id="{{ $id }}" aria-labelledby="{{ $id }}-titulo"
        @if ($errors->any() && old('_modal') === $id) data-abrir-al-cargar @endif
        {{ $attributes->class('modal') }}>
    <div class="flex items-center justify-between gap-3 border-b border-slate-100 px-5 py-4">
        <h2 id="{{ $id }}-titulo" class="text-lg font-semibold text-slate-900">{{ $titulo }}</h2>
        <button type="button" data-cerrar-modal class="-mr-2 flex h-11 w-11 items-center justify-center rounded-xl text-slate-500 hover:bg-slate-100" aria-label="Cerrar">
            <x-icono nombre="cerrar" />
        </button>
    </div>

    <div class="px-5 py-5">
        {{ $slot }}
    </div>
</dialog>
