@extends('layouts.app')

@section('titulo', $especie['nombre'])

@section('contenido')
    <a href="{{ route('especies.index') }}" class="mb-4 inline-flex min-h-11 items-center gap-1 text-base font-medium text-marca-700 hover:underline">
        ← Guía de especies
    </a>

    <section class="mb-6 overflow-hidden rounded-3xl bg-white shadow-sm ring-1 ring-slate-200 md:grid md:grid-cols-2">
        <div class="aspect-[16/10] bg-marca-50 md:aspect-auto">
            @if ($especie['foto'])
                <img src="{{ $especie['foto'] }}" alt="{{ $especie['nombre'] }}" decoding="async" class="h-full w-full object-cover">
            @else
                <div class="flex h-full min-h-48 w-full flex-col items-center justify-center gap-2 text-marca-500">
                    <x-icono nombre="pez" class="h-16 w-16" />
                    <span class="text-sm font-medium">Foto pendiente</span>
                </div>
            @endif
        </div>

        <div class="p-5 sm:p-8">
            <h1 class="text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">{{ $especie['nombre'] }}</h1>
            <p class="mt-1 text-base italic text-slate-500">{{ $especie['cientifico'] }} · {{ $especie['familia'] }}</p>

            <p class="mt-4 rounded-xl bg-marca-50 p-4 text-base text-marca-900">
                <strong class="font-semibold">En policultivo:</strong> {{ $especie['policultivo'] }}
            </p>

            <dl class="mt-5 grid grid-cols-2 gap-3">
                @foreach ([
                    'Clima' => 'Agua '.($especie['clima'] === \App\Support\CatalogoEspecies::CLIMA_FRIO ? 'fría' : 'cálida'),
                    'Temperatura' => $especie['temperatura'][0].' a '.$especie['temperatura'][1].' °C',
                    'Oxígeno mínimo' => str_replace('.', ',', (string) $especie['oxigeno_min']).' mg/L',
                    'pH' => str_replace('.', ',', $especie['ph'][0].' a '.$especie['ph'][1]),
                    'Tiempo a cosecha' => $especie['meses_cosecha'],
                    'Peso comercial' => $especie['peso_comercial'],
                ] as $etiqueta => $valor)
                    <div class="rounded-xl bg-slate-50 p-3">
                        <dt class="text-sm text-slate-500">{{ $etiqueta }}</dt>
                        <dd class="text-base font-semibold text-slate-900">{{ $valor }}</dd>
                    </div>
                @endforeach
            </dl>
        </div>
    </section>

    <div class="grid gap-5 lg:grid-cols-3">
        <div class="space-y-5">
            <x-tarjeta titulo="Densidad de siembra">
                <dl class="space-y-3 text-base">
                    <div>
                        <dt class="text-sm text-slate-500">Estanque de tierra</dt>
                        <dd class="font-semibold text-slate-900">{{ $especie['densidad_tierra'] }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm text-slate-500">Geomembrana o concreto</dt>
                        <dd class="font-semibold text-slate-900">{{ $especie['densidad_tanque'] }}</dd>
                    </div>
                </dl>
            </x-tarjeta>

            <x-tarjeta titulo="Proteína del alimento">
                <dl class="space-y-3 text-base">
                    @foreach (['iniciacion' => 'Iniciación', 'levante' => 'Levante', 'engorde' => 'Engorde'] as $etapa => $texto)
                        <div class="flex items-center justify-between gap-3">
                            <dt class="text-slate-600">{{ $texto }}</dt>
                            <dd class="font-semibold text-slate-900">{{ $especie['proteina'][$etapa] }}</dd>
                        </div>
                    @endforeach
                </dl>
            </x-tarjeta>
        </div>

        <x-tarjeta titulo="Guía de manejo" class="lg:col-span-2">
            <ol class="space-y-4">
                @foreach ($especie['manejo'] as $titulo => $texto)
                    <li class="flex gap-4">
                        <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-marca-600 text-sm font-bold text-white">{{ $loop->iteration }}</span>
                        <div>
                            <h3 class="font-semibold text-slate-900">{{ $titulo }}</h3>
                            <p class="mt-0.5 text-base text-slate-600">{{ $texto }}</p>
                        </div>
                    </li>
                @endforeach
            </ol>
        </x-tarjeta>
    </div>

    <p class="mt-6 text-sm text-slate-500">
        Valores de referencia. Ajústalos con el asistente técnico de tu finca.
    </p>
@endsection
