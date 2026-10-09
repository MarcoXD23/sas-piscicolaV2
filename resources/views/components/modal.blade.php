@props(['id', 'titulo'])

{{--
    Ventana emergente. Se abre con cualquier botón que tenga data-abrir-modal="{{ $id }}".
    Si el formulario de adentro vuelve con errores de validación, se abre sola al cargar.
--}}
<dialog id="{{ $id }}" aria-labelledby="{{ $id }}-titulo"
        @if ($errors->any() && old('_modal') === $id) data-abrir-al-cargar @endif
        {{ $attributes->class('modal') }}>
    <div class="sticky top-0 z-10 border-b border-slate-100 bg-white/90 backdrop-blur">
        {{-- Asa de la hoja inferior (solo celular) --}}
        <span class="mx-auto mt-2 block h-1.5 w-10 rounded-full bg-slate-300 md:hidden" aria-hidden="true"></span>
        <div class="flex items-center justify-between gap-3 px-5 py-3 md:py-4">
            <h2 id="{{ $id }}-titulo" class="text-lg font-semibold tracking-tight text-slate-900">{{ $titulo }}</h2>
            <button type="button" data-cerrar-modal class="presionable -mr-2 flex h-12 w-12 items-center justify-center rounded-xl text-texto-suave hover:rotate-90 hover:bg-slate-100 hover:text-slate-800" aria-label="{{ __('componentes.cerrar') }}">
                <x-icono nombre="cerrar" />
            </button>
        </div>
    </div>

    <div class="px-5 py-5">
        @if ($errors->any() && old('_modal') === $id)
            <p role="alert" class="mb-5 flex animate-aparecer items-start gap-2 rounded-xl bg-red-50 p-4 text-base font-medium text-red-700 ring-1 ring-red-200">
                <x-icono nombre="alerta" class="mt-0.5 h-5 w-5" /> {{ $errors->first() }}
            </p>
        @endif

        {{ $slot }}
    </div>
</dialog>
