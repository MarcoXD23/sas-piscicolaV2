@extends('layouts.app')

@section('titulo', 'Tareas')

@section('contenido')
    <x-encabezado titulo="Tareas" subtitulo="Trabajos asignados al personal de campo.">
        <x-boton tipo="button" data-abrir-modal="modal-tarea" :disabled="$personal === []">
            <x-icono nombre="mas" class="h-5 w-5" /> Nueva tarea
        </x-boton>
    </x-encabezado>

    <nav class="mb-6 flex gap-1 rounded-2xl bg-slate-200/70 p-1" aria-label="Filtrar tareas">
        @foreach (['pendientes' => 'Pendientes', 'completadas' => 'Completadas'] as $valor => $texto)
            @php $activa = ($valor === 'completadas') === $verCompletadas; @endphp
            <a href="{{ route('admin.tareas.index', $valor === 'completadas' ? ['ver' => 'completadas'] : []) }}" @if ($activa) aria-current="page" @endif
               @class([
                   'flex min-h-11 flex-1 items-center justify-center rounded-xl px-4 text-base font-semibold',
                   'bg-white text-marca-800 shadow-sm' => $activa,
                   'text-slate-600 hover:text-slate-900' => ! $activa,
               ])>{{ $texto }}</a>
        @endforeach
    </nav>

    @if ($personal === [])
        <x-vacio icono="personal" mensaje="Para asignar tareas necesitas trabajadores o celadores aprobados." class="mb-6" />
    @endif

    @if ($tareas->isEmpty())
        <x-vacio icono="tareas" :mensaje="$verCompletadas ? 'Aún no hay tareas completadas.' : 'No hay tareas pendientes.'" />
    @endif

    <div class="space-y-3">
        @foreach ($tareas as $tarea)
            <x-tarjeta>
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <h2 class="text-lg font-semibold text-slate-900">{{ $tarea->titulo }}</h2>
                        <p class="text-sm text-slate-500">
                            {{ $tarea->asignado->name }}
                            @if ($tarea->lago) · Lago {{ $tarea->lago->nombre }} @endif
                            · asignada por {{ $tarea->creador->name }}
                        </p>
                        @if ($tarea->descripcion)
                            <p class="mt-2 text-base text-slate-700">{{ $tarea->descripcion }}</p>
                        @endif
                        @if ($tarea->estaCompletada())
                            <p class="mt-2 text-sm font-medium text-marca-700">
                                Hecha el {{ $tarea->completada_at->format('d/m/Y H:i') }}@if ($tarea->nota_cierre): {{ $tarea->nota_cierre }}@endif
                            </p>
                        @endif
                    </div>

                    <div class="flex shrink-0 items-center gap-1">
                        @unless ($tarea->estaCompletada())
                            <span @class([
                                'rounded-full px-2.5 py-0.5 text-xs font-semibold',
                                'bg-red-100 text-red-800' => $tarea->estaVencida(),
                                'bg-slate-100 text-slate-700' => ! $tarea->estaVencida(),
                            ])>{{ $tarea->estaVencida() ? 'Vencida ' : '' }}{{ $tarea->fecha->format('d/m') }}</span>

                            <x-formulario :action="route('admin.tareas.destroy', $tarea)" metodo="DELETE" class="!space-y-0">
                                <button type="submit" class="flex h-11 w-11 items-center justify-center rounded-xl text-slate-400 hover:bg-red-50 hover:text-red-600"
                                        aria-label="Eliminar tarea {{ $tarea->titulo }}" onclick="return confirm('¿Eliminar esta tarea?')">
                                    <x-icono nombre="basura" class="h-5 w-5" />
                                </button>
                            </x-formulario>
                        @endunless
                    </div>
                </div>
            </x-tarjeta>
        @endforeach
    </div>

    @if ($tareas->hasPages())
        <nav class="mt-4 flex items-center justify-between gap-3" aria-label="Páginas">
            @if ($tareas->onFirstPage()) <span></span> @else <x-boton variante="secundario" :href="$tareas->previousPageUrl()">← Anteriores</x-boton> @endif
            <span class="text-sm text-slate-500">Página {{ $tareas->currentPage() }} de {{ $tareas->lastPage() }}</span>
            @if ($tareas->hasMorePages()) <x-boton variante="secundario" :href="$tareas->nextPageUrl()">Siguientes →</x-boton> @else <span></span> @endif
        </nav>
    @endif

    @if ($personal !== [])
        <x-modal id="modal-tarea" titulo="Nueva tarea">
            <x-formulario :action="route('admin.tareas.store')">
                <x-campo nombre="titulo" etiqueta="¿Qué hay que hacer?" placeholder="Ej. limpiar la entrada de agua" required />
                <x-seleccion nombre="asignada_a" etiqueta="Asignar a" :opciones="$personal" required />
                <div class="grid grid-cols-2 gap-4">
                    <x-campo nombre="fecha" etiqueta="Para el" tipo="date" :valor="today()->toDateString()" :min="today()->toDateString()" required />
                    <x-seleccion nombre="lago_id" etiqueta="Lago (opcional)" :opciones="['' => 'Ninguno'] + $lagos" />
                </div>
                <x-campo nombre="descripcion" etiqueta="Detalles (opcional)" />

                <div class="grid grid-cols-2 gap-3 pt-2">
                    <x-boton tipo="button" variante="secundario" data-cerrar-modal>Cancelar</x-boton>
                    <x-boton>Asignar</x-boton>
                </div>
            </x-formulario>
        </x-modal>
    @endif
@endsection
