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
    <article class="mx-auto max-w-3xl rounded-2xl bg-white p-5 text-slate-800 shadow-suave ring-1 ring-slate-200 sm:p-8">
        <p class="mb-5 rounded-xl bg-amber-50 p-4 text-base font-semibold text-amber-900" role="note">{{ __('legal.borrador') }}</p>
        <h1 class="text-2xl font-bold text-slate-900 sm:text-3xl">{{ $textos['titulo'] }}</h1>
        <p class="mt-1 text-sm text-texto-suave">{{ __('legal.version', ['version' => config('legal.version')]) }}</p>
        <p class="mt-5 text-base">{{ $reemplazar($textos['intro']) }}</p>

        @foreach ($textos['secciones'] as $titulo => $texto)
            <section class="mt-6">
                <h2 class="text-lg font-semibold text-slate-900">{{ $titulo }}</h2>
                <p class="mt-1 text-base leading-relaxed">{{ $reemplazar($texto) }}</p>
            </section>
        @endforeach

        <p class="mt-8 border-t border-slate-200 pt-4 text-sm">
            <a href="{{ route('legal.terminos') }}" class="text-marca-700 underline">{{ __('legal.terminos') }}</a>
            ·
            <a href="{{ route('legal.privacidad') }}" class="text-marca-700 underline">{{ __('legal.privacidad') }}</a>
        </p>
    </article>
@endsection
