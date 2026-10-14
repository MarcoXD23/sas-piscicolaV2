@extends('layouts.app')

@section('titulo', __('alertas.titulo'))

@section('contenido')
    <x-encabezado :titulo="__('alertas.titulo')" :subtitulo="__('alertas.subtitulo')" />

    <nav class="mb-4 flex flex-wrap gap-x-1 border-b border-renglon text-base" aria-label="{{ __('alertas.filtrar') }}">
        @foreach ([null => __('alertas.todas')] + collect($tipos)->mapWithKeys(fn ($tipo) => [$tipo => __('alertas.tipos.'.$tipo)])->all() as $valor => $texto)
            <a href="{{ route('admin.alertas.index', array_filter(['tipo' => $valor ?: null])) }}"
               @if ($filtro === ($valor ?: null)) aria-current="page" @endif
               @class([
                   '-mb-px flex min-h-12 items-center px-3 transition-colors',
                   'font-bold text-tinta shadow-[inset_0_-3px_0_var(--color-marca-700)]' => $filtro === ($valor ?: null),
                   'text-tinta-suave hover:bg-papel-hondo hover:text-tinta' => $filtro !== ($valor ?: null),
               ])>{{ $texto }}</a>
        @endforeach
    </nav>

    <x-tabla :columnas="[__('alertas.alerta'), __('alertas.estado'), __('alertas.abierta'), __('alertas.cerrada')]" :vacio="__('alertas.vacio')">
        @foreach ($alertas as $alerta)
            <tr>
                <td data-label="{{ __('alertas.alerta') }}">
                    <span @class([
                        'mb-1 inline-block rounded-full px-2.5 py-0.5 text-sm font-semibold',
                        'bg-alerta-fondo text-alerta' => $alerta->esCritica(),
                        'bg-atencion-fondo text-atencion' => ! $alerta->esCritica(),
                    ])>{{ __('alertas.tipos.'.$alerta->tipo) }} · {{ __('alertas.severidades.'.$alerta->severidad) }}</span>
                    <p class="text-tinta">{{ $alerta->mensaje() }}</p>
                    @if ($alerta->notificada_at)
                        <p class="text-sm text-tinta-suave">{{ __('alertas.notificada', ['momento' => $alerta->notificada_at->formatoHora('d/m/Y H:i')]) }}</p>
                    @endif
                </td>
                <td data-label="{{ __('alertas.estado') }}">
                    <span @class([
                        'inline-block rounded-full px-3 py-1 text-sm font-semibold',
                        'bg-alerta-fondo text-alerta' => $alerta->estaAbierta(),
                        'bg-papel-hondo text-tinta-suave' => ! $alerta->estaAbierta(),
                    ])>{{ $alerta->estaAbierta() ? __('alertas.abierta_estado') : __('alertas.cerrada_estado') }}</span>
                </td>
                <td data-label="{{ __('alertas.abierta') }}" class="whitespace-nowrap tabular-nums">{{ $alerta->abierta_at->formatoHora('d/m/Y H:i') }}</td>
                <td data-label="{{ __('alertas.cerrada') }}" class="whitespace-nowrap tabular-nums">{{ $alerta->cerrada_at?->formatoHora('d/m/Y H:i') ?? '—' }}</td>
            </tr>
        @endforeach
    </x-tabla>

    @if ($alertas->hasPages())
        <nav class="mt-4 flex items-center justify-between gap-3" aria-label="{{ __('comun.paginas') }}">
            @if ($alertas->onFirstPage())
                <span></span>
            @else
                <x-boton variante="secundario" :href="$alertas->previousPageUrl()"><x-icono nombre="abajo" class="h-5 w-5 rotate-90" /> {{ __('comun.anteriores') }}</x-boton>
            @endif
            <span class="text-sm text-tinta-suave">{{ __('comun.pagina_de', ['actual' => $alertas->currentPage(), 'total' => $alertas->lastPage()]) }}</span>
            @if ($alertas->hasMorePages())
                <x-boton variante="secundario" :href="$alertas->nextPageUrl()">{{ __('comun.siguientes') }} <x-icono nombre="abajo" class="h-5 w-5 -rotate-90" /></x-boton>
            @else
                <span></span>
            @endif
        </nav>
    @endif
@endsection
