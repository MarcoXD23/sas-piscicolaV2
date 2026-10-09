@extends('layouts.app')

@section('titulo', __('legal.aceptar.titulo'))

@section('contenido')
    <x-tarjeta class="mx-auto max-w-xl" :titulo="__('legal.aceptar.titulo')">
        <p class="text-base text-slate-700">{{ __('legal.aceptar.texto', ['version' => config('legal.version')]) }}</p>
        <p class="mt-3 flex flex-wrap gap-4 text-base">
            <a href="{{ route('legal.terminos') }}" target="_blank" rel="noopener" class="font-semibold text-marca-700 underline">{{ __('legal.terminos') }}</a>
            <a href="{{ route('legal.privacidad') }}" target="_blank" rel="noopener" class="font-semibold text-marca-700 underline">{{ __('legal.privacidad') }}</a>
        </p>

        <x-formulario :action="route('legal.aceptar.store')" class="mt-5">
            <label class="flex items-start gap-3 text-base text-slate-800">
                <input type="checkbox" name="acepto" value="1" required class="mt-1 h-5 w-5 rounded border-slate-400 accent-marca-600">
                <span>{{ __('legal.aceptar.casilla') }}</span>
            </label>
            @error('acepto')
                <p class="text-sm font-medium text-red-600">{{ $message }}</p>
            @enderror
            <x-boton class="w-full">{{ __('legal.aceptar.boton') }}</x-boton>
        </x-formulario>

        <form method="POST" action="{{ route('logout') }}" class="mt-4 text-center">
            @csrf
            <button class="inline-flex min-h-12 items-center text-base text-slate-600 underline">{{ __('legal.aceptar.no_acepto') }}</button>
        </form>
    </x-tarjeta>
@endsection
