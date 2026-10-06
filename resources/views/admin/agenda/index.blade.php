@extends('layouts.app')

@section('titulo', 'Agenda')

@section('contenido')
    @php
        $colores = [
            'cosecha' => 'bg-marca-100 text-marca-900',
            'compra' => 'bg-sky-100 text-sky-900',
            'visita' => 'bg-violet-100 text-violet-900',
            'mantenimiento' => 'bg-amber-100 text-amber-900',
            'pago' => 'bg-rose-100 text-rose-900',
            'sanidad' => 'bg-red-100 text-red-900',
            'otro' => 'bg-slate-100 text-slate-800',
            'retiro' => 'bg-amber-50 text-amber-800 ring-1 ring-inset ring-amber-200',
            'permiso' => 'bg-slate-50 text-slate-600 ring-1 ring-inset ring-slate-200',
        ];
        $eventos = $porDia->flatten(1)->pluck('evento')->filter()->unique('id');
        $diasSemana = ['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'];
    @endphp

    <x-encabezado titulo="Agenda" subtitulo="Actividades de la finca: cosechas, compras, visitas, mantenimiento y pagos.">
        <x-boton tipo="button" data-abrir-modal="modal-evento">
            <x-icono nombre="mas" class="h-5 w-5" /> Nuevo evento
        </x-boton>
    </x-encabezado>

    <div class="mb-4 flex items-center justify-between gap-2">
        <x-boton variante="secundario" class="!px-3" :href="route('admin.agenda.index', ['mes' => $mes->subMonth()->format('Y-m')])" aria-label="Mes anterior">←</x-boton>
        <h2 class="text-center text-xl font-semibold capitalize text-slate-900">{{ $mes->translatedFormat('F Y') }}</h2>
        <x-boton variante="secundario" class="!px-3" :href="route('admin.agenda.index', ['mes' => $mes->addMonth()->format('Y-m')])" aria-label="Mes siguiente">→</x-boton>
    </div>

    {{-- Escritorio: calendario mensual --}}
    <div class="hidden overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200 md:block">
        <div class="grid grid-cols-7 border-b border-slate-200 bg-marca-50 text-center text-sm font-semibold text-marca-900">
            @foreach ($diasSemana as $nombre)
                <div class="py-2">{{ $nombre }}</div>
            @endforeach
        </div>
        <div class="grid grid-cols-7">
            @foreach ($dias as $dia)
                @php $elementos = $porDia->get($dia->toDateString(), collect()); @endphp
                <div @class([
                    'min-h-28 border-b border-r border-slate-100 p-1.5 [&:nth-child(7n)]:border-r-0',
                    'bg-slate-50/70' => ! $dia->isSameMonth($mes),
                ])>
                    <p @class([
                        'mb-1 flex h-7 w-7 items-center justify-center rounded-full text-sm font-semibold',
                        'bg-marca-600 text-white' => $dia->isToday(),
                        'text-slate-400' => ! $dia->isToday() && ! $dia->isSameMonth($mes),
                        'text-slate-700' => ! $dia->isToday() && $dia->isSameMonth($mes),
                    ])>{{ $dia->day }}</p>

                    <ul class="space-y-1">
                        @foreach ($elementos->take(3) as $elemento)
                            <li>
                                @if ($elemento['evento'])
                                    <button type="button" data-abrir-modal="evento-{{ $elemento['evento']->id }}"
                                            @class(['block w-full truncate rounded-md px-1.5 py-0.5 text-left text-xs font-medium', $colores[$elemento['color']], 'line-through opacity-60' => $elemento['evento']->estaCompletado()])>
                                        @if ($elemento['hora']){{ $elemento['hora'] }} @endif{{ $elemento['titulo'] }}
                                    </button>
                                @else
                                    <span class="block truncate rounded-md px-1.5 py-0.5 text-xs {{ $colores[$elemento['color']] }}">{{ $elemento['titulo'] }}</span>
                                @endif
                            </li>
                        @endforeach
                        @if ($elementos->count() > 3)
                            <li class="px-1.5 text-xs text-slate-500">+{{ $elementos->count() - 3 }} más</li>
                        @endif
                    </ul>
                </div>
            @endforeach
        </div>
    </div>

    {{-- Celular: lista de los días del mes que tienen algo --}}
    <div class="space-y-3 md:hidden">
        @php $diasConElementos = $dias->filter(fn ($dia) => $dia->isSameMonth($mes) && $porDia->has($dia->toDateString())); @endphp
        @forelse ($diasConElementos as $dia)
            <section @class(['rounded-2xl bg-white p-4 shadow-sm ring-1', 'ring-marca-300' => $dia->isToday(), 'ring-slate-200' => ! $dia->isToday()])>
                <h3 class="mb-2 font-semibold capitalize text-slate-900">{{ $dia->translatedFormat('l j') }}</h3>
                <ul class="space-y-2">
                    @foreach ($porDia[$dia->toDateString()] as $elemento)
                        <li>
                            @if ($elemento['evento'])
                                <button type="button" data-abrir-modal="evento-{{ $elemento['evento']->id }}"
                                        @class(['flex min-h-11 w-full items-center gap-2 rounded-xl px-3 text-left text-base font-medium', $colores[$elemento['color']], 'line-through opacity-60' => $elemento['evento']->estaCompletado()])>
                                    @if ($elemento['hora'])<span class="tabular-nums">{{ $elemento['hora'] }}</span>@endif
                                    <span class="min-w-0 truncate">{{ $elemento['titulo'] }}</span>
                                </button>
                            @else
                                <span class="flex min-h-11 items-center rounded-xl px-3 text-base {{ $colores[$elemento['color']] }}">{{ $elemento['titulo'] }}</span>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </section>
        @empty
            <x-vacio icono="calendario" mensaje="No hay nada agendado este mes." />
        @endforelse
    </div>

    <ul class="mt-4 flex flex-wrap gap-2 text-xs" aria-label="Colores">
        @foreach ($tipos + ['retiro' => 'Fin de retiro sanitario', 'permiso' => 'Permiso del personal'] as $clave => $texto)
            <li class="rounded-full px-2.5 py-1 font-medium {{ $colores[$clave] }}">{{ $texto }}</li>
        @endforeach
    </ul>

    @foreach ($eventos as $evento)
        <x-modal id="evento-{{ $evento->id }}" :titulo="$evento->titulo">
            <dl class="space-y-2 text-base">
                <div class="flex justify-between gap-3"><dt class="text-slate-500">Tipo</dt><dd class="font-semibold">{{ $evento->textoTipo() }}</dd></div>
                <div class="flex justify-between gap-3">
                    <dt class="text-slate-500">Cuándo</dt>
                    <dd class="text-right font-semibold first-letter:uppercase">
                        {{ $evento->inicia_at->translatedFormat('l j \d\e F')
                            .($evento->todo_el_dia ? '' : ', '.$evento->inicia_at->format('H:i'))
                            .($evento->termina_at ? ' a '.$evento->termina_at->format('H:i') : '') }}
                    </dd>
                </div>
                @if ($evento->lago)
                    <div class="flex justify-between gap-3"><dt class="text-slate-500">Lago</dt><dd class="font-semibold">{{ $evento->lago->nombre }}</dd></div>
                @endif
                @if ($evento->recordar_dias_antes > 0)
                    <div class="flex justify-between gap-3"><dt class="text-slate-500">Aviso</dt><dd class="font-semibold">{{ $evento->recordar_dias_antes }} día(s) antes</dd></div>
                @endif
            </dl>
            @if ($evento->descripcion)
                <p class="mt-4 rounded-xl bg-slate-50 p-3 text-base text-slate-700">{{ $evento->descripcion }}</p>
            @endif

            @if ($evento->estaCompletado())
                <p class="mt-4 text-base font-medium text-marca-700">Hecho el {{ $evento->completado_at->format('d/m/Y H:i') }}.</p>
            @else
                <div class="mt-5 grid grid-cols-2 gap-3">
                    <x-formulario :action="route('admin.agenda.destroy', $evento)" metodo="DELETE" class="!space-y-0">
                        <x-boton variante="secundario" class="w-full" onclick="return confirm('¿Eliminar este evento?')">
                            <x-icono nombre="basura" class="h-5 w-5" /> Eliminar
                        </x-boton>
                    </x-formulario>
                    <x-formulario :action="route('admin.agenda.completar', $evento)" class="!space-y-0">
                        <x-boton class="w-full"><x-icono nombre="ok" class="h-5 w-5" /> Hecho</x-boton>
                    </x-formulario>
                </div>
            @endif
        </x-modal>
    @endforeach

    <x-modal id="modal-evento" titulo="Nuevo evento">
        <x-formulario :action="route('admin.agenda.store')">
            <x-campo nombre="titulo" etiqueta="Título" placeholder="Ej. cosecha del lago L02" required />
            <div class="grid grid-cols-2 gap-4">
                <x-seleccion nombre="tipo" etiqueta="Tipo" :opciones="$tipos" required />
                <x-campo nombre="fecha" etiqueta="Fecha" tipo="date" :valor="today()->toDateString()" required />
                <x-campo nombre="hora" etiqueta="Hora (opcional)" tipo="time" />
                <x-campo nombre="hora_fin" etiqueta="Hasta (opcional)" tipo="time" />
                <x-seleccion nombre="lago_id" etiqueta="Lago (opcional)" :opciones="['' => 'Ninguno'] + $lagos" />
                <x-campo nombre="recordar_dias_antes" etiqueta="Avisar días antes" tipo="number" min="0" max="60" inputmode="numeric" valor="1" required />
            </div>
            <x-campo nombre="descripcion" etiqueta="Notas (opcional)" />
            <p class="text-sm text-slate-500">Sin hora, el evento es de todo el día. El aviso aparece en tu inicio.</p>

            <div class="grid grid-cols-2 gap-3 pt-2">
                <x-boton tipo="button" variante="secundario" data-cerrar-modal>Cancelar</x-boton>
                <x-boton>Agendar</x-boton>
            </div>
        </x-formulario>
    </x-modal>
@endsection
