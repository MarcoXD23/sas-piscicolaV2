@extends('layouts.app')

@section('titulo', __('conversion.titulo'))

@section('contenido')
    @php
        $kg = fn ($valor) => \App\Support\Formato::numero((float) $valor, 1);
        $fca = fn (array $fila) => $fila['fca'] !== null ? \App\Support\Formato::numero($fila['fca'], 2) : '—';
        $motivo = fn (array $fila) => $fila['motivo'] ? __('conversion.motivos.'.$fila['motivo']) : null;
    @endphp

    <x-encabezado :titulo="__('conversion.titulo')" :subtitulo="__('conversion.subtitulo')" />

    <section class="mb-8">
        <h2 class="mb-3 text-lg font-semibold text-slate-900">{{ __('conversion.por_lago') }}</h2>
        <x-tabla :columnas="[__('conversion.lago_siembra'), __('conversion.alimento'), __('conversion.ganancia'), __('conversion.fca')]" :vacio="__('conversion.sin_siembras')">
            @foreach ($porLago as $item)
                <tr class="bg-slate-50">
                    <td data-label="{{ __('conversion.lago_siembra') }}" class="font-semibold text-slate-900">{{ __('lagos.lago_nombre', ['lago' => $item['lago']->nombre]) }}</td>
                    <td data-label="{{ __('conversion.alimento') }}" class="tabular-nums">{{ $kg($item['total']['alimento_kg']) }} kg</td>
                    <td data-label="{{ __('conversion.ganancia') }}" class="tabular-nums">{{ $kg($item['total']['ganancia_kg']) }} kg</td>
                    <td data-label="{{ __('conversion.fca') }}" class="font-bold tabular-nums">{{ $fca($item['total']) }}
                        @if ($motivo($item['total']))<span class="block text-xs font-normal text-slate-500">{{ $motivo($item['total']) }}</span>@endif
                    </td>
                </tr>
                @foreach ($item['siembras'] as $fila)
                    <tr>
                        <td data-label="{{ __('conversion.lago_siembra') }}" class="pl-8">
                            {{ $fila['siembra']->nombre_especie }}
                            <span class="block text-sm text-slate-500">
                                @if ($fila['hasta'])
                                    {{ __('conversion.periodo', ['desde' => $fila['siembra']->fecha->format('d/m/Y'), 'hasta' => \Carbon\CarbonImmutable::parse($fila['hasta'])->format('d/m/Y')]) }}
                                @endif
                                @if (count($item['siembras']) > 1) · {{ __('conversion.estimado_policultivo') }} @endif
                            </span>
                        </td>
                        <td data-label="{{ __('conversion.alimento') }}" class="tabular-nums">{{ $kg($fila['alimento_kg']) }} kg</td>
                        <td data-label="{{ __('conversion.ganancia') }}" class="tabular-nums">{{ $kg($fila['ganancia_kg']) }} kg</td>
                        <td data-label="{{ __('conversion.fca') }}" class="tabular-nums">{{ $fca($fila) }}
                            @if ($motivo($fila))<span class="block text-xs text-slate-500">{{ $motivo($fila) }}</span>@endif
                        </td>
                    </tr>
                @endforeach
            @endforeach
        </x-tabla>
    </section>

    <section class="mb-8">
        <h2 class="mb-3 text-lg font-semibold text-slate-900">{{ __('conversion.por_especie') }}</h2>
        <x-tabla :columnas="[__('conversion.especie'), __('conversion.siembras'), __('conversion.alimento'), __('conversion.ganancia'), __('conversion.fca')]" :vacio="__('conversion.sin_siembras')">
            @foreach ($porEspecie as $fila)
                <tr>
                    <td data-label="{{ __('conversion.especie') }}" class="font-semibold text-slate-900">{{ $fila['especie'] }}</td>
                    <td data-label="{{ __('conversion.siembras') }}" class="tabular-nums">{{ $fila['siembras'] }}</td>
                    <td data-label="{{ __('conversion.alimento') }}" class="tabular-nums">{{ $kg($fila['alimento_kg']) }} kg</td>
                    <td data-label="{{ __('conversion.ganancia') }}" class="tabular-nums">{{ $kg($fila['ganancia_kg']) }} kg</td>
                    <td data-label="{{ __('conversion.fca') }}" class="font-bold tabular-nums">{{ $fca($fila) }}
                        @if ($motivo($fila))<span class="block text-xs font-normal text-slate-500">{{ $motivo($fila) }}</span>@endif
                    </td>
                </tr>
            @endforeach
        </x-tabla>
    </section>

    @if ($lago)
        <section>
            <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
                <h2 class="text-lg font-semibold text-slate-900">{{ __('conversion.tendencia', ['semanas' => count($semanas), 'lago' => $lago->nombre]) }}</h2>
                <nav class="flex flex-wrap gap-1 rounded-xl bg-slate-200/70 p-1 text-sm font-semibold" aria-label="{{ __('conversion.elegir_lago') }}">
                    @foreach ($lagos as $opcion)
                        <a href="{{ route('admin.conversion.index', ['lago' => $opcion->id]) }}"
                           @if ($opcion->is($lago)) aria-current="page" @endif
                           @class([
                               'flex min-h-10 items-center rounded-lg px-3',
                               'bg-white text-marca-800 shadow-sm' => $opcion->is($lago),
                               'text-slate-600' => ! $opcion->is($lago),
                           ])>{{ $opcion->nombre }}</a>
                    @endforeach
                </nav>
            </div>

            @php $maximo = max(collect($semanas)->max('fca') ?? 0, 0.01); @endphp
            <x-tabla :columnas="[__('conversion.semana'), __('conversion.alimento'), __('conversion.ganancia'), __('conversion.fca')]">
                @foreach (array_reverse($semanas) as $fila)
                    <tr>
                        <td data-label="{{ __('conversion.semana') }}" class="whitespace-nowrap">{{ __('conversion.semana_del', ['fecha' => $fila['semana']->format('d/m')]) }}</td>
                        <td data-label="{{ __('conversion.alimento') }}" class="tabular-nums">{{ $kg($fila['alimento_kg']) }} kg</td>
                        <td data-label="{{ __('conversion.ganancia') }}" class="tabular-nums">{{ $fila['motivo'] === \App\Support\ConversionAlimenticia::SIN_PESO ? '—' : $kg($fila['ganancia_kg']).' kg' }}</td>
                        <td data-label="{{ __('conversion.fca') }}" class="w-1/3">
                            @if ($fila['fca'] !== null)
                                <span class="flex items-center gap-2">
                                    <span class="block h-3 rounded-r-[4px] bg-marca-500" style="width: {{ max($fila['fca'] / $maximo * 100, 2) }}%" aria-hidden="true"></span>
                                    <span class="font-semibold tabular-nums">{{ $fca($fila) }}</span>
                                </span>
                            @else
                                <span class="text-sm text-slate-500">— {{ $motivo($fila) }}</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </x-tabla>
        </section>
    @endif

    <p class="mt-6 text-sm text-slate-500">{{ __('conversion.nota') }}</p>
@endsection
