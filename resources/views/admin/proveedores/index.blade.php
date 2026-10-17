@extends('layouts.app')

@section('titulo', __('proveedores.titulo'))

@section('contenido')

    <x-encabezado :titulo="__('proveedores.titulo')" :subtitulo="__('proveedores.subtitulo')">
        @if ($puedeEditar)
            <x-boton tipo="button" data-abrir-modal="modal-proveedor">
                <x-icono nombre="mas" class="h-5 w-5" /> {{ __('proveedores.nuevo') }}
            </x-boton>
        @endif
    </x-encabezado>

    @unless ($puedeEditar)
        <x-aviso tono="info" class="mb-6">{{ __('proveedores.solo_lectura') }}</x-aviso>
    @endunless

    {{-- Siembras viejas con el proveedor escrito a mano: se ligan en la pantalla de revisión, sin adivinar --}}
    @if ($sinLigar > 0)
        <x-aviso tono="atencion" class="mb-6">
            {{ trans_choice('proveedores.revision.pendientes', $sinLigar, ['cantidad' => $sinLigar]) }}
            <x-slot:accion>
                <x-boton variante="secundario" :href="route('admin.proveedores.revision')">{{ __('proveedores.revision.enlace') }}</x-boton>
            </x-slot:accion>
        </x-aviso>
    @endif

    <x-formulario :action="route('admin.proveedores.index')" metodo="GET" class="mb-6 grid gap-3 space-y-0 sm:grid-cols-[1fr_auto_auto_auto] sm:items-end">
        <x-campo nombre="buscar" :etiqueta="__('proveedores.buscar')" :valor="$buscar" autocomplete="off" />
        <x-seleccion nombre="provee" :etiqueta="__('proveedores.columnas.provee')" :valor="$provee" :opciones="['' => __('proveedores.todo_lo_que_provee')] + $opcionesProvee" />
        <x-seleccion nombre="estado" :etiqueta="__('proveedores.columnas.estado')" :valor="$estado" :opciones="['activos' => __('proveedores.activos'), 'todos' => __('proveedores.todos')]" />
        <x-boton variante="secundario">{{ __('proveedores.buscar_boton') }}</x-boton>
    </x-formulario>

    <x-tabla :columnas="[__('proveedores.columnas.nombre'), __('proveedores.columnas.provee'), __('proveedores.columnas.documento'), __('proveedores.columnas.telefono'), __('proveedores.columnas.estado')]"
             :vacio="$buscar !== '' || $provee !== null ? __('proveedores.sin_resultados') : __('proveedores.vacio')">
        @foreach ($proveedores as $proveedor)
            <tr @class(['opacity-60' => ! $proveedor->activo])>
                <td data-label="{{ __('proveedores.columnas.nombre') }}" class="font-medium">
                    @if ($puedeEditar)
                        <a href="{{ route('admin.proveedores.edit', $proveedor) }}" class="font-semibold text-marca-700 underline-offset-4 hover:underline">{{ $proveedor->nombreCompleto() }}</a>
                    @else
                        {{ $proveedor->nombreCompleto() }}
                    @endif
                </td>
                <td data-label="{{ __('proveedores.columnas.provee') }}">{{ $proveedor->textoProvee() ?? __('proveedores.sin_dato') }}</td>
                {{-- El documento completo solo lo ve el propietario --}}
                <td data-label="{{ __('proveedores.columnas.documento') }}" class="tabular-nums">{{ ($veDocumento ? $proveedor->documento : $proveedor->documentoEnmascarado()) ?? __('proveedores.sin_dato') }}</td>
                <td data-label="{{ __('proveedores.columnas.telefono') }}" class="tabular-nums">{{ $proveedor->telefono ?? __('proveedores.sin_dato') }}</td>
                <td data-label="{{ __('proveedores.columnas.estado') }}">
                    @if ($puedeEditar)
                        <form action="{{ route('admin.proveedores.activo', $proveedor) }}" method="POST" class="inline">
                            @csrf
                            @unless ($proveedor->activo) <x-insignia class="mr-2">{{ __('proveedores.inactivo') }}</x-insignia> @endunless
                            <button type="submit" class="min-h-11 text-base font-semibold text-marca-700 underline underline-offset-4">{{ $proveedor->activo ? __('proveedores.desactivar') : __('proveedores.activar') }}</button>
                        </form>
                    @elseif (! $proveedor->activo)
                        <x-insignia>{{ __('proveedores.inactivo') }}</x-insignia>
                    @endif
                </td>
            </tr>
        @endforeach
    </x-tabla>

    @if ($proveedores->hasPages())
        <nav class="mt-4 flex items-center justify-between gap-3" aria-label="{{ __('comun.paginas') }}">
            @if ($proveedores->onFirstPage()) <span></span> @else <x-boton variante="secundario" :href="$proveedores->previousPageUrl()">{{ __('comun.anteriores') }}</x-boton> @endif
            <span class="text-sm text-tinta-suave">{{ __('comun.pagina_de', ['actual' => $proveedores->currentPage(), 'total' => $proveedores->lastPage()]) }}</span>
            @if ($proveedores->hasMorePages()) <x-boton variante="secundario" :href="$proveedores->nextPageUrl()">{{ __('comun.siguientes') }}</x-boton> @else <span></span> @endif
        </nav>
    @endif

    @if ($puedeEditar)
        <x-modal id="modal-proveedor" :titulo="__('proveedores.nuevo')">
            <x-formulario :action="route('admin.proveedores.store')">
                <x-campo nombre="nombre" :etiqueta="__('proveedores.campos.nombre')" required maxlength="120" autocomplete="off" />
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-campo nombre="documento" :etiqueta="__('proveedores.campos.documento')" :ayuda="__('proveedores.documento_ayuda')" maxlength="30" autocomplete="off" />
                    <x-campo nombre="telefono" :etiqueta="__('proveedores.campos.telefono')" tipo="tel" maxlength="30" autocomplete="off" />
                </div>
                @include('admin.proveedores.partials.provee', ['marcados' => []])
                <div class="grid grid-cols-2 gap-3 pt-2">
                    <x-boton tipo="button" variante="secundario" data-cerrar-modal>{{ __('comun.cancelar') }}</x-boton>
                    <x-boton>{{ __('proveedores.agregar') }}</x-boton>
                </div>
            </x-formulario>
        </x-modal>
    @endif
@endsection
