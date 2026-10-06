@extends('layouts.app')

@section('titulo', 'Solicitudes')

@section('contenido')
    @php
        $numero = fn ($valor) => number_format((float) $valor, 1, ',', '.');
        $chips = ['aprobado' => 'bg-marca-100 text-marca-800', 'rechazado' => 'bg-red-100 text-red-800'];
    @endphp

    <x-encabezado titulo="Personal" subtitulo="Permisos y horas extra por revisar." />
    <x-pestanas-personal />

    <div class="grid gap-6 lg:grid-cols-2">
        <section>
            <h2 class="mb-3 text-lg font-semibold text-slate-900">Permisos pendientes</h2>
            @forelse ($permisos as $permiso)
                <x-tarjeta class="mb-3">
                    <p class="text-lg font-semibold text-slate-900">{{ $permiso->usuario->name }}</p>
                    <p class="text-base text-slate-700">
                        {{ $permiso->fecha_inicio->format('d/m/Y') }} – {{ $permiso->fecha_fin->format('d/m/Y') }} ({{ $permiso->dias() }} día(s))
                    </p>
                    <p class="text-sm text-slate-500">{{ $permiso->motivo }}</p>
                    @if ($turnosAfectados[$permiso->id] > 0)
                        <p class="mt-2 rounded-lg bg-amber-50 p-2 text-sm text-amber-900">
                            Tiene {{ $turnosAfectados[$permiso->id] }} turno(s) programado(s) en esas fechas.
                        </p>
                    @endif

                    <x-revision :accion="route('admin.permisos.revisar', $permiso)" :id="'permiso-'.$permiso->id" />
                </x-tarjeta>
            @empty
                <x-vacio icono="ok" mensaje="No hay permisos por revisar." />
            @endforelse
        </section>

        <section>
            <h2 class="mb-3 text-lg font-semibold text-slate-900">Horas extra pendientes</h2>
            @forelse ($horasExtras as $hora)
                <x-tarjeta class="mb-3">
                    <p class="text-lg font-semibold text-slate-900">{{ $hora->usuario->name }}</p>
                    <p class="text-base text-slate-700">{{ $hora->fecha->format('d/m/Y') }} · {{ $numero($hora->horas) }} horas</p>
                    <p class="text-sm text-slate-500">{{ $hora->motivo }}</p>

                    <x-revision :accion="route('admin.horas-extras.revisar', $hora)" :id="'horas-'.$hora->id" />
                </x-tarjeta>
            @empty
                <x-vacio icono="ok" mensaje="No hay horas extra por revisar." />
            @endforelse
        </section>
    </div>

    @if ($revisadas->isNotEmpty())
        <x-tarjeta titulo="Revisadas recientemente" class="mt-6">
            <ul class="divide-y divide-slate-100">
                @foreach ($revisadas as $solicitud)
                    <li class="flex items-start justify-between gap-3 py-2.5">
                        <div>
                            <p class="font-medium text-slate-900">
                                {{ $solicitud->usuario->name }} ·
                                @if ($solicitud instanceof \App\Models\Permiso)
                                    permiso {{ $solicitud->fecha_inicio->format('d/m') }} – {{ $solicitud->fecha_fin->format('d/m') }}
                                @else
                                    {{ $numero($solicitud->horas) }} h extra el {{ $solicitud->fecha->format('d/m') }}
                                @endif
                            </p>
                            <p class="text-sm text-slate-500">
                                {{ collect([$solicitud->revisor?->name, $solicitud->revisado_at?->format('d/m/Y H:i'), $solicitud->respuesta])->filter()->join(' · ') }}
                            </p>
                        </div>
                        <span class="shrink-0 rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $chips[$solicitud->estado] }}">{{ $solicitud->textoEstado() }}</span>
                    </li>
                @endforeach
            </ul>
        </x-tarjeta>
    @endif
@endsection
