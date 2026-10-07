@extends('layouts.app')

@section('titulo', __('plataforma.fincas.titulo'))

@section('contenido')
    <x-encabezado :titulo="__('plataforma.fincas.titulo')" :subtitulo="__('plataforma.fincas.subtitulo', ['activas' => $fincas->reject->estaSuspendida()->count(), 'total' => $fincas->count()])" />

    <x-tabla :columnas="[__('plataforma.fincas.finca'), __('plataforma.fincas.usuarios'), __('plataforma.fincas.estado'), __('plataforma.fincas.creada'), '']" :vacio="__('plataforma.fincas.vacio')">
        @foreach ($fincas as $finca)
            <tr>
                <td data-label="{{ __('plataforma.fincas.finca') }}">
                    <p class="font-semibold text-slate-900">{{ $finca->nombre }}</p>
                    <p class="text-sm text-slate-500">{{ __('plataforma.fincas.codigo', ['codigo' => $finca->codigo]) }}@if ($finca->pais) · {{ \App\Support\Region::nombrePais($finca->pais) }}@endif</p>
                </td>
                <td data-label="{{ __('plataforma.fincas.usuarios') }}" class="tabular-nums">{{ $finca->users_count }}</td>
                <td data-label="{{ __('plataforma.fincas.estado') }}">
                    @if ($finca->estaSuspendida())
                        <span class="inline-block rounded-full bg-red-100 px-3 py-1 text-sm font-semibold text-red-800">{{ __('plataforma.fincas.suspendida_desde', ['fecha' => $finca->suspendida_at->format('d/m/Y')]) }}</span>
                        @if ($finca->motivo_suspension)
                            <p class="mt-1 text-sm text-slate-500">{{ $finca->motivo_suspension }}</p>
                        @endif
                    @else
                        <span class="inline-block rounded-full bg-marca-100 px-3 py-1 text-sm font-semibold text-marca-800">{{ __('plataforma.fincas.activa') }}</span>
                    @endif
                </td>
                <td data-label="{{ __('plataforma.fincas.creada') }}" class="tabular-nums">{{ \App\Support\Region::local($finca->created_at)->format('d/m/Y') }}</td>
                <td>
                    @if ($finca->estaSuspendida())
                        <x-formulario :action="route('plataforma.fincas.reactivar', $finca)" class="!space-y-0">
                            <x-boton variante="secundario" class="w-full">{{ __('plataforma.fincas.reactivar') }}</x-boton>
                        </x-formulario>
                    @else
                        <x-boton tipo="button" variante="peligro" class="w-full" data-abrir-modal="suspender-{{ $finca->id }}">{{ __('plataforma.fincas.suspender') }}</x-boton>
                    @endif
                </td>
            </tr>
        @endforeach
    </x-tabla>

    @foreach ($fincas->reject->estaSuspendida() as $finca)
        <x-modal id="suspender-{{ $finca->id }}" :titulo="__('plataforma.fincas.suspender_finca', ['finca' => $finca->nombre])">
            <x-formulario :action="route('plataforma.fincas.suspender', $finca)">
                <p class="rounded-xl bg-red-50 p-4 text-base text-red-900">{{ __('plataforma.fincas.aviso_suspender', ['usuarios' => $finca->users_count]) }}</p>
                <x-campo nombre="motivo_suspension" id="motivo-{{ $finca->id }}" :etiqueta="__('plataforma.fincas.motivo')" />

                <div class="grid grid-cols-2 gap-3 pt-2">
                    <x-boton tipo="button" variante="secundario" data-cerrar-modal>{{ __('comun.cancelar') }}</x-boton>
                    <x-boton variante="peligro">{{ __('plataforma.fincas.suspender') }}</x-boton>
                </div>
            </x-formulario>
        </x-modal>
    @endforeach
@endsection
