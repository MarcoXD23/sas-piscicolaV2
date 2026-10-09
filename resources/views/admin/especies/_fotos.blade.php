{{-- Fotos reales de la especie: subir, ordenar y borrar (solo el administrador). Recibe $especie. --}}
@php
    $fotos = $especie->fotos;
    $maximo = config('fotos.max_por_especie');
@endphp

<x-tarjeta class="mt-6 max-w-2xl" :titulo="__('especies.fotos.titulo')" :subtitulo="__('especies.fotos.subtitulo')">
    <p class="mb-4 text-sm text-tinta-suave">{{ __('especies.fotos.cupo', ['cantidad' => $fotos->count(), 'maximo' => $maximo]) }}</p>

    @if ($fotos->isNotEmpty())
        <ol class="mb-6 space-y-3">
            @foreach ($fotos as $foto)
                <li class="flex items-center gap-3 rounded-ficha bg-papel p-3 ring-1 ring-renglon">
                    <img src="{{ $foto->url() }}" alt="" loading="lazy" decoding="async" class="h-16 w-16 shrink-0 rounded-md object-cover ring-1 ring-renglon">
                    <div class="min-w-0 flex-1 text-sm">
                        @if ($loop->first)
                            <x-insignia tono="bien" icono="ok">{{ __('especies.fotos.portada') }}</x-insignia>
                        @endif
                        @if ($foto->autor)
                            <p class="truncate font-semibold">{{ __('especies.fotos.por', ['autor' => $foto->autor]) }}</p>
                        @endif
                        @if ($foto->licencia)
                            <p class="truncate text-tinta-suave">{{ __('especies.fotos.licencia_texto', ['licencia' => $foto->licencia]) }}</p>
                        @endif
                        <a href="{{ $foto->urlOriginal() }}" class="inline-flex min-h-11 items-center font-semibold text-marca-700 underline decoration-renglon-fuerte underline-offset-4 hover:decoration-marca-700">{{ __('especies.fotos.original') }}</a>
                    </div>
                    <div class="flex shrink-0 gap-1">
                        @foreach (['subir' => [! $loop->first, __('especies.fotos.subir_arriba'), 'rotate-180'], 'bajar' => [! $loop->last, __('especies.fotos.bajar'), '']] as $direccion => [$puede, $texto, $giro])
                            @if ($puede)
                                <form method="POST" action="{{ route('admin.especies.fotos.orden', [$especie, $foto]) }}">
                                    @csrf
                                    <input type="hidden" name="direccion" value="{{ $direccion }}">
                                    <button class="flex h-12 w-12 items-center justify-center rounded-ficha text-tinta-suave ring-1 ring-renglon-fuerte transition-colors hover:bg-ficha hover:text-tinta" aria-label="{{ $texto }}" title="{{ $texto }}"><x-icono nombre="abajo" class="h-5 w-5 {{ $giro }}" /></button>
                                </form>
                            @endif
                        @endforeach
                        <form method="POST" action="{{ route('admin.especies.fotos.destroy', [$especie, $foto]) }}" onsubmit="return confirm(@js(__('especies.fotos.confirmar_borrar')))">
                            @csrf
                            @method('DELETE')
                            <button class="flex h-12 w-12 items-center justify-center rounded-ficha text-tinta-suave ring-1 ring-renglon-fuerte transition-colors hover:bg-alerta-fondo hover:text-alerta" aria-label="{{ __('especies.fotos.borrar') }}" title="{{ __('especies.fotos.borrar') }}">
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
            <x-aviso tono="atencion">{{ __('especies.fotos.aviso_derechos') }}</x-aviso>
            <label class="block">
                <span class="mb-1.5 block text-base font-semibold text-tinta">{{ __('especies.fotos.archivo', ['maximo' => config('fotos.max_mb')]) }}</span>
                <input type="file" name="foto" accept="image/jpeg,image/png,image/webp" required
                       class="block w-full rounded-ficha border border-renglon-fuerte bg-ficha px-3 py-2.5 text-base text-tinta-suave file:mr-3 file:min-h-10 file:cursor-pointer file:rounded-md file:border-0 file:bg-tinta file:px-4 file:font-semibold file:text-white hover:file:bg-marca-900">
                @error('foto')
                    <span class="mt-1.5 flex items-start gap-1.5 text-sm font-semibold text-alerta"><x-icono nombre="alerta" class="mt-px h-4 w-4" /> {{ $message }}</span>
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
