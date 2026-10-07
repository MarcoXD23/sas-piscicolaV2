@extends('layouts.app')

@php
    use App\Models\Especie;
    use App\Support\Formato;
@endphp

@section('titulo', $especie['nombre'])

@section('contenido')
    <a href="{{ route('especies.index') }}" class="mb-4 inline-flex min-h-11 items-center gap-1 text-base font-medium text-marca-700 hover:underline">
        {{ __('especies.guia_especies_2') }}
    </a>

    <section class="mb-6 overflow-hidden rounded-3xl bg-white shadow-sm ring-1 ring-slate-200 md:grid md:grid-cols-2">
        <div class="aspect-[16/10] bg-marca-50 md:aspect-auto">
            @if ($especie['foto'])
                <img src="{{ $especie['foto'] }}" alt="{{ $especie['nombre'] }}" decoding="async" class="h-full w-full object-cover">
            @else
                <div class="flex h-full min-h-48 w-full flex-col items-center justify-center gap-2 text-marca-500">
                    <x-icono nombre="pez" class="h-16 w-16" />
                    <span class="text-sm font-medium">{{ __('especies.foto_pendiente') }}</span>
                </div>
            @endif
        </div>

        <div class="p-5 sm:p-8">
            <h1 class="text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">{{ $especie['nombre'] }}</h1>
            @if ($especie['cientifico'] || $especie['familia'])
                <p class="mt-1 text-base italic text-slate-500">{{ collect([$especie['cientifico'], $especie['familia']])->filter()->join(' · ') }}</p>
            @endif
            @unless ($especie['activo'])
                <p class="mt-3 rounded-xl bg-slate-100 p-3 text-sm text-slate-700">{{ __('especies.catalogo_finca.inactiva_guia') }}</p>
            @endunless

            @if ($especie['policultivo'])
                <p class="mt-4 rounded-xl bg-marca-50 p-4 text-base text-marca-900">
                    <strong class="font-semibold">{{ __('especies.policultivo') }}</strong> {{ $especie['policultivo'] }}
                </p>
            @endif

            {{-- Datos de la ficha de la finca; lo que falta se muestra como «sin dato» (nunca se inventa) --}}
            <dl class="mt-5 grid grid-cols-2 gap-3">
                @foreach ([
                    __('especies.clima') => __($especie['clima'] === Especie::FRIO ? 'especies.agua_fria' : 'especies.agua_calida'),
                    __('especies.temperatura') => $especie['temperatura'] ? __('especies.rango_temperatura', ['min' => Formato::sinCeros($especie['temperatura'][0]), 'max' => Formato::sinCeros($especie['temperatura'][1])]) : null,
                    __('especies.oxigeno_minimo') => $especie['oxigeno_min'] !== null ? __('especies.valor_oxigeno', ['valor' => Formato::sinCeros($especie['oxigeno_min'])]) : null,
                    __('especies.ph') => $especie['ph'] ? __('especies.rango', ['min' => Formato::sinCeros($especie['ph'][0]), 'max' => Formato::sinCeros($especie['ph'][1])]) : null,
                    __('especies.tiempo_cosecha') => $especie['meses_cosecha'],
                    __('especies.peso_comercial') => $especie['peso_comercial'],
                ] as $etiqueta => $valor)
                    <div class="rounded-xl bg-slate-50 p-3">
                        <dt class="text-sm text-slate-500">{{ $etiqueta }}</dt>
                        <dd class="text-base font-semibold text-slate-900">{{ $valor ?? __('especies.sin_dato') }}</dd>
                    </div>
                @endforeach
            </dl>
        </div>
    </section>

    @if ($especie['densidad_tierra'] || $especie['proteina'] || $especie['manejo'])
        <div class="grid gap-5 lg:grid-cols-3">
            <div class="space-y-5">
                @if ($especie['densidad_tierra'])
                    <x-tarjeta :titulo="__('especies.densidad_siembra')">
                        <dl class="space-y-3 text-base">
                            <div>
                                <dt class="text-sm text-slate-500">{{ __('especies.estanque_tierra') }}</dt>
                                <dd class="font-semibold text-slate-900">{{ $especie['densidad_tierra'] }}</dd>
                            </div>
                            <div>
                                <dt class="text-sm text-slate-500">{{ __('especies.geomembrana_concreto') }}</dt>
                                <dd class="font-semibold text-slate-900">{{ $especie['densidad_tanque'] }}</dd>
                            </div>
                        </dl>
                    </x-tarjeta>
                @endif

                @if ($especie['proteina'])
                    <x-tarjeta :titulo="__('especies.proteina_alimento')">
                        <dl class="space-y-3 text-base">
                            @foreach (__('especies.etapas') as $etapa => $texto)
                                <div class="flex items-center justify-between gap-3">
                                    <dt class="text-slate-600">{{ $texto }}</dt>
                                    <dd class="font-semibold text-slate-900">{{ $especie['proteina'][$etapa] ?? __('especies.sin_dato') }}</dd>
                                </div>
                            @endforeach
                        </dl>
                    </x-tarjeta>
                @endif
            </div>

            @if ($especie['manejo'])
                <x-tarjeta :titulo="__('especies.guia_manejo')" class="lg:col-span-2">
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
            @endif
        </div>
    @else
        <x-vacio icono="pez" :mensaje="__('especies.catalogo_finca.sin_ficha')" />
    @endif

    <p class="mt-6 text-sm text-slate-500">
        {{ __('especies.valores_referencia_ajustalos_asistente_tecnico') }}
    </p>
@endsection
