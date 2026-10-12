@extends('layouts.app')

@section('titulo', __('jornaleros.titulo'))

@section('contenido')

    <x-encabezado :titulo="__('jornaleros.titulo')" :subtitulo="__('jornaleros.subtitulo')">
        <x-boton tipo="button" data-abrir-modal="modal-jornalero">
            <x-icono nombre="mas" class="h-5 w-5" /> {{ __('jornaleros.nuevo') }}
        </x-boton>
    </x-encabezado>

    <x-formulario :action="route('admin.jornaleros.index')" metodo="GET" class="mb-6 grid gap-3 space-y-0 sm:grid-cols-[1fr_auto_auto] sm:items-end">
        <x-campo nombre="buscar" :etiqueta="__('jornaleros.buscar')" :valor="$buscar" autocomplete="off" />
        <x-seleccion nombre="estado" :etiqueta="__('jornaleros.columnas.estado')" :valor="$estado" :opciones="['activos' => __('jornaleros.activos'), 'todos' => __('jornaleros.todos')]" />
        <x-boton variante="secundario">{{ __('jornaleros.buscar_boton') }}</x-boton>
    </x-formulario>

    <x-tabla :columnas="[__('jornaleros.columnas.nombre'), __('jornaleros.columnas.documento'), __('jornaleros.columnas.telefono'), __('jornaleros.columnas.categoria'), __('jornaleros.columnas.estado')]"
             :vacio="$buscar !== '' ? __('jornaleros.sin_resultados') : __('jornaleros.vacio')">
        @foreach ($jornaleros as $jornalero)
            <tr>
                <td data-label="{{ __('jornaleros.columnas.nombre') }}" class="font-medium">
                    @if ($jornalero->anonimizado())
                        {{ $jornalero->nombreCompleto() }}
                    @else
                        <a href="{{ route('admin.jornaleros.edit', $jornalero) }}" class="font-semibold text-marca-700 underline-offset-4 hover:underline">{{ $jornalero->nombreCompleto() }}</a>
                    @endif
                </td>
                {{-- El documento completo solo lo ve el propietario --}}
                <td data-label="{{ __('jornaleros.columnas.documento') }}" class="tabular-nums">{{ ($veDocumento ? $jornalero->documento : $jornalero->documentoEnmascarado()) ?? __('jornaleros.sin_dato') }}</td>
                <td data-label="{{ __('jornaleros.columnas.telefono') }}" class="tabular-nums">{{ $jornalero->telefono ?? __('jornaleros.sin_dato') }}</td>
                <td data-label="{{ __('jornaleros.columnas.categoria') }}">{{ $jornalero->categoria_habitual ? $categorias[$jornalero->categoria_habitual] : __('jornaleros.sin_dato') }}</td>
                <td data-label="{{ __('jornaleros.columnas.estado') }}">
                    @if ($jornalero->anonimizado())
                        <x-insignia>{{ __('jornaleros.anonimizado') }}</x-insignia>
                    @else
                        <form action="{{ route('admin.jornaleros.activo', $jornalero) }}" method="POST" class="inline">
                            @csrf
                            @unless ($jornalero->activo) <x-insignia class="mr-2">{{ __('jornaleros.inactivo') }}</x-insignia> @endunless
                            <button type="submit" class="min-h-11 text-base font-semibold text-marca-700 underline underline-offset-4">{{ $jornalero->activo ? __('jornaleros.desactivar') : __('jornaleros.activar') }}</button>
                        </form>
                    @endif
                </td>
            </tr>
        @endforeach
    </x-tabla>

    @if ($jornaleros->hasPages())
        <nav class="mt-4 flex items-center justify-between gap-3" aria-label="{{ __('comun.paginas') }}">
            @if ($jornaleros->onFirstPage()) <span></span> @else <x-boton variante="secundario" :href="$jornaleros->previousPageUrl()">{{ __('comun.anteriores') }}</x-boton> @endif
            <span class="text-sm text-tinta-suave">{{ __('comun.pagina_de', ['actual' => $jornaleros->currentPage(), 'total' => $jornaleros->lastPage()]) }}</span>
            @if ($jornaleros->hasMorePages()) <x-boton variante="secundario" :href="$jornaleros->nextPageUrl()">{{ __('comun.siguientes') }}</x-boton> @else <span></span> @endif
        </nav>
    @endif

    <x-modal id="modal-jornalero" :titulo="__('jornaleros.nuevo')">
        <x-formulario :action="route('admin.jornaleros.store')">
            <div class="grid gap-4 sm:grid-cols-2">
                <x-campo nombre="nombres" :etiqueta="__('jornaleros.campos.nombres')" required maxlength="120" autocomplete="off" />
                <x-campo nombre="apellidos" :etiqueta="__('jornaleros.campos.apellidos')" maxlength="120" autocomplete="off" />
                <x-campo nombre="documento" :etiqueta="__('jornaleros.campos.documento')" :ayuda="__('jornaleros.documento_ayuda')" maxlength="30" inputmode="numeric" autocomplete="off" />
                <x-campo nombre="telefono" :etiqueta="__('jornaleros.campos.telefono')" tipo="tel" maxlength="30" autocomplete="off" />
            </div>
            <x-seleccion nombre="categoria_habitual" :etiqueta="__('jornaleros.campos.categoria_habitual')" :opciones="['' => __('jornaleros.elige_categoria')] + $categorias" />
            <div class="grid grid-cols-2 gap-3 pt-2">
                <x-boton tipo="button" variante="secundario" data-cerrar-modal>{{ __('comun.cancelar') }}</x-boton>
                <x-boton>{{ __('jornaleros.agregar') }}</x-boton>
            </div>
        </x-formulario>
    </x-modal>
@endsection
