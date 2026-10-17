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
        <h2 class="mb-3 text-lg font-semibold text-tinta">{{ __('conversion.por_lago') }}</h2>
        <x-tabla :columnas="[__('conversion.lago_siembra'), __('conversion.alimento'), __('conversion.ganancia'), __('conversion.fca')]" :vacio="__('conversion.sin_siembras')">
            @foreach ($porLago as $item)
                <tr class="bg-papel">
                    <td data-label="{{ __('conversion.lago_siembra') }}" class="font-semibold text-tinta">{{ __('lagos.lago_nombre', ['lago' => $item['lago']->nombre]) }}</td>
                    <td data-label="{{ __('conversion.alimento') }}" class="tabular-nums">{{ $kg($item['total']['alimento_kg']) }} kg</td>
                    <td data-label="{{ __('conversion.ganancia') }}" class="tabular-nums">{{ $kg($item['total']['ganancia_kg']) }} kg</td>
                    <td data-label="{{ __('conversion.fca') }}" class="font-bold tabular-nums">{{ $fca($item['total']) }}
                        @if ($motivo($item['total']))<span class="block text-sm font-normal text-tinta-suave">{{ $motivo($item['total']) }}</span>@endif
                    </td>
                </tr>
                @foreach ($item['siembras'] as $fila)
                    <tr>
                        <td data-label="{{ __('conversion.lago_siembra') }}" class="pl-8">
                            {{ $fila['siembra']->nombre_especie }}
                            <span class="block text-sm text-tinta-suave">
                                @if ($fila['hasta'])
                                    {{ __('conversion.periodo', ['desde' => $fila['siembra']->fecha->format('d/m/Y'), 'hasta' => \Carbon\CarbonImmutable::parse($fila['hasta'])->format('d/m/Y')]) }}
                                @endif
                                @if (count($item['siembras']) > 1) · {{ __('conversion.estimado_policultivo') }} @endif
                            </span>
                        </td>
                        <td data-label="{{ __('conversion.alimento') }}" class="tabular-nums">{{ $kg($fila['alimento_kg']) }} kg</td>
                        <td data-label="{{ __('conversion.ganancia') }}" class="tabular-nums">{{ $kg($fila['ganancia_kg']) }} kg</td>
                        <td data-label="{{ __('conversion.fca') }}" class="tabular-nums">{{ $fca($fila) }}
                            @if ($motivo($fila))<span class="block text-sm text-tinta-suave">{{ $motivo($fila) }}</span>@endif
                        </td>
                    </tr>
                @endforeach
            @endforeach
        </x-tabla>
    </section>

    <section class="mb-8">
        <h2 class="mb-3 text-lg font-semibold text-tinta">{{ __('conversion.por_especie') }}</h2>
        <x-tabla :columnas="[__('conversion.especie'), __('conversion.siembras'), __('conversion.alimento'), __('conversion.ganancia'), __('conversion.fca')]" :vacio="__('conversion.sin_siembras')">
            @foreach ($porEspecie as $fila)
                <tr>
                    <td data-label="{{ __('conversion.especie') }}" class="font-semibold text-tinta">{{ $fila['especie'] }}</td>
                    <td data-label="{{ __('conversion.siembras') }}" class="tabular-nums">{{ $fila['siembras'] }}</td>
                    <td data-label="{{ __('conversion.alimento') }}" class="tabular-nums">{{ $kg($fila['alimento_kg']) }} kg</td>
                    <td data-label="{{ __('conversion.ganancia') }}" class="tabular-nums">{{ $kg($fila['ganancia_kg']) }} kg</td>
                    <td data-label="{{ __('conversion.fca') }}" class="font-bold tabular-nums">{{ $fca($fila) }}
                        @if ($motivo($fila))<span class="block text-sm font-normal text-tinta-suave">{{ $motivo($fila) }}</span>@endif
                    </td>
                </tr>
            @endforeach
        </x-tabla>
    </section>

    {{-- Comparación de proveedores de alevinos (F5) --}}
    <section class="mb-8" data-por-proveedor>
        <h2 class="mb-1 text-lg font-semibold text-tinta">{{ __('conversion.por_proveedor') }}</h2>
        <p class="mb-3 text-sm text-tinta-suave">{{ __('conversion.por_proveedor_ayuda') }}</p>
        <x-tabla :columnas="[__('conversion.proveedor'), __('conversion.alevinos'), __('conversion.crecimiento'), __('conversion.supervivencia'), __('conversion.fca')]" :vacio="__('conversion.sin_siembras')">
            @foreach ($porProveedor as $fila)
                <tr>
                    <td data-label="{{ __('conversion.proveedor') }}" class="font-semibold text-tinta">
                        @if ($fila['clave'] === \App\Support\ComparacionProveedores::SIN_LIGAR)
                            {{ __('conversion.proveedor_sin_ligar') }}
                            <a href="{{ route('admin.proveedores.revision') }}" class="block text-sm font-normal text-marca-700 underline underline-offset-4">{{ __('proveedores.revision.enlace') }}</a>
                        @elseif ($fila['clave'] === \App\Support\ComparacionProveedores::SIN_PROVEEDOR)
                            {{ __('conversion.proveedor_ninguno') }}
                        @else
                            {{ $fila['nombre'] }}
                        @endif
                        <span class="block text-sm font-normal text-tinta-suave">{{ trans_choice('conversion.lotes', $fila['lotes'], ['lotes' => $fila['lotes']]) }}@if ($fila['mezcla']) · {{ __('conversion.mezcla') }}@endif</span>
                    </td>
                    <td data-label="{{ __('conversion.alevinos') }}" class="tabular-nums">{{ \App\Support\Formato::numero($fila['alevinos']) }}</td>
                    <td data-label="{{ __('conversion.crecimiento') }}" class="tabular-nums">{{ $fila['crecimiento_g_dia'] === null ? '—' : __('conversion.gramos_dia', ['valor' => \App\Support\Formato::numero($fila['crecimiento_g_dia'], 2)]) }}</td>
                    <td data-label="{{ __('conversion.supervivencia') }}" class="tabular-nums">{{ $fila['supervivencia'] === null ? '—' : __('conversion.porcentaje', ['valor' => \App\Support\Formato::numero($fila['supervivencia'], 1)]) }}</td>
                    <td data-label="{{ __('conversion.fca') }}" class="font-bold tabular-nums">{{ $fca($fila) }}
                        @if ($motivo($fila))<span class="block text-sm font-normal text-tinta-suave">{{ $motivo($fila) }}</span>@endif
                    </td>
                </tr>
            @endforeach
        </x-tabla>
    </section>

    @if ($lago)
        <section>
            <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
                <h2 class="text-lg font-semibold text-tinta">{{ __('conversion.tendencia', ['semanas' => count($semanas), 'lago' => $lago->nombre]) }}</h2>
                <nav class="flex flex-wrap gap-x-1 border-b border-renglon text-base" aria-label="{{ __('conversion.elegir_lago') }}">
                    @foreach ($lagos as $opcion)
                        <a href="{{ route('admin.conversion.index', ['lago' => $opcion->id]) }}"
                           @if ($opcion->is($lago)) aria-current="page" @endif
                           @class([
                               '-mb-px flex min-h-12 items-center px-3 transition-colors',
                               'font-bold text-tinta shadow-[inset_0_-3px_0_var(--color-marca-700)]' => $opcion->is($lago),
                               'text-tinta-suave hover:bg-papel-hondo hover:text-tinta' => ! $opcion->is($lago),
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
                                    {{-- SVG con el ancho como atributo (la Content-Security-Policy no permite style en el HTML) --}}
                                    <svg class="block h-3 flex-1" aria-hidden="true"><rect height="100%" rx="3" width="{{ round(max($fila['fca'] / $maximo * 100, 2), 2) }}%" class="fill-marca-500" /></svg>
                                    <span class="font-semibold tabular-nums">{{ $fca($fila) }}</span>
                                </span>
                            @else
                                <span class="text-sm text-tinta-suave">— {{ $motivo($fila) }}</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </x-tabla>
        </section>
    @endif

    <p class="mt-6 text-sm text-tinta-suave">{{ __('conversion.nota') }}</p>
@endsection
