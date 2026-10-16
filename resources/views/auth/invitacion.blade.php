@extends('layouts.app')

@section('titulo', $invitacion ? __('invitaciones.crear_titulo') : __('invitaciones.invalida_titulo'))

@section('contenido')
    <div class="mb-8 text-center">
        <x-logo class="mx-auto mb-5 h-20 w-20 rounded-2xl shadow-levantada ring-1 ring-white/20" />
        {{-- Con sesión abierta la página va sobre el fondo claro de la app (sin la foto del ingreso): título oscuro --}}
        <h1 @class(['text-3xl font-bold tracking-tight', 'text-white drop-shadow-sm' => ! auth()->check(), 'text-tinta' => auth()->check()])>{{ $invitacion ? __('invitaciones.crear_titulo') : __('invitaciones.invalida_titulo') }}</h1>
        @if ($invitacion && ! auth()->check())
            <p class="mt-2 text-base text-marca-100">{{ __('invitaciones.crear_subtitulo') }}</p>
        @endif
    </div>

    <x-tarjeta class="!shadow-levantada !ring-0">
        @if (! $invitacion)
            {{-- Vencida, usada, anulada o inventada: el mismo aviso (no revela qué invitaciones existen) --}}
            <x-aviso tono="alerta">{{ __('invitaciones.invalida') }}</x-aviso>
            <x-boton :href="route('login')" class="mt-5 w-full">{{ __('invitaciones.ir_ingresar') }}</x-boton>
        @elseif ($sesionAbierta)
            {{-- Con una sesión abierta no se crea la finca: se avisa con qué cuenta y se ofrece cerrarla y volver aquí --}}
            <x-aviso tono="atencion">{{ __('invitaciones.sesion_abierta', ['nombre' => $sesionAbierta->name]) }}</x-aviso>
            <x-formulario :action="route('invitacion.salir', $codigo)" class="mt-5">
                <x-boton class="w-full">{{ __('invitaciones.cerrar_y_continuar') }}</x-boton>
            </x-formulario>
        @else
            <x-formulario :action="route('invitacion.store', $codigo)">
                {{-- El correo es el invitado: se muestra, pero no se envía ni se puede cambiar --}}
                <div>
                    <label for="invitacion-correo" class="mb-1.5 block text-base font-semibold text-tinta">{{ __('invitaciones.tu_correo') }}</label>
                    <input id="invitacion-correo" type="email" value="{{ $invitacion->correo }}" disabled
                           class="block min-h-12 w-full rounded-ficha border border-renglon bg-papel-hondo px-4 text-base text-tinta-suave">
                </div>
                <x-campo nombre="finca" :etiqueta="__('invitaciones.nombre_finca')" autocomplete="organization" required />
                <x-seleccion nombre="pais" :etiqueta="__('invitaciones.pais')" :opciones="$paises" valor="CO" required />
                <x-seleccion nombre="zona_horaria" :etiqueta="__('invitaciones.zona_horaria')" :opciones="array_combine($zonas, $zonas)" :valor="$zonaPorDefecto"
                             :ayuda="__('invitaciones.zona_ayuda')" required />
                <x-campo nombre="nombre" :etiqueta="__('invitaciones.tu_nombre')" autocomplete="name" required />
                <x-contrasena nombre="clave" :etiqueta="__('invitaciones.contrasena')" autocomplete="new-password" required nueva />
                <x-contrasena nombre="clave_confirmation" :etiqueta="__('invitaciones.confirma_contrasena')" autocomplete="new-password" required />

                {{-- Aceptar los términos y la privacidad es obligatorio, como en el registro --}}
                <label class="flex items-start gap-3 text-base text-tinta">
                    <input type="checkbox" name="acepto" value="1" required @checked(old('acepto')) class="mt-1 h-5 w-5 rounded border-renglon-fuerte accent-marca-600">
                    <span>{!! __('legal.aceptar.registro_casilla', [
                        'terminos' => '<a href="'.e(route('legal.terminos')).'" target="_blank" rel="noopener" class="font-semibold text-marca-700 underline">'.e(__('legal.terminos')).'</a>',
                        'privacidad' => '<a href="'.e(route('legal.privacidad')).'" target="_blank" rel="noopener" class="font-semibold text-marca-700 underline">'.e(__('legal.privacidad')).'</a>',
                    ]) !!}</span>
                </label>
                @error('acepto')
                    <p class="text-sm font-medium text-alerta">{{ $message }}</p>
                @enderror

                <x-boton class="w-full">{{ __('invitaciones.crear') }}</x-boton>
            </x-formulario>
        @endif
    </x-tarjeta>
@endsection
