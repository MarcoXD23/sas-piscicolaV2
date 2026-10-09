@extends('layouts.app')

@section('titulo', __('plataforma.fincas.titulo'))

@section('contenido')
    <x-encabezado :titulo="__('plataforma.fincas.titulo')" :subtitulo="__('plataforma.fincas.subtitulo', ['activas' => $fincas->reject->estaSuspendida()->count(), 'total' => $fincas->count()])" />

    <x-tabla :columnas="[__('plataforma.fincas.finca'), __('plataforma.fincas.usuarios'), __('plataforma.fincas.estado'), __('plataforma.fincas.creada'), '']" :vacio="__('plataforma.fincas.vacio')">
        @foreach ($fincas as $finca)
            <tr>
                <td data-label="{{ __('plataforma.fincas.finca') }}">
                    <p class="font-semibold text-tinta">{{ $finca->nombre }}</p>
                    <p class="text-sm text-tinta-suave">{{ __('plataforma.fincas.codigo', ['codigo' => $finca->codigo]) }}@if ($finca->pais) · {{ \App\Support\Region::nombrePais($finca->pais) }}@endif</p>
                </td>
                <td data-label="{{ __('plataforma.fincas.usuarios') }}" class="tabular-nums">{{ $finca->users_count }}</td>
                <td data-label="{{ __('plataforma.fincas.estado') }}">
                    @if ($finca->enEliminacion())
                        <span class="inline-block rounded-full bg-alerta px-3 py-1 text-sm font-semibold text-white">{{ __('datos.eliminar.en_eliminacion', ['fecha' => $finca->eliminacion_programada_at->format('d/m/Y')]) }}</span>
                    @elseif ($finca->estaSuspendida())
                        <span class="inline-flex items-center gap-1 rounded-md bg-alerta-fondo px-2 py-0.5 text-sm font-bold text-alerta ring-1 ring-alerta-borde"><x-icono nombre="alerta" class="h-3.5 w-3.5" />{{ __('plataforma.fincas.suspendida_desde', ['fecha' => $finca->suspendida_at->format('d/m/Y')]) }}</span>
                        @if ($finca->motivo_suspension)
                            <p class="mt-1 text-sm text-tinta-suave">{{ $finca->motivo_suspension }}</p>
                        @endif
                    @else
                        <span class="inline-flex items-center gap-1 rounded-md bg-bien-fondo px-2 py-0.5 text-sm font-bold text-bien ring-1 ring-bien-borde"><x-icono nombre="ok" class="h-3.5 w-3.5" />{{ __('plataforma.fincas.activa') }}</span>
                    @endif
                </td>
                <td data-label="{{ __('plataforma.fincas.creada') }}" class="tabular-nums">{{ \App\Support\Region::local($finca->created_at)->format('d/m/Y') }}</td>
                <td class="space-y-2">
                    @if ($finca->enEliminacion())
                        <x-formulario :action="route('plataforma.fincas.cancelar-eliminacion', $finca)" class="!space-y-0">
                            <x-boton variante="secundario" class="w-full">{{ __('datos.eliminar.cancelar_plataforma') }}</x-boton>
                        </x-formulario>
                    @elseif ($finca->estaSuspendida())
                        <x-formulario :action="route('plataforma.fincas.reactivar', $finca)" class="!space-y-0">
                            <x-boton variante="secundario" class="w-full">{{ __('plataforma.fincas.reactivar') }}</x-boton>
                        </x-formulario>
                    @else
                        <x-boton tipo="button" variante="peligro" class="w-full" data-abrir-modal="suspender-{{ $finca->id }}">{{ __('plataforma.fincas.suspender') }}</x-boton>
                    @endif
                    @unless ($finca->enEliminacion())
                        <x-boton tipo="button" variante="secundario" class="w-full !text-red-700" data-abrir-modal="eliminar-{{ $finca->id }}">{{ __('datos.eliminar.eliminar_plataforma') }}</x-boton>
                    @endunless
                </td>
            </tr>
        @endforeach
    </x-tabla>

    @foreach ($fincas->reject->estaSuspendida() as $finca)
        <x-modal id="suspender-{{ $finca->id }}" :titulo="__('plataforma.fincas.suspender_finca', ['finca' => $finca->nombre])">
            <x-formulario :action="route('plataforma.fincas.suspender', $finca)">
                <x-aviso tono="alerta">{{ __('plataforma.fincas.aviso_suspender', ['usuarios' => $finca->users_count]) }}</x-aviso>
                <x-campo nombre="motivo_suspension" id="motivo-{{ $finca->id }}" :etiqueta="__('plataforma.fincas.motivo')" />

                <div class="grid grid-cols-2 gap-3 pt-2">
                    <x-boton tipo="button" variante="secundario" data-cerrar-modal>{{ __('comun.cancelar') }}</x-boton>
                    <x-boton variante="peligro">{{ __('plataforma.fincas.suspender') }}</x-boton>
                </div>
            </x-formulario>
        </x-modal>
    @endforeach
    @foreach ($fincas->reject->enEliminacion() as $finca)
        <x-modal id="eliminar-{{ $finca->id }}" :titulo="__('datos.eliminar.confirmar_plataforma', ['finca' => $finca->nombre])">
            <x-formulario :action="route('plataforma.fincas.eliminar', $finca)">
                <x-aviso tono="alerta">{{ __('datos.eliminar.ayuda', ['dias' => config('datos.dias_gracia')]) }}</x-aviso>
                <x-campo nombre="codigo" id="eliminar-codigo-{{ $finca->id }}" :etiqueta="__('datos.eliminar.codigo', ['codigo' => $finca->codigo])" autocomplete="off" required />
                <x-campo nombre="actual" id="eliminar-clave-{{ $finca->id }}" :etiqueta="__('datos.eliminar.tu_contrasena_plataforma')" tipo="password" autocomplete="current-password" required />
                <div class="grid grid-cols-2 gap-3 pt-2">
                    <x-boton tipo="button" variante="secundario" data-cerrar-modal>{{ __('comun.cancelar') }}</x-boton>
                    <x-boton variante="peligro">{{ __('datos.eliminar.boton') }}</x-boton>
                </div>
            </x-formulario>
        </x-modal>
    @endforeach
@endsection
