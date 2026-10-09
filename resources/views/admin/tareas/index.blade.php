@extends('layouts.app')

@section('titulo', __('tareas.tareas'))

@section('contenido')
    <x-encabezado :titulo="__('tareas.tareas')" :subtitulo="__('tareas.trabajos_asignados_personal_campo')">
        <x-boton tipo="button" data-abrir-modal="modal-tarea" :disabled="$personal === []">
            <x-icono nombre="mas" class="h-5 w-5" /> {{ __('tareas.nueva_tarea') }}
        </x-boton>
    </x-encabezado>

    {{-- Filtro como las pestañas de la libreta: la opción activa lleva raya y negrita --}}
    <nav class="mb-6 flex gap-1 border-b border-renglon" aria-label="{{ __('tareas.filtrar_tareas') }}">
        @foreach (['pendientes' => __('tareas.pendientes'), 'completadas' => __('tareas.completadas')] as $valor => $texto)
            @php $activa = ($valor === 'completadas') === $verCompletadas; @endphp
            <a href="{{ route('admin.tareas.index', $valor === 'completadas' ? ['ver' => 'completadas'] : []) }}" @if ($activa) aria-current="page" @endif
               @class([
                   '-mb-px flex min-h-12 flex-1 items-center justify-center px-4 text-base transition-colors sm:flex-none',
                   'font-bold text-tinta shadow-[inset_0_-3px_0_var(--color-marca-700)]' => $activa,
                   'text-tinta-suave hover:bg-papel-hondo hover:text-tinta' => ! $activa,
               ])>{{ $texto }}</a>
        @endforeach
    </nav>

    @if ($personal === [])
        <x-vacio icono="personal" :mensaje="__('tareas.asignar_tareas_necesitas_trabajadores_celadores')" class="mb-6" />
    @endif

    @if ($tareas->isEmpty())
        <x-vacio icono="tareas" :mensaje="$verCompletadas ? __('tareas.sin_completadas') : __('tareas.sin_pendientes')" />
    @endif

    <div class="space-y-3">
        @foreach ($tareas as $tarea)
            <x-tarjeta>
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <h2 class="text-lg font-semibold text-tinta">{{ $tarea->titulo }}</h2>
                        <p class="text-sm text-tinta-suave">
                            {{ $tarea->asignado->name }}
                            @if ($tarea->lago) · {{ __('lagos.lago_nombre', ['lago' => $tarea->lago->nombre]) }} @endif
                            · {{ __('tareas.asignada_por', ['nombre' => $tarea->creador->name]) }}
                        </p>
                        @if ($tarea->descripcion)
                            <p class="mt-2 text-base text-tinta">{{ $tarea->descripcion }}</p>
                        @endif
                        @if ($tarea->estaCompletada())
                            <p class="mt-2 text-sm font-medium text-marca-700">
                                {{ __('tareas.hecha_el', ['momento' => $tarea->completada_at->format('d/m/Y H:i')]) }}@if ($tarea->nota_cierre): {{ $tarea->nota_cierre }}@endif
                            </p>
                        @endif
                    </div>

                    <div class="flex shrink-0 items-center gap-1">
                        @unless ($tarea->estaCompletada())
                            <span @class([
                                'rounded-full px-2.5 py-0.5 text-sm font-semibold',
                                'bg-alerta-fondo text-alerta' => $tarea->estaVencida(),
                                'bg-papel-hondo text-tinta' => ! $tarea->estaVencida(),
                            ])>{{ $tarea->estaVencida() ? __('tareas.vencida', ['fecha' => $tarea->fecha->format('d/m')]) : $tarea->fecha->format('d/m') }}</span>

                            <x-formulario :action="route('admin.tareas.destroy', $tarea)" metodo="DELETE" class="!space-y-0">
                                <button type="submit" class="flex h-12 w-12 items-center justify-center rounded-ficha text-tinta-suave hover:bg-alerta-fondo hover:text-alerta"
                                        aria-label="{{ __('tareas.eliminar_tarea', ['titulo' => $tarea->titulo]) }}" onclick="return confirm(@js(__('tareas.confirmar_eliminar')))">
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
        <nav class="mt-4 flex items-center justify-between gap-3" aria-label="{{ __('comun.paginas') }}">
            @if ($tareas->onFirstPage()) <span></span> @else <x-boton variante="secundario" :href="$tareas->previousPageUrl()"><x-icono nombre="abajo" class="h-5 w-5 rotate-90" /> {{ __('comun.anteriores') }}</x-boton> @endif
            <span class="text-sm text-tinta-suave">{{ __('comun.pagina_de', ['actual' => $tareas->currentPage(), 'total' => $tareas->lastPage()]) }}</span>
            @if ($tareas->hasMorePages()) <x-boton variante="secundario" :href="$tareas->nextPageUrl()">{{ __('comun.siguientes') }} <x-icono nombre="abajo" class="h-5 w-5 -rotate-90" /></x-boton> @else <span></span> @endif
        </nav>
    @endif

    @if ($personal !== [])
        <x-modal id="modal-tarea" :titulo="__('tareas.nueva_tarea')">
            <x-formulario :action="route('admin.tareas.store')">
                <x-campo nombre="titulo" :etiqueta="__('tareas.hay_hacer')" :placeholder="__('tareas.ej_limpiar_entrada_agua')" required />
                <x-seleccion nombre="asignada_a" :etiqueta="__('tareas.asignar')" :opciones="$personal" required />
                <div class="grid grid-cols-2 gap-4">
                    <x-campo nombre="fecha" :etiqueta="__('tareas.texto')" tipo="date" :valor="\App\Support\Region::hoy()->toDateString()" :min="\App\Support\Region::hoy()->toDateString()" required />
                    <x-seleccion nombre="lago_id" :etiqueta="__('comun.lago_opcional')" :opciones="['' => __('comun.ninguno')] + $lagos" />
                </div>
                <x-campo nombre="descripcion" :etiqueta="__('tareas.detalles_opcional')" />

                <div class="grid grid-cols-2 gap-3 pt-2">
                    <x-boton tipo="button" variante="secundario" data-cerrar-modal>{{ __('comun.cancelar') }}</x-boton>
                    <x-boton>{{ __('tareas.asignar_2') }}</x-boton>
                </div>
            </x-formulario>
        </x-modal>
    @endif
@endsection
