@extends('layouts.app')

@section('titulo', __('proveedores.revision.titulo'))

@section('contenido')

    <x-encabezado :titulo="__('proveedores.revision.titulo')" :subtitulo="__('proveedores.revision.subtitulo')">
        <x-boton variante="secundario" :href="route('admin.proveedores.index')">{{ __('proveedores.revision.volver') }}</x-boton>
    </x-encabezado>

    @unless ($puedeEditar)
        <x-aviso tono="info" class="mb-6">{{ __('proveedores.revision.solo_lectura') }}</x-aviso>
    @endunless
    @if ($puedeEditar && $textos->isNotEmpty() && $proveedores === [])
        <x-aviso tono="atencion" class="mb-6">{{ __('proveedores.revision.sin_proveedores') }}</x-aviso>
    @endif
    @error('texto')
        <x-aviso tono="alerta" class="mb-6">{{ $message }}</x-aviso>
    @enderror

    {{-- Cada texto escrito a mano se liga a un proveedor que elige el administrador: nada viene preseleccionado --}}
    <x-tabla :columnas="$puedeEditar ? [__('proveedores.revision.texto'), __('proveedores.revision.proveedor')] : [__('proveedores.revision.texto')]"
             :vacio="__('proveedores.revision.vacio')">
        @foreach ($textos as $fila)
            <tr>
                <td data-label="{{ __('proveedores.revision.texto') }}">
                    <span class="font-semibold">«{{ $fila->texto }}»</span>
                    <span class="block text-sm text-tinta-suave">{{ trans_choice('proveedores.revision.siembras', $fila->siembras, ['cantidad' => $fila->siembras]) }}</span>
                </td>
                @if ($puedeEditar)
                    <td data-label="{{ __('proveedores.revision.proveedor') }}">
                        <form action="{{ route('admin.proveedores.ligar') }}" method="POST" class="flex flex-wrap items-end gap-2">
                            @csrf
                            <input type="hidden" name="texto" value="{{ $fila->texto }}">
                            @if ($proveedores !== [])
                                <div class="min-w-48 flex-1">
                                    <x-seleccion nombre="proveedor_id" id="ligar-{{ $loop->index }}" :etiqueta="__('proveedores.revision.proveedor')"
                                                 :opciones="['' => __('proveedores.revision.elige')] + $proveedores" />
                                </div>
                                <x-boton>{{ __('proveedores.revision.ligar') }}</x-boton>
                            @endif
                            <x-boton variante="secundario" name="crear" value="1">{{ __('proveedores.revision.crear') }}</x-boton>
                        </form>
                    </td>
                @endif
            </tr>
        @endforeach
    </x-tabla>
@endsection
