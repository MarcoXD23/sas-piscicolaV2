{{-- Fotos reales de la especie: subir, ordenar y borrar (solo el administrador). Recibe $especie. --}}
@php
    $fotos = $especie->fotos;
    $maximo = config('fotos.max_por_especie');
@endphp

<x-tarjeta class="mt-6 max-w-2xl" :titulo="__('especies.fotos.titulo')" :subtitulo="__('especies.fotos.subtitulo')">
    <p class="mb-4 text-sm text-slate-500">{{ __('especies.fotos.cupo', ['cantidad' => $fotos->count(), 'maximo' => $maximo]) }}</p>

    @if ($fotos->isNotEmpty())
        <ol class="mb-6 space-y-3">
            @foreach ($fotos as $foto)
                <li class="flex items-center gap-3 rounded-xl bg-slate-50 p-3 ring-1 ring-slate-200">
                    <img src="{{ $foto->url() }}" alt="" loading="lazy" decoding="async" class="h-16 w-16 shrink-0 rounded-lg object-cover">
                    <div class="min-w-0 flex-1 text-sm">
                        @if ($loop->first)
                            <span class="rounded-full bg-marca-100 px-2 py-0.5 font-semibold text-marca-800">{{ __('especies.fotos.portada') }}</span>
                        @endif
                        @if ($foto->autor)
                            <p class="truncate text-slate-700">{{ __('especies.fotos.por', ['autor' => $foto->autor]) }}</p>
                        @endif
                        @if ($foto->licencia)
                            <p class="truncate text-slate-500">{{ __('especies.fotos.licencia_texto', ['licencia' => $foto->licencia]) }}</p>
                        @endif
                        <a href="{{ $foto->urlOriginal() }}" class="text-marca-700 underline">{{ __('especies.fotos.original') }}</a>
                    </div>
                    <div class="flex shrink-0 gap-1">
                        @foreach (['subir' => [! $loop->first, __('especies.fotos.subir_arriba'), '↑'], 'bajar' => [! $loop->last, __('especies.fotos.bajar'), '↓']] as $direccion => [$puede, $texto, $flecha])
                            @if ($puede)
                                <form method="POST" action="{{ route('admin.especies.fotos.orden', [$especie, $foto]) }}">
                                    @csrf
                                    <input type="hidden" name="direccion" value="{{ $direccion }}">
                                    <button class="flex h-11 w-11 items-center justify-center rounded-xl text-lg text-slate-600 ring-1 ring-slate-200 hover:bg-white" aria-label="{{ $texto }}" title="{{ $texto }}">{{ $flecha }}</button>
                                </form>
                            @endif
                        @endforeach
                        <form method="POST" action="{{ route('admin.especies.fotos.destroy', [$especie, $foto]) }}" onsubmit="return confirm(@js(__('especies.fotos.confirmar_borrar')))">
                            @csrf
                            @method('DELETE')
                            <button class="flex h-11 w-11 items-center justify-center rounded-xl text-slate-500 ring-1 ring-slate-200 hover:bg-red-50 hover:text-red-600" aria-label="{{ __('especies.fotos.borrar') }}" title="{{ __('especies.fotos.borrar') }}">
                                <x-icono nombre="basura" class="h-5 w-5" />
                            </button>
                        </form>
                    </div>
                </li>
            @endforeach
        </ol>
    @endif

    @if ($fotos->count() < $maximo)
        <form method="POST" action="{{ route('admin.especies.fotos.store', $especie) }}" enctype="multipart/form-data" class="space-y-4">
            @csrf
            <p class="rounded-xl bg-amber-50 p-4 text-base font-medium text-amber-900">{{ __('especies.fotos.aviso_derechos') }}</p>
            <label class="block">
                <span class="mb-1.5 block text-base font-medium text-slate-700">{{ __('especies.fotos.archivo', ['maximo' => config('fotos.max_mb')]) }}</span>
                <input type="file" name="foto" accept="image/jpeg,image/png,image/webp" required
                       class="block w-full rounded-xl border border-slate-300 bg-white px-3 py-3 text-base text-slate-700 file:mr-3 file:rounded-lg file:border-0 file:bg-marca-50 file:px-3 file:py-2 file:font-semibold file:text-marca-800">
                @error('foto')
                    <span class="mt-1.5 block text-sm font-medium text-red-600">{{ $message }}</span>
                @enderror
            </label>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <x-campo nombre="autor" :etiqueta="__('especies.fotos.autor')" maxlength="150" />
                <x-campo nombre="licencia" :etiqueta="__('especies.fotos.licencia')" maxlength="150" />
            </div>
            <x-boton>{{ __('especies.fotos.subir') }}</x-boton>
        </form>
    @endif
</x-tarjeta>
