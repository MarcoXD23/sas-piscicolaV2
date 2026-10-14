@extends('layouts.app')

@section('titulo', __('acceso.solicitar_acceso'))

@section('contenido')
    <div class="mb-8 text-center">
        <x-logo class="mx-auto mb-5 h-20 w-20 rounded-2xl shadow-levantada ring-1 ring-white/20" />
        <h1 class="text-3xl font-bold tracking-tight text-white drop-shadow-sm">{{ __('acceso.solicitar_acceso') }}</h1>
        <p class="mt-2 text-base text-marca-100">{{ __('acceso.propietario_finca_aprobara_tu_cuenta') }}</p>
    </div>

    <x-tarjeta class="!shadow-levantada !ring-0">
        @error('registro')
            <div role="alert" class="mb-5 animate-sacudir rounded-ficha border border-alerta-borde bg-alerta-fondo px-4 py-3 text-base text-alerta">
                <p>{{ $message }}</p>
                <a href="{{ route('password.request') }}" class="mt-1 inline-flex min-h-12 items-center font-semibold underline underline-offset-2">{{ __('acceso.recuperar.enlace_login') }}</a>
            </div>
        @enderror
        <x-formulario :action="route('registro')">
            <x-campo nombre="codigo_finca" :etiqueta="__('acceso.codigo_finca')" autocomplete="off" autocapitalize="none" required
                     :ayuda="__('acceso.pideselo_propietario_finca')" />
            <x-campo nombre="name" :etiqueta="__('acceso.nombre_completo')" autocomplete="name" required />
            <x-campo nombre="email" :etiqueta="__('acceso.correo')" tipo="email" autocomplete="email" inputmode="email" required />
            <x-contrasena nombre="password" :etiqueta="__('acceso.contrasena')" autocomplete="new-password" required
                     :ayuda="__('acceso.minimo_8_caracteres')" />
            <x-contrasena nombre="password_confirmation" :etiqueta="__('acceso.confirma_contrasena')" autocomplete="new-password" required />

            {{-- Aceptar los términos y la privacidad es obligatorio para registrarse --}}
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

            <x-boton class="w-full">{{ __('acceso.enviar_solicitud') }}</x-boton>
        </x-formulario>
    </x-tarjeta>

    <p class="mt-6 text-center text-base text-marca-50">
        {{ __('acceso.ya_tienes_cuenta') }}
        <a href="{{ route('login') }}" class="font-semibold text-white underline decoration-marca-300/60 underline-offset-4 transition-colors hover:decoration-white">{{ __('acceso.ingresar') }}</a>
    </p>
    <p class="mt-4 text-center text-sm text-marca-100">
        <a href="{{ route('legal.terminos') }}" class="underline decoration-marca-300/50 underline-offset-4 transition-colors hover:text-white">{{ __('legal.terminos') }}</a>
        ·
        <a href="{{ route('legal.privacidad') }}" class="underline decoration-marca-300/50 underline-offset-4 transition-colors hover:text-white">{{ __('legal.privacidad') }}</a>
    </p>
@endsection

