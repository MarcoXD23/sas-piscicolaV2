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

    {{-- Encabezado de la cosecha --}}
    <x-tarjeta>
        <dl class="grid grid-cols-1 gap-4 text-base sm:grid-cols-2 lg:grid-cols-3">
            <div><dt class="text-sm text-slate-500">{{ __('cosechas.lago') }}</dt><dd class="font-semibold">{{ $cosecha->lago->nombre }}</dd></div>
            <div><dt class="text-sm text-slate-500">{{ __('cosechas.especie') }}</dt><dd class="font-semibold">{{ $cosecha->siembra->nombre_especie }}</dd></div>
            <div><dt class="text-sm text-slate-500">{{ __('cosechas.destino') }}</dt><dd class="font-semibold">{{ $cosecha->destino ?: __('cosechas.sin_dato') }}</dd></div>
            <div><dt class="text-sm text-slate-500">{{ __('cosechas.fecha') }}</dt><dd class="font-semibold tabular-nums">{{ $cosecha->fecha->format('d/m/Y') }}</dd></div>
            <div><dt class="text-sm text-slate-500">{{ __('cosechas.hora') }}</dt><dd class="font-semibold tabular-nums">{{ Region::local($cosecha->created_at)?->format('H:i') }}</dd></div>
            <div><dt class="text-sm text-slate-500">{{ __('cosechas.despacho') }}</dt><dd class="font-semibold">{{ $cosecha->despachadoPor?->name ?? __('cosechas.sin_dato') }}</dd></div>
            <div><dt class="text-sm text-slate-500">{{ __('cosechas.registro') }}</dt><dd>{{ $cosecha->usuario?->name }}</dd></div>
            <div><dt class="text-sm text-slate-500">{{ __('cosechas.peces') }}</dt><dd class="tabular-nums">{{ Formato::numero($cosecha->peces) }}</dd></div>
            <div><dt class="text-sm text-slate-500">{{ __('cosechas.peso_promedio') }}</dt><dd class="tabular-nums">{{ __('cosechas.gramos', ['gramos' => Formato::numero($cosecha->peso_promedio_g)]) }}</dd></div>
            <div><dt class="text-sm font-semibold text-marca-900">{{ __('cosechas.kg_limpios') }}</dt><dd class="text-xl font-bold tabular-nums text-marca-900">{{ CalculoPesaje::kg($cosecha->kg) }}</dd></div>
            @if ($cosecha->observaciones)
                <div class="sm:col-span-2 lg:col-span-3"><dt class="text-sm text-slate-500">{{ __('cosechas.observaciones') }}</dt><dd>{{ $cosecha->observaciones }}</dd></div>
            @endif
        </dl>
    </x-tarjeta>

    <section class="mt-6">
        <h2 class="mb-3 text-lg font-semibold text-slate-900">{{ __('cosechas.pesadas') }}</h2>
        @if ($pesaje)
            @include('admin.cosechas._pesadas', ['pesaje' => $pesaje])
        @else
            <x-vacio icono="pez" :mensaje="__('cosechas.sin_pesadas')" />
        @endif
    </section>
@endsection
