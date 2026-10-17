@extends('layouts.app')

@section('titulo', __('proveedores.editar'))

@section('contenido')

    <x-encabezado :titulo="$proveedor->nombreCompleto()" :subtitulo="__('proveedores.editar')">
        <x-boton variante="secundario" :href="route('admin.proveedores.index')">{{ __('proveedores.volver') }}</x-boton>
    </x-encabezado>

    <div class="max-w-2xl">
        <x-tarjeta :titulo="__('proveedores.editar')">
            @if ($proveedor->es_jornalero)
                <x-aviso tono="info" class="mb-4">{{ __('proveedores.tambien_jornalero') }}</x-aviso>
            @endif
            <x-formulario :action="route('admin.proveedores.update', $proveedor)" metodo="PUT">
                @unless ($proveedor->es_jornalero)
                    <x-campo nombre="nombre" :etiqueta="__('proveedores.campos.nombre')" :valor="$proveedor->nombres" required maxlength="120" autocomplete="off" />
                @endunless

                {{-- El documento nunca se devuelve en el campo: el administrador ve solo 4 dígitos --}}
                <div>
                    @if ($proveedor->documento !== null)
                        <p class="mb-2 text-base text-tinta-suave">{{ __('proveedores.documento_actual', ['documento' => $proveedor->documentoEnmascarado()]) }}</p>
                    @endif
                    <x-campo nombre="documento" :etiqueta="__('proveedores.campos.documento')" :ayuda="__('proveedores.documento_ayuda_editar')" maxlength="30" autocomplete="off" />
                    @if ($proveedor->documento !== null)
                        <label class="mt-2 flex min-h-11 items-center gap-3 text-base text-tinta">
                            <input type="checkbox" name="quitar_documento" value="1" class="h-5 w-5 accent-marca-700"> {{ __('proveedores.quitar_documento') }}
                        </label>
                    @endif
                </div>

                <x-campo nombre="telefono" :etiqueta="__('proveedores.campos.telefono')" tipo="tel" :valor="$proveedor->telefono" maxlength="30" autocomplete="off" />
                @include('admin.proveedores.partials.provee', ['marcados' => (array) $proveedor->provee])
                <x-boton>{{ __('proveedores.guardar') }}</x-boton>
            </x-formulario>
        </x-tarjeta>
    </div>
@endsection
