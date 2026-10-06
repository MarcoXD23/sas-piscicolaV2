@extends('layouts.app')

@section('titulo', 'Mi trabajo')

@section('contenido')
    @php
        $numero = fn ($valor, $decimales = 1) => number_format((float) $valor, $decimales, ',', '.');
        $chips = ['pendiente' => 'bg-amber-100 text-amber-900', 'aprobado' => 'bg-marca-100 text-marca-800', 'rechazado' => 'bg-red-100 text-red-800'];
    @endphp

    <x-encabezado titulo="Mi trabajo" subtitulo="Tus tareas, permisos y horas extra.">
        <x-boton tipo="button" variante="secundario" data-abrir-modal="modal-permiso">Pedir permiso</x-boton>
        <x-boton tipo="button" variante="secundario" data-abrir-modal="modal-horas">Horas extra</x-boton>
    </x-encabezado>

    @unless ($enTurno)
        <div role="status" class="mb-5 flex items-start gap-3 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-base text-amber-900">
            <x-icono nombre="reloj" class="mt-0.5 h-5 w-5" />
            <span>Estás fuera de tu turno: puedes ver tus tareas, pero solo las marcas como hechas durante tu turno.</span>
        </div>
    @endunless

    <section class="mb-8">
        <h2 class="mb-3 text-lg font-semibold text-slate-900">Tareas pendientes</h2>

        @forelse ($pendientes as $tarea)
            <x-tarjeta class="mb-3">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <h3 class="text-lg font-semibold text-slate-900">{{ $tarea->titulo }}</h3>
                        <p @class(['text-sm', 'font-semibold text-red-700' => $tarea->estaVencida(), 'text-slate-500' => ! $tarea->estaVencida()])>
                            {{ $tarea->estaVencida() ? 'Vencida: ' : 'Para el ' }}{{ $tarea->fecha->format('d/m/Y') }}
                            @if ($tarea->lago) · Lago {{ $tarea->lago->nombre }} @endif
                        </p>
                        @if ($tarea->descripcion)
                            <p class="mt-2 text-base text-slate-700">{{ $tarea->descripcion }}</p>
                        @endif
                    </div>
                </div>

                <x-boton tipo="button" class="mt-4 w-full" data-abrir-modal="modal-completar-{{ $tarea->id }}" :disabled="! $enTurno">
                    <x-icono nombre="ok" class="h-5 w-5" /> Marcar como hecha
                </x-boton>
            </x-tarjeta>

            @if ($enTurno)
                <x-modal id="modal-completar-{{ $tarea->id }}" titulo="{{ $tarea->titulo }}">
                    <x-formulario :action="route('mi-trabajo.tareas.completar', $tarea)">
                        <x-campo nombre="nota_cierre" id="nota-{{ $tarea->id }}" etiqueta="Nota (opcional)" placeholder="Ej. se cambió la malla del lago" />
                        <div class="grid grid-cols-2 gap-3 pt-2">
                            <x-boton tipo="button" variante="secundario" data-cerrar-modal>Cancelar</x-boton>
                            <x-boton>Hecha</x-boton>
                        </div>
                    </x-formulario>
                </x-modal>
            @endif
        @empty
            <x-vacio icono="tareas" mensaje="No tienes tareas pendientes." />
        @endforelse

        @if ($completadas->isNotEmpty())
            <details class="mt-4 rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200">
                <summary class="min-h-11 cursor-pointer content-center text-base font-semibold text-marca-700">Completadas recientemente</summary>
                <ul class="mt-2 divide-y divide-slate-100">
                    @foreach ($completadas as $tarea)
                        <li class="py-2.5">
                            <p class="font-medium text-slate-900">{{ $tarea->titulo }}</p>
                            <p class="text-sm text-slate-500">{{ $tarea->completada_at->format('d/m/Y H:i') }}@if ($tarea->nota_cierre) · {{ $tarea->nota_cierre }}@endif</p>
                        </li>
                    @endforeach
                </ul>
            </details>
        @endif
    </section>

    @if ($liquidaciones->isNotEmpty())
        <x-tarjeta titulo="Mis pagos de nómina" class="mb-6">
            @foreach ($liquidaciones as $liquidacion)
                <div class="flex items-start justify-between gap-3 border-t border-slate-100 py-2.5 first:border-t-0 first:pt-0">
                    <div>
                        <p class="font-medium text-slate-900">Semana del {{ $liquidacion->semana_inicio->format('d/m') }} al {{ $liquidacion->semana_fin->format('d/m/Y') }}</p>
                        <p class="text-sm text-slate-500">
                            {{ $numero($liquidacion->horas_turnos) }} h de turno · {{ $numero($liquidacion->horas_extra) }} h extra
                            @if ($liquidacion->descuento_pescado > 0) · descuento de pescado {{ \App\Support\Formato::pesos($liquidacion->descuento_pescado) }} @endif
                        </p>
                    </div>
                    <div class="shrink-0 text-right">
                        <p class="font-bold tabular-nums">{{ \App\Support\Formato::pesos($liquidacion->total_pagar) }}</p>
                        <p @class(['text-xs font-semibold', 'text-marca-700' => $liquidacion->estaPagada(), 'text-amber-700' => ! $liquidacion->estaPagada()])>
                            {{ $liquidacion->estaPagada() ? 'Pagada' : 'Por pagar' }}
                        </p>
                    </div>
                </div>
            @endforeach
        </x-tarjeta>
    @endif

    @if ($creditosPescado->isNotEmpty())
        <x-tarjeta titulo="Mi saldo de pescado" :subtitulo="'Debes '.\App\Support\Formato::pesos($creditosPescado->sum('saldo'))" class="mb-6">
            @foreach ($creditosPescado as $credito)
                <div class="flex justify-between gap-3 border-t border-slate-100 py-2.5 first:border-t-0 first:pt-0">
                    <span>{{ $credito->fecha->format('d/m/Y') }} · {{ $numero($credito->kg) }} kg</span>
                    <span class="font-semibold tabular-nums">{{ \App\Support\Formato::pesos($credito->saldo) }}</span>
                </div>
            @endforeach
        </x-tarjeta>
    @endif

    <div class="grid gap-6 md:grid-cols-2">
        <x-tarjeta titulo="Mis permisos">
            @forelse ($permisos as $permiso)
                <div class="flex items-start justify-between gap-3 border-t border-slate-100 py-3 first:border-t-0 first:pt-0">
                    <div>
                        <p class="font-medium text-slate-900">{{ $permiso->fecha_inicio->format('d/m') }} – {{ $permiso->fecha_fin->format('d/m/Y') }} ({{ $permiso->dias() }} día(s))</p>
                        <p class="text-sm text-slate-500">{{ $permiso->motivo }}@if ($permiso->respuesta) · Respuesta: {{ $permiso->respuesta }}@endif</p>
                    </div>
                    <span class="shrink-0 rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $chips[$permiso->estado] }}">{{ $permiso->textoEstado() }}</span>
                </div>
            @empty
                <p class="text-base text-slate-500">No has pedido permisos.</p>
            @endforelse
        </x-tarjeta>

        <x-tarjeta titulo="Mis horas extra" :subtitulo="'Aprobadas este mes: '.$numero($horasAprobadasMes).' h'">
            @forelse ($horasExtras as $hora)
                <div class="flex items-start justify-between gap-3 border-t border-slate-100 py-3 first:border-t-0 first:pt-0">
                    <div>
                        <p class="font-medium text-slate-900">{{ $hora->fecha->format('d/m/Y') }} · {{ $numero($hora->horas) }} h</p>
                        <p class="text-sm text-slate-500">{{ $hora->motivo }}@if ($hora->respuesta) · Respuesta: {{ $hora->respuesta }}@endif</p>
                    </div>
                    <span class="shrink-0 rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $chips[$hora->estado] }}">{{ $hora->textoEstado() }}</span>
                </div>
            @empty
                <p class="text-base text-slate-500">No has reportado horas extra.</p>
            @endforelse
        </x-tarjeta>
    </div>

    <x-modal id="modal-permiso" titulo="Pedir permiso">
        <x-formulario :action="route('mi-trabajo.permisos.store')">
            <div class="grid grid-cols-2 gap-4">
                <x-campo nombre="fecha_inicio" etiqueta="Desde" tipo="date" :min="today()->toDateString()" required />
                <x-campo nombre="fecha_fin" etiqueta="Hasta" tipo="date" :min="today()->toDateString()" required />
            </div>
            <x-campo nombre="motivo" id="permiso-motivo" etiqueta="Motivo" placeholder="Ej. cita médica" required />
            <div class="grid grid-cols-2 gap-3 pt-2">
                <x-boton tipo="button" variante="secundario" data-cerrar-modal>Cancelar</x-boton>
                <x-boton>Enviar</x-boton>
            </div>
        </x-formulario>
    </x-modal>

    <x-modal id="modal-horas" titulo="Reportar horas extra">
        <x-formulario :action="route('mi-trabajo.horas-extras.store')">
            <div class="grid grid-cols-2 gap-4">
                <x-campo nombre="fecha" etiqueta="Fecha" tipo="date" :valor="today()->toDateString()" :max="today()->toDateString()" required />
                <x-campo nombre="horas" etiqueta="Horas" tipo="number" step="0.5" min="0.5" max="12" inputmode="decimal" required />
            </div>
            <x-campo nombre="motivo" id="horas-motivo" etiqueta="¿En qué trabajaste?" placeholder="Ej. cosecha del lago L02" required />
            <div class="grid grid-cols-2 gap-3 pt-2">
                <x-boton tipo="button" variante="secundario" data-cerrar-modal>Cancelar</x-boton>
                <x-boton>Enviar</x-boton>
            </div>
        </x-formulario>
    </x-modal>
@endsection
