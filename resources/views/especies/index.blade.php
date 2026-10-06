@extends('layouts.app')

@section('titulo', 'Guía de especies')

@section('contenido')
    <x-encabezado titulo="Guía de especies" subtitulo="Fichas técnicas de referencia de las especies que se pueden sembrar." />

    <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ($especies as $especie)
            <a href="{{ route('especies.show', $especie['slug']) }}"
               class="group flex flex-col overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200 transition hover:shadow-md hover:ring-marca-300 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-marca-600">
                <div class="relative aspect-[16/10] bg-marca-50">
                    @if ($especie['foto'])
                        <img src="{{ $especie['foto'] }}" alt="{{ $especie['nombre'] }}" loading="lazy" decoding="async"
                             class="h-full w-full object-cover transition group-hover:scale-[1.02]">
                    @else
                        <div class="flex h-full w-full flex-col items-center justify-center gap-2 text-marca-500">
                            <x-icono nombre="pez" class="h-12 w-12" />
                            <span class="text-sm font-medium">Foto pendiente</span>
                        </div>
                    @endif

                    <span @class([
                        'absolute left-3 top-3 rounded-full px-3 py-1 text-xs font-semibold shadow-sm',
                        'bg-amber-100 text-amber-900' => $especie['clima'] === \App\Support\CatalogoEspecies::CLIMA_CALIDO,
                        'bg-sky-100 text-sky-900' => $especie['clima'] === \App\Support\CatalogoEspecies::CLIMA_FRIO,
                    ])>Agua {{ $especie['clima'] === \App\Support\CatalogoEspecies::CLIMA_FRIO ? 'fría' : 'cálida' }}</span>
                </div>

                <div class="flex flex-1 flex-col p-5">
                    <h2 class="text-lg font-bold text-slate-900">{{ $especie['nombre'] }}</h2>
                    <p class="text-sm italic text-slate-500">{{ $especie['cientifico'] }}</p>

                    <dl class="mt-4 divide-y divide-slate-100 text-base">
                        @foreach ([
                            'Temperatura' => $especie['temperatura'][0].' a '.$especie['temperatura'][1].' °C',
                            'Cosecha' => $especie['meses_cosecha'],
                            'Peso comercial' => $especie['peso_comercial'],
                        ] as $etiqueta => $valor)
                            <div class="flex items-center justify-between gap-3 py-2">
                                <dt class="text-slate-500">{{ $etiqueta }}</dt>
                                <dd class="text-right font-semibold text-slate-900">{{ $valor }}</dd>
                            </div>
                        @endforeach
                    </dl>

                    <span class="mt-4 text-base font-semibold text-marca-700 group-hover:underline">Ver ficha completa →</span>
                </div>
            </a>
        @endforeach
    </div>

    <p class="mt-6 text-sm text-slate-500">
        Los valores son de referencia. Ajústalos con el asistente técnico de tu finca según el agua, el clima y el alimento que uses.
    </p>
@endsection
