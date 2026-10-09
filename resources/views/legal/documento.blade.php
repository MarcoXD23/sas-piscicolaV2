@extends('layouts.app')

@php
    $textos = __('legal.documentos.'.$documento);
    // Quién responde: de LEGAL_ENTIDAD y LEGAL_CORREO; si faltan, un aviso visible (nunca datos inventados)
    $datos = [
        'app' => config('app.name'),
        'entidad' => config('legal.entidad') ?? __('legal.pendiente_entidad'),
        'correo' => config('legal.correo') ?? __('legal.pendiente_correo'),
    ];
    $reemplazar = fn (string $texto) => strtr($texto, [':app' => $datos['app'], ':entidad' => $datos['entidad'], ':correo' => $datos['correo']]);
@endphp

@section('titulo', $textos['titulo'])

@section('contenido')
    <article class="mx-auto max-w-3xl rounded-ficha bg-ficha p-5 text-tinta shadow-ficha ring-1 ring-renglon sm:p-8">
        <x-aviso tono="atencion" class="mb-5" role="note">{{ __('legal.borrador') }}</x-aviso>
        <h1 class="text-2xl font-bold text-tinta sm:text-3xl">{{ $textos['titulo'] }}</h1>
        <p class="mt-1 text-sm text-tinta-suave">{{ __('legal.version', ['version' => config('legal.version')]) }}</p>
        <p class="mt-5 text-base">{{ $reemplazar($textos['intro']) }}</p>

        @foreach ($textos['secciones'] as $titulo => $texto)
            <section class="mt-6">
                <h2 class="text-lg font-semibold text-tinta">{{ $titulo }}</h2>
                <p class="mt-1 text-base leading-relaxed">{{ $reemplazar($texto) }}</p>
            </section>
        @endforeach

        <p class="mt-8 border-t border-renglon pt-4 text-sm">
            <a href="{{ route('legal.terminos') }}" class="text-marca-700 underline">{{ __('legal.terminos') }}</a>
            ·
            <a href="{{ route('legal.privacidad') }}" class="text-marca-700 underline">{{ __('legal.privacidad') }}</a>
        </p>
    </article>
@endsection
