@extends('layouts.app')

@section('titulo', __('alertas.titulo'))

@section('contenido')
    <x-encabezado :titulo="__('alertas.titulo')" :subtitulo="__('alertas.subtitulo')" />

    <nav class="mb-4 flex flex-wrap gap-1 rounded-xl bg-slate-200/70 p-1 text-sm font-semibold" aria-label="{{ __('alertas.filtrar') }}">
        @foreach ([null => __('alertas.todas')] + collect($tipos)->mapWithKeys(fn ($tipo) => [$tipo => __('alertas.tipos.'.$tipo)])->all() as $valor => $texto)
            <a href="{{ route('admin.alertas.index', array_filter(['tipo' => $valor ?: null])) }}"
               @if ($filtro === ($valor ?: null)) aria-current="page" @endif
               @class([
                   'flex min-h-10 items-center rounded-lg px-3',
                   'bg-white text-marca-800 shadow-sm' => $filtro === ($valor ?: null),
                   'text-slate-600' => $filtro !== ($valor ?: null),
               ])>{{ $texto }}</a>
        @endforeach
    </nav>

    <x-tabla :columnas="[__('alertas.alerta'), __('alertas.estado'), __('alertas.abierta'), __('alertas.cerrada')]" :vacio="__('alertas.vacio')">
        @foreach ($alertas as $alerta)
            <tr>
                <td data-label="{{ __('alertas.alerta') }}">
                    <span @class([
                        'mb-1 inline-block rounded-full px-2.5 py-0.5 text-xs font-semibold',
                        'bg-red-100 text-red-800' => $alerta->esCritica(),
                        'bg-amber-100 text-amber-900' => ! $alerta->esCritica(),
                    ])>{{ __('alertas.tipos.'.$alerta->tipo) }} · {{ __('alertas.severidades.'.$alerta->severidad) }}</span>
                    <p class="text-slate-800">{{ $alerta->mensaje() }}</p>
                    @if ($alerta->notificada_at)
                        <p class="text-sm text-slate-500">{{ __('alertas.notificada', ['momento' => $alerta->notificada_at->format('d/m/Y H:i')]) }}</p>
                    @endif
                </td>
                <td data-label="{{ __('alertas.estado') }}">
                    <span @class([
                        'inline-block rounded-full px-3 py-1 text-sm font-semibold',
                        'bg-red-50 text-red-800' => $alerta->estaAbierta(),
                        'bg-slate-100 text-slate-600' => ! $alerta->estaAbierta(),
                    ])>{{ $alerta->estaAbierta() ? __('alertas.abierta_estado') : __('alertas.cerrada_estado') }}</span>
                </td>
                <td data-label="{{ __('alertas.abierta') }}" class="whitespace-nowrap tabular-nums">{{ $alerta->abierta_at->format('d/m/Y H:i') }}</td>
                <td data-label="{{ __('alertas.cerrada') }}" class="whitespace-nowrap tabular-nums">{{ $alerta->cerrada_at?->format('d/m/Y H:i') ?? '—' }}</td>
            </tr>
        @endforeach
    </x-tabla>

    @if ($alertas->hasPages())
        <nav class="mt-4 flex items-center justify-between gap-3" aria-label="{{ __('comun.paginas') }}">
            @if ($alertas->onFirstPage())
                <span></span>
            @else
                <x-boton variante="secundario" :href="$alertas->previousPageUrl()">{{ __('comun.anteriores') }}</x-boton>
            @endif
            <span class="text-sm text-slate-500">{{ __('comun.pagina_de', ['actual' => $alertas->currentPage(), 'total' => $alertas->lastPage()]) }}</span>
            @if ($alertas->hasMorePages())
                <x-boton variante="secundario" :href="$alertas->nextPageUrl()">{{ __('comun.siguientes') }}</x-boton>
            @else
                <span></span>
            @endif
        </nav>
    @endif
@endsection
