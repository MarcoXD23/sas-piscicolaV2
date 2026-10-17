@extends('layouts.app')

@section('titulo', __('jornadas.titulo'))

@php
    use App\Models\Jornada;
    use App\Support\CalculoPesaje;

    $tonos = [Jornada::ABIERTA => 'info', Jornada::CERRADA => 'bien', Jornada::CANCELADA => 'neutro'];
@endphp

@section('contenido')
    <x-encabezado :titulo="__('jornadas.titulo')" :subtitulo="__('jornadas.subtitulo')">
        @if (auth()->user()->tieneRol('administrador'))
            <x-boton :href="route('admin.jornadas.create')"><x-icono nombre="mas" class="h-5 w-5" /> {{ __('jornadas.programar') }}</x-boton>
        @endif
    </x-encabezado>

    @if ($jornadas->isEmpty())
        <x-vacio icono="pez" :mensaje="__('jornadas.sin_jornadas')" />
    @else
        <x-tabla :columnas="[__('jornadas.fecha'), __('jornadas.lagos'), __('jornadas.estado'), __('jornadas.kg_cosechados')]">
            @foreach ($jornadas as $jornada)
                <tr>
                    <td data-label="{{ __('jornadas.fecha') }}" class="font-bold tabular-nums">
                        <a href="{{ route('admin.jornadas.show', $jornada) }}" class="underline-offset-2 hover:underline">{{ $jornada->fecha->format('d/m/Y') }}</a>
                    </td>
                    <td data-label="{{ __('jornadas.lagos') }}">{{ $jornada->lagos->map(fn ($jl) => $jl->lago->nombre)->implode(', ') }}</td>
                    <td data-label="{{ __('jornadas.estado') }}"><x-insignia :tono="$tonos[$jornada->estado]">{{ __('jornadas.estados.'.$jornada->estado) }}</x-insignia></td>
                    <td data-label="{{ __('jornadas.kg_cosechados') }}" class="tabular-nums">
                        {{ $jornada->estado === Jornada::CERRADA ? CalculoPesaje::kg((float) $jornada->cosechas_sum_kg) : '—' }}
                    </td>
                </tr>
            @endforeach
        </x-tabla>
        <div class="mt-6">{{ $jornadas->links() }}</div>
    @endif
@endsection
