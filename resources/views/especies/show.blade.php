@extends('layouts.app')

@php
    use App\Models\Especie;
    use App\Support\Formato;
@endphp

@section('titulo', $especie['nombre'])

@section('contenido')
    {{-- Volver a la guía: la flecha sale del juego de íconos (no un carácter de texto) --}}
    <a href="{{ route('especies.index') }}" class="-ml-1 mb-2 inline-flex min-h-12 items-center gap-1 text-base font-semibold text-marca-700 underline decoration-renglon-fuerte underline-offset-4 hover:decoration-marca-700">
        <x-icono nombre="abajo" class="h-5 w-5 rotate-90" /> {{ __('especies.guia_especies_2') }}
    </a>

    <section class="mb-8 overflow-hidden rounded-ficha bg-ficha shadow-ficha ring-1 ring-renglon md:grid md:grid-cols-2">
        <figure class="flex flex-col border-b border-renglon md:border-r md:border-b-0">
            @if ($especie['foto'])
                <img src="{{ $especie['foto'] }}" alt="{{ $especie['nombre'] }}" decoding="async" class="aspect-[16/10] w-full flex-1 object-cover">
                {{-- Foto de referencia con licencia libre: autor, licencia y fuente, en un pie debajo (no encima de la imagen) --}}
                @if ($credito = $especie['foto_credito'])
                    <figcaption class="bg-papel-hondo px-4 py-2.5 text-sm leading-snug text-tinta-suave">
                        @if (isset($credito['nota']))
                            <span class="block font-semibold text-tinta">{{ $credito['nota'] }}</span>
                        @endif
                        {{ __('especies.foto_credito.autor', ['tipo' => $credito['tipo'], 'autor' => $credito['autor']]) }}
                        ·
                        @if (isset($credito['licencia_url']))
                            <a href="{{ $credito['licencia_url'] }}" target="_blank" rel="noopener noreferrer license" class="underline decoration-renglon-fuerte underline-offset-2 hover:text-tinta">{{ $credito['licencia'] }}</a>
                        @else
                            {{ $credito['licencia'] }}
                        @endif
                        · <a href="{{ $credito['fuente'] }}" target="_blank" rel="noopener noreferrer" class="underline decoration-renglon-fuerte underline-offset-2 hover:text-tinta">{{ __('especies.foto_credito.fuente') }}</a>
                        @if ($credito['modificada'])
                            · {{ __('especies.foto_credito.modificada') }}
                        @endif
                    </figcaption>
                @endif
            @else
                <div class="flex min-h-48 w-full flex-1 flex-col items-center justify-center gap-2 bg-papel-hondo text-marca-700">
                    <x-icono nombre="pez" class="h-16 w-16" />
                    <span class="text-sm font-semibold">{{ __('especies.foto_pendiente') }}</span>
                </div>
            @endif
        </figure>

        <div class="p-5 sm:p-8">
            <h1 class="text-titulo font-bold text-balance">{{ $especie['nombre'] }}</h1>
            @if ($especie['cientifico'] || $especie['familia'])
                <p class="mt-1 text-base italic text-tinta-suave">{{ collect([$especie['cientifico'], $especie['familia']])->filter()->join(' · ') }}</p>
            @endif
            @unless ($especie['activo'])
                <x-aviso tono="info" class="mt-4 text-sm">{{ __('especies.catalogo_finca.inactiva_guia') }}</x-aviso>
            @endunless

            @if ($especie['policultivo'])
                <x-aviso tono="info" icono="pez" class="mt-4">
                    <strong class="font-bold">{{ __('especies.policultivo') }}</strong> <span class="font-normal">{{ $especie['policultivo'] }}</span>
                </x-aviso>
            @endif

            {{-- Datos de la ficha de la finca, como renglones de lectura; lo que falta se muestra como «sin dato» (nunca se inventa) --}}
            <dl class="mt-6 grid grid-cols-2 gap-px overflow-hidden rounded-ficha bg-renglon ring-1 ring-renglon">
                @foreach ([
                    __('especies.clima') => __($especie['clima'] === Especie::FRIO ? 'especies.agua_fria' : 'especies.agua_calida'),
                    __('especies.temperatura') => $especie['temperatura'] ? __('especies.rango_temperatura', ['min' => Formato::sinCeros($especie['temperatura'][0]), 'max' => Formato::sinCeros($especie['temperatura'][1])]) : null,
                    __('especies.oxigeno_minimo') => $especie['oxigeno_min'] !== null ? __('especies.valor_oxigeno', ['valor' => Formato::sinCeros($especie['oxigeno_min'])]) : null,
                    __('especies.ph') => $especie['ph'] ? __('especies.rango', ['min' => Formato::sinCeros($especie['ph'][0]), 'max' => Formato::sinCeros($especie['ph'][1])]) : null,
                    __('especies.tiempo_cosecha') => $especie['meses_cosecha'],
                    __('especies.peso_comercial') => $especie['peso_comercial'],
                ] as $etiqueta => $valor)
                    <div class="bg-ficha px-4 py-3">
                        <dt class="text-sm text-tinta-suave">{{ $etiqueta }}</dt>
                        <dd @class(['mt-0.5 text-lg font-bold', 'text-base font-normal text-tinta-suave' => $valor === null])>{{ $valor ?? __('especies.sin_dato') }}</dd>
                    </div>
                @endforeach
            </dl>
        </div>
    </section>

    {{-- Galería de fotos reales (versión ligera), con su autor o fuente y su licencia en el pie --}}
    @if ($fotos->isNotEmpty())
        <section class="mb-8" aria-labelledby="galeria-titulo">
            <h2 id="galeria-titulo" class="mb-4 text-seccion font-bold">{{ __('especies.fotos.galeria') }}</h2>
            <div class="grid grid-cols-2 gap-4 sm:grid-cols-3">
                @foreach ($fotos as $foto)
                    <figure class="overflow-hidden rounded-ficha bg-ficha shadow-ficha ring-1 ring-renglon">
                        <img src="{{ $foto->url() }}" alt="{{ $especie['nombre'] }}" loading="lazy" decoding="async" class="aspect-[4/3] w-full object-cover">
                        <figcaption class="space-y-0.5 border-t border-renglon p-3 text-sm text-tinta-suave">
                            @if ($foto->autor)
                                <p class="font-semibold text-tinta">{{ __('especies.fotos.por', ['autor' => $foto->autor]) }}</p>
                            @endif
                            @if ($foto->licencia)
                                <p>{{ __('especies.fotos.licencia_texto', ['licencia' => $foto->licencia]) }}</p>
                            @endif
                            <p>{{ __('especies.fotos.subida_el', ['fecha' => \App\Support\Region::local($foto->created_at)->format('d/m/Y')]) }}</p>
                            @if ($veOriginal)
                                <a href="{{ $foto->urlOriginal() }}" class="inline-flex min-h-11 items-center font-semibold text-marca-700 underline decoration-renglon-fuerte underline-offset-4 hover:decoration-marca-700">{{ __('especies.fotos.original') }}</a>
                            @endif
                        </figcaption>
                    </figure>
                @endforeach
            </div>
        </section>
    @endif

    @if ($especie['orientativa'])
        <x-aviso tono="atencion" class="mb-6">{{ __('especies.ficha.orientativa') }}</x-aviso>
    @endif

    {{-- Ficha de la finca: cada parte vacía se muestra como «sin dato» (nunca se inventa) --}}
    <div class="grid items-start gap-6 lg:grid-cols-2">
        @foreach (['cultivo' => __('especies.ficha.campos_cultivo'), 'procesamiento' => __('especies.ficha.campos_procesamiento')] as $bloque => $etiquetas)
            <x-tarjeta :titulo="__('especies.ficha.'.$bloque)">
                <dl class="-mt-1 divide-y divide-renglon">
                    @foreach ($especie[$bloque] as $campo => $texto)
                        <div class="py-3">
                            <dt class="font-bold">{{ $etiquetas[$campo] }}</dt>
                            <dd @class(['mt-0.5 max-w-[65ch] text-base', 'text-tinta' => $texto, 'text-tinta-suave' => ! $texto])>{{ $texto ?? __('especies.sin_dato') }}</dd>
                        </div>
                    @endforeach
                </dl>
            </x-tarjeta>
        @endforeach

        <div class="space-y-6">
            <x-tarjeta :titulo="__('especies.ficha.descripcion')">
                <dl class="-mt-1 divide-y divide-renglon">
                    @foreach (['color', 'aspecto'] as $campo)
                        <div class="py-3">
                            <dt class="font-bold">{{ __('especies.ficha.'.$campo) }}</dt>
                            <dd @class(['mt-0.5 text-base', 'text-tinta' => $especie[$campo], 'text-tinta-suave' => ! $especie[$campo]])>{{ $especie[$campo] ?? __('especies.sin_dato') }}</dd>
                        </div>
                    @endforeach
                </dl>
            </x-tarjeta>

            @if ($especie['proteina'])
                <x-tarjeta :titulo="__('especies.proteina_alimento')">
                    <dl class="-mt-1 divide-y divide-renglon text-base">
                        @foreach (__('especies.etapas') as $etapa => $texto)
                            <div class="flex items-baseline justify-between gap-3 py-2.5">
                                <dt class="text-tinta-suave">{{ $texto }}</dt>
                                <dd class="font-bold tabular-nums">{{ $especie['proteina'][$etapa] ?? __('especies.sin_dato') }}</dd>
                            </div>
                        @endforeach
                    </dl>
                </x-tarjeta>
            @endif
        </div>
    </div>

    <p class="mt-8 max-w-[65ch] text-sm text-tinta-suave">
        {{ __('especies.valores_referencia_ajustalos_asistente_tecnico') }}
    </p>
@endsection
