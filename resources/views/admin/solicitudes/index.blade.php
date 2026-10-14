@extends('layouts.app')

@section('titulo', __('solicitudes.solicitudes'))

@section('contenido')
    @php
        $numero = fn ($valor) => \App\Support\Formato::numero((float) $valor, 1);
        $chips = ['aprobado' => 'bg-bien-fondo text-bien', 'rechazado' => 'bg-alerta-fondo text-alerta'];
    @endphp

    <x-encabezado :titulo="__('comun.personal')" :subtitulo="__('solicitudes.permisos_horas_extra_revisar')" />
    <x-pestanas-personal />

    <div class="grid gap-6 lg:grid-cols-2">
        <section>
            <h2 class="mb-3 text-lg font-semibold text-tinta">{{ __('solicitudes.permisos_pendientes') }}</h2>
            @forelse ($permisos as $permiso)
                <x-tarjeta class="mb-3">
                    <p class="text-lg font-semibold text-tinta">{{ $permiso->usuario->name }}</p>
                    <p class="text-base text-tinta">
                        {{ __('comun.rango_dias', ['desde' => $permiso->fecha_inicio->format('d/m/Y'), 'hasta' => $permiso->fecha_fin->format('d/m/Y'), 'dias' => $permiso->dias()]) }}
                    </p>
                    <p class="text-sm text-tinta-suave">{{ $permiso->motivo }}</p>
                    @if ($turnosAfectados[$permiso->id] > 0)
                        <p class="mt-2 rounded-lg bg-atencion-fondo p-2 text-sm text-atencion">
                            {{ __('solicitudes.turnos_afectados', ['cantidad' => $turnosAfectados[$permiso->id]]) }}
                        </p>
                    @endif

                    <x-revision :accion="route('admin.permisos.revisar', $permiso)" :id="'permiso-'.$permiso->id" />
                </x-tarjeta>
            @empty
                <x-vacio icono="ok" :mensaje="__('solicitudes.no_hay_permisos_revisar')" />
            @endforelse
        </section>

        <section>
            <h2 class="mb-3 text-lg font-semibold text-tinta">{{ __('solicitudes.horas_extra_pendientes') }}</h2>
            @forelse ($horasExtras as $hora)
                <x-tarjeta class="mb-3">
                    <p class="text-lg font-semibold text-tinta">{{ $hora->usuario->name }}</p>
                    <p class="text-base text-tinta">{{ $hora->fecha->format('d/m/Y') }} · {{ __('solicitudes.horas', ['horas' => $numero($hora->horas)]) }}</p>
                    <p class="text-sm text-tinta-suave">{{ $hora->motivo }}</p>

                    <x-revision :accion="route('admin.horas-extras.revisar', $hora)" :id="'horas-'.$hora->id" />
                </x-tarjeta>
            @empty
                <x-vacio icono="ok" :mensaje="__('solicitudes.no_hay_horas_extra_revisar')" />
            @endforelse
        </section>
    </div>

    @if ($revisadas->isNotEmpty())
        <x-tarjeta :titulo="__('solicitudes.revisadas_recientemente')" class="mt-6">
            <ul class="divide-y divide-renglon">
                @foreach ($revisadas as $solicitud)
                    <li class="flex items-start justify-between gap-3 py-2.5">
                        <div>
                            <p class="font-medium text-tinta">
                                {{ $solicitud->usuario->name }} ·
                                @if ($solicitud instanceof \App\Models\Permiso)
                                    {{ __('solicitudes.permiso_rango', ['desde' => $solicitud->fecha_inicio->format('d/m'), 'hasta' => $solicitud->fecha_fin->format('d/m')]) }}
                                @else
                                    {{ __('solicitudes.horas_extra_el', ['horas' => $numero($solicitud->horas), 'fecha' => $solicitud->fecha->format('d/m')]) }}
                                @endif
                            </p>
                            <p class="text-sm text-tinta-suave">
                                {{ collect([$solicitud->revisor?->name, $solicitud->revisado_at?->formatoHora('d/m/Y H:i'), $solicitud->respuesta])->filter()->join(' · ') }}
                            </p>
                        </div>
                        <span class="shrink-0 rounded-full px-2.5 py-0.5 text-sm font-semibold {{ $chips[$solicitud->estado] }}">{{ $solicitud->textoEstado() }}</span>
                    </li>
                @endforeach
            </ul>
        </x-tarjeta>
    @endif
@endsection
