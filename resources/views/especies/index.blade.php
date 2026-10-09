@extends('layouts.app')

@section('titulo', __('especies.guia_especies'))

@section('contenido')
    <x-encabezado :titulo="__('especies.guia_especies')" :subtitulo="__('especies.fichas_tecnicas_referencia_especies_pueden')" />

    {{-- Una ficha por especie: foto, nombre, clima y los datos de cultivo que más se consultan en el campo --}}
    <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ($especies as $especie)
            <a href="{{ route('especies.show', $especie['id']) }}"
               class="group flex flex-col overflow-hidden rounded-ficha bg-ficha shadow-ficha ring-1 ring-renglon transition-shadow duration-150 hover:shadow-levantada focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-marca-700">
                <div class="aspect-[16/10] bg-papel-hondo">
                    @if ($especie['foto'])
                        <img src="{{ $especie['foto'] }}" alt="{{ $especie['nombre'] }}" loading="lazy" decoding="async" class="h-full w-full object-cover">
                    @else
                        <div class="flex h-full w-full flex-col items-center justify-center gap-2 text-marca-700">
                            <x-icono nombre="pez" class="h-12 w-12" />
                            <span class="text-sm font-semibold">{{ __('especies.foto_pendiente') }}</span>
                        </div>
                    @endif
                </div>

                <div class="flex flex-1 flex-col px-5 pt-4 pb-5">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <h2 class="text-seccion font-bold">{{ $especie['nombre'] }}</h2>
                            @if ($especie['cientifico'])
                                <p class="text-sm italic text-tinta-suave">{{ $especie['cientifico'] }}</p>
                            @endif
                        </div>
                        {{-- El clima no es un estado: insignia neutra con el termómetro --}}
                        <x-insignia tono="neutro" icono="termometro" class="mt-1 shrink-0">{{ __($especie['clima'] === \App\Models\Especie::FRIO ? 'especies.agua_fria' : 'especies.agua_calida') }}</x-insignia>
                    </div>

                    <dl class="mt-4 divide-y divide-renglon border-t border-renglon text-base">
                        {{-- Sin el dato en la ficha se muestra «sin dato»: nunca se inventa --}}
                        @foreach ([
                            __('especies.temperatura') => $especie['temperatura'] ? __('especies.rango_temperatura', ['min' => \App\Support\Formato::sinCeros($especie['temperatura'][0]), 'max' => \App\Support\Formato::sinCeros($especie['temperatura'][1])]) : null,
                            __('especies.cosecha') => $especie['meses_cosecha'],
                            __('especies.peso_comercial') => $especie['peso_comercial'],
                        ] as $etiqueta => $valor)
                            <div class="flex items-baseline justify-between gap-3 py-2.5">
                                <dt class="text-tinta-suave">{{ $etiqueta }}</dt>
                                <dd @class(['text-right font-bold', 'font-normal text-tinta-suave' => $valor === null])>{{ $valor ?? __('especies.sin_dato') }}</dd>
                            </div>
                        @endforeach
                    </dl>

                    <span class="mt-auto pt-4 text-base font-bold text-marca-700 underline decoration-renglon-fuerte underline-offset-4 group-hover:decoration-marca-700">{{ __('especies.ver_ficha_completa') }}</span>
                </div>
            </a>
        @endforeach
    </div>

    <p class="mt-8 max-w-[65ch] text-sm text-tinta-suave">
        {{ __('especies.valores_son_referencia_ajustalos_asistente') }}
    </p>
@endsection
