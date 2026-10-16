@extends('layouts.app')

@section('titulo', __('cuenta.titulo'))

@section('contenido')
    <x-encabezado :titulo="__('cuenta.titulo')" :subtitulo="__('cuenta.subtitulo')" />

    <div class="grid max-w-3xl gap-6">
        <x-tarjeta :titulo="__('cuenta.datos')">
            <dl class="grid grid-cols-1 gap-3 text-base sm:grid-cols-2">
                <div><dt class="text-sm text-tinta-suave">{{ __('cuenta.nombre') }}</dt><dd class="font-semibold text-tinta">{{ $usuario->name }}</dd></div>
                <div><dt class="text-sm text-tinta-suave">{{ __('cuenta.correo') }}</dt><dd class="break-all font-semibold text-tinta">{{ $usuario->email }}</dd></div>
            </dl>
        </x-tarjeta>

        <x-tarjeta :titulo="__('cuenta.formato_hora.titulo')" :subtitulo="__('cuenta.formato_hora.ayuda')">
            <x-formulario :action="route('mi-cuenta.formato-hora')" metodo="PUT">
                <x-seleccion nombre="formato_hora" :etiqueta="__('cuenta.formato_hora.etiqueta')" :valor="$usuario->formato_hora" :opciones="$formatosHora" />
                <x-boton variante="secundario">{{ __('cuenta.formato_hora.guardar') }}</x-boton>
            </x-formulario>
        </x-tarjeta>

        <x-tarjeta :titulo="__('cuenta.contrasena.titulo')" :subtitulo="__('cuenta.contrasena.ayuda')">
            <x-formulario :action="route('mi-cuenta.contrasena')" metodo="PUT">
                <x-contrasena nombre="actual" :etiqueta="__('cuenta.contrasena.actual')" autocomplete="current-password" required :ayuda="__('cuenta.contrasena.actual_ayuda')" />
                <x-contrasena nombre="password" :etiqueta="__('cuenta.contrasena.nueva')" autocomplete="new-password" minlength="10" required nueva />
                <x-contrasena nombre="password_confirmation" :etiqueta="__('cuenta.contrasena.confirmar')" autocomplete="new-password" minlength="10" required />
                <x-boton>{{ __('cuenta.contrasena.guardar') }}</x-boton>
            </x-formulario>
        </x-tarjeta>

        <x-tarjeta :titulo="__('cuenta.dos_pasos.titulo')" :subtitulo="__('cuenta.dos_pasos.que_es')">
            @if ($usuario->tieneDosPasos())
                <p class="mb-2 text-base font-semibold text-bien">{{ __('cuenta.dos_pasos.activa', ['fecha' => $usuario->dos_pasos_activado_at->format('d/m/Y')]) }}</p>
                <p class="mb-5 text-sm text-tinta-suave">{{ trans_choice('cuenta.dos_pasos.recuperacion_quedan', count($usuario->dos_pasos_recuperacion ?? [])) }}</p>

                <x-formulario :action="route('mi-cuenta.dos-pasos.desactivar')" metodo="DELETE" class="border-t border-renglon pt-5">
                    <p class="text-sm text-tinta-suave">{{ __('cuenta.dos_pasos.desactivar_ayuda') }}</p>
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <x-contrasena nombre="actual" id="desactivar-actual" :etiqueta="__('cuenta.contrasena.actual')" autocomplete="current-password" required />
                        <x-campo nombre="codigo" id="desactivar-codigo" :etiqueta="__('cuenta.dos_pasos.ingreso_codigo')" inputmode="numeric" autocomplete="one-time-code" maxlength="20" required />
                    </div>
                    <x-boton variante="peligro">{{ __('cuenta.dos_pasos.desactivar') }}</x-boton>
                </x-formulario>
            @else
                <p class="mb-4 text-base text-tinta-suave">{{ __('cuenta.dos_pasos.inactiva') }}</p>
                <x-boton :href="route('mi-cuenta.dos-pasos.activar')">{{ __('cuenta.dos_pasos.activar') }}</x-boton>
            @endif
        </x-tarjeta>
    </div>
@endsection
