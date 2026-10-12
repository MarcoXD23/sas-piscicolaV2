@extends('layouts.app')
@use('App\Support\Formato')

@section('titulo', __('jornaleros.editar'))

@section('contenido')

    <x-encabezado :titulo="$jornalero->nombreCompleto()" :subtitulo="__('jornaleros.editar')">
        <x-boton variante="secundario" :href="route('admin.jornaleros.index')">{{ __('jornaleros.volver') }}</x-boton>
    </x-encabezado>

    <div class="grid gap-6 lg:grid-cols-2">
        <x-tarjeta :titulo="__('jornaleros.editar')">
            @if ($saldo > 0)
                <p class="mb-4 rounded-ficha border border-atencion-borde bg-atencion-fondo px-4 py-3 text-base text-atencion">{{ __('jornaleros.saldo_por_cobrar', ['saldo' => Formato::dinero($saldo)]) }}</p>
            @endif
            <x-formulario :action="route('admin.jornaleros.update', $jornalero)" metodo="PUT">
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-campo nombre="nombres" :etiqueta="__('jornaleros.campos.nombres')" :valor="$jornalero->nombres" required maxlength="120" autocomplete="off" />
                    <x-campo nombre="apellidos" :etiqueta="__('jornaleros.campos.apellidos')" :valor="$jornalero->apellidos" maxlength="120" autocomplete="off" />
                </div>

                {{-- El documento nunca se devuelve en el campo: el propietario lo ve aquí; el administrador, solo 4 dígitos --}}
                <div>
                    @if ($jornalero->documento !== null)
                        <p class="mb-2 text-base text-tinta-suave">{{ __('jornaleros.documento_actual', ['documento' => $veDocumento ? $jornalero->documento : $jornalero->documentoEnmascarado()]) }}</p>
                    @endif
                    <x-campo nombre="documento" :etiqueta="__('jornaleros.campos.documento')" :ayuda="$veDocumento ? __('jornaleros.documento_ayuda') : __('jornaleros.documento_ayuda_admin')" maxlength="30" inputmode="numeric" autocomplete="off" />
                    @if ($jornalero->documento !== null)
                        <label class="mt-2 flex min-h-11 items-center gap-3 text-base text-tinta">
                            <input type="checkbox" name="quitar_documento" value="1" class="h-5 w-5 accent-marca-700"> {{ __('jornaleros.quitar_documento') }}
                        </label>
                    @endif
                </div>

                <x-campo nombre="telefono" :etiqueta="__('jornaleros.campos.telefono')" tipo="tel" :valor="$jornalero->telefono" maxlength="30" autocomplete="off" />
                <x-seleccion nombre="categoria_habitual" :etiqueta="__('jornaleros.campos.categoria_habitual')" :valor="$jornalero->categoria_habitual" :opciones="['' => __('jornaleros.elige_categoria')] + $categorias" />
                <x-boton>{{ __('jornaleros.guardar') }}</x-boton>
            </x-formulario>
        </x-tarjeta>

        @if ($puedeAnonimizar)
            <x-tarjeta :titulo="__('jornaleros.anonimizar.titulo')">
                <p class="mb-4 text-base text-tinta">{{ __('jornaleros.anonimizar.texto') }}</p>
                <x-formulario :action="route('admin.jornaleros.anonimizar', $jornalero)">
                    <x-campo nombre="confirmar" :etiqueta="__('jornaleros.anonimizar.confirmar', ['palabra' => __('jornaleros.anonimizar.palabra')])" autocomplete="off" required />
                    <x-boton variante="peligro">{{ __('jornaleros.anonimizar.boton') }}</x-boton>
                </x-formulario>
            </x-tarjeta>
        @endif
    </div>
@endsection
