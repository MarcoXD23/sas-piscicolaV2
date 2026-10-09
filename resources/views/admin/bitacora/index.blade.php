@extends('layouts.app')

@section('titulo', __('bitacora.titulo'))

@section('contenido')
    <x-encabezado :titulo="__('bitacora.titulo')" :subtitulo="__('bitacora.subtitulo')" />

    <x-tarjeta class="mb-6">
        <x-formulario :action="route('admin.bitacora.index')" metodo="GET" class="!space-y-0">
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
                <x-seleccion nombre="user_id" :etiqueta="__('bitacora.persona')" :opciones="['' => __('bitacora.todas')] + $personas" :valor="$filtros['user_id'] ?? null" />
                <x-seleccion nombre="rol" :etiqueta="__('bitacora.rol')" :opciones="['' => __('bitacora.todos')] + $roles" :valor="$filtros['rol'] ?? null" />
                <x-seleccion nombre="lago_id" :etiqueta="__('bitacora.lago')" :opciones="['' => __('bitacora.todos')] + $lagos" :valor="$filtros['lago_id'] ?? null" />
                <x-campo nombre="desde" :etiqueta="__('comun.desde')" tipo="date" :valor="$filtros['desde'] ?? null" />
                <x-campo nombre="hasta" :etiqueta="__('comun.hasta')" tipo="date" :valor="$filtros['hasta'] ?? null" />
            </div>
            <div class="mt-4 flex flex-wrap gap-3">
                <x-boton>{{ __('bitacora.filtrar') }}</x-boton>
                @if (array_filter($filtros))
                    <x-boton variante="secundario" :href="route('admin.bitacora.index')">{{ __('bitacora.limpiar') }}</x-boton>
                @endif
            </div>
        </x-formulario>
    </x-tarjeta>

    <x-tabla :columnas="[__('bitacora.momento'), __('bitacora.persona'), __('bitacora.accion'), __('bitacora.lago'), __('bitacora.detalle')]" :vacio="__('bitacora.vacio')">
        @foreach ($registros as $registro)
            <tr>
                <td data-label="{{ __('bitacora.momento') }}" class="whitespace-nowrap tabular-nums">
                    <time datetime="{{ $registro->momentoLocal()->toIso8601String() }}">{{ $registro->momentoLocal()->format('d/m/Y H:i:s') }}</time>
                </td>
                <td data-label="{{ __('bitacora.persona') }}">
                    <p class="font-medium text-slate-900">{{ $registro->usuario->name }}</p>
                    <p class="text-sm text-texto-suave">{{ __('bitacora.roles.'.$registro->rol) }}</p>
                </td>
                <td data-label="{{ __('bitacora.accion') }}">
                    <span @class([
                        'inline-block rounded-full px-2.5 py-0.5 text-sm font-semibold',
                        'bg-amber-100 text-amber-900' => in_array($registro->accion, ['mortalidad', 'novedad'], true),
                        'bg-marca-100 text-marca-800' => ! in_array($registro->accion, ['mortalidad', 'novedad'], true),
                    ])>{{ __('bitacora.acciones.'.$registro->accion) }}</span>
                </td>
                <td data-label="{{ __('bitacora.lago') }}">{{ $registro->detalle['lago'] ?? '—' }}</td>
                <td data-label="{{ __('bitacora.detalle') }}" class="text-slate-700">{{ $registro->textoDetalle() }}</td>
            </tr>
        @endforeach
    </x-tabla>

    @if ($registros->hasPages())
        <nav class="mt-4 flex items-center justify-between gap-3" aria-label="{{ __('comun.paginas') }}">
            @if ($registros->onFirstPage())
                <span></span>
            @else
                <x-boton variante="secundario" :href="$registros->previousPageUrl()">{{ __('comun.anteriores') }}</x-boton>
            @endif
            <span class="text-sm text-texto-suave">{{ __('comun.pagina_de', ['actual' => $registros->currentPage(), 'total' => $registros->lastPage()]) }}</span>
            @if ($registros->hasMorePages())
                <x-boton variante="secundario" :href="$registros->nextPageUrl()">{{ __('comun.siguientes') }}</x-boton>
            @else
                <span></span>
            @endif
        </nav>
    @endif

    <p class="mt-6 text-sm text-texto-suave">{{ __('bitacora.nota') }}</p>
@endsection
