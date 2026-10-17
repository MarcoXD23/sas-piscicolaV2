@extends('layouts.app')

@php
    use App\Support\CalculoPesaje;
    use App\Support\Formato;
    use App\Support\Region;
@endphp

@section('titulo', __('cosechas.titulo_detalle', ['fecha' => $cosecha->fecha->format('d/m/Y')]))

@section('contenido')
    <x-encabezado :titulo="__('cosechas.titulo_detalle', ['fecha' => $cosecha->fecha->format('d/m/Y')])"
                  :subtitulo="__('lagos.lago_nombre', ['lago' => $cosecha->lago->nombre]).' · '.$cosecha->siembra->nombre_especie">
        <x-boton variante="secundario" :href="route('admin.lagos.show', $cosecha->lago)">{{ __('cosechas.volver_lago') }}</x-boton>
    </x-encabezado>

    {{-- La creó una jornada: no se edita ni se borra aquí, se ve en la jornada --}}
    @if ($cosecha->esDeJornada())
        <x-aviso tono="info" icono="pez" class="mb-5">
            {{ __('jornadas.cosecha_de_jornada', ['fecha' => $cosecha->fecha->format('d/m/Y')]) }}
            <a href="{{ route('admin.jornadas.show', $cosecha->jornada_id) }}" class="font-bold underline">{{ __('jornadas.ver_jornada') }}</a>
        </x-aviso>
    @endif

    {{-- Encabezado de la cosecha --}}
    <x-tarjeta>
        <dl class="grid grid-cols-1 gap-4 text-base sm:grid-cols-2 lg:grid-cols-3">
            <div><dt class="text-sm text-tinta-suave">{{ __('cosechas.lago') }}</dt><dd class="font-semibold">{{ $cosecha->lago->nombre }}</dd></div>
            <div><dt class="text-sm text-tinta-suave">{{ __('cosechas.especie') }}</dt><dd class="font-semibold">{{ $cosecha->siembra->nombre_especie }}</dd></div>
            <div><dt class="text-sm text-tinta-suave">{{ __('cosechas.destino') }}</dt><dd class="font-semibold">{{ $cosecha->textoDestino() }}</dd></div>
            <div><dt class="text-sm text-tinta-suave">{{ __('cosechas.fecha') }}</dt><dd class="font-semibold tabular-nums">{{ $cosecha->fecha->format('d/m/Y') }}</dd></div>
            <div><dt class="text-sm text-tinta-suave">{{ __('cosechas.hora') }}</dt><dd class="font-semibold tabular-nums">{{ Region::local($cosecha->created_at)?->formatoHora('H:i') }}</dd></div>
            <div><dt class="text-sm text-tinta-suave">{{ __('cosechas.despacho') }}</dt><dd class="font-semibold">{{ $cosecha->despachadoPor?->name ?? __('cosechas.sin_dato') }}</dd></div>
            <div><dt class="text-sm text-tinta-suave">{{ __('cosechas.registro') }}</dt><dd>{{ $cosecha->usuario?->name }}</dd></div>
            <div><dt class="text-sm text-tinta-suave">{{ __('cosechas.peces') }}</dt><dd class="tabular-nums">{{ Formato::numero($cosecha->peces) }}</dd></div>
            <div><dt class="text-sm text-tinta-suave">{{ __('cosechas.peso_promedio') }}</dt><dd class="tabular-nums">{{ __('cosechas.gramos', ['gramos' => Formato::numero($cosecha->peso_promedio_g)]) }}</dd></div>
            <div><dt class="text-sm font-bold text-tinta">{{ __('cosechas.kg_enteros') }}</dt><dd class="text-cifra font-bold tabular-nums text-marca-800">{{ CalculoPesaje::kg($cosecha->kg) }}</dd></div>
            @if ($cosecha->observaciones)
                <div class="sm:col-span-2 lg:col-span-3"><dt class="text-sm text-tinta-suave">{{ __('cosechas.observaciones') }}</dt><dd>{{ $cosecha->observaciones }}</dd></div>
            @endif
        </dl>
    </x-tarjeta>

    <section class="mt-8">
        <h2 class="mb-4 text-seccion font-bold">{{ __('cosechas.pesadas') }}</h2>
        @if ($pesaje)
            @include('admin.cosechas._pesadas', ['pesaje' => $pesaje])
        @else
            <x-vacio icono="pez" :mensaje="__('cosechas.sin_pesadas')" />
        @endif
    </section>
@endsection
