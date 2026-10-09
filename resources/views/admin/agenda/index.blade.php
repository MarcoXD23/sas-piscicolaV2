@extends('layouts.app')

@section('titulo', __('agenda.agenda'))

@section('contenido')
    @php
        $colores = [
            'cosecha' => 'bg-bien-fondo text-bien',
            'compra' => 'bg-info-fondo text-info',
            'visita' => 'bg-violet-100 text-violet-900',
            'mantenimiento' => 'bg-atencion-fondo text-atencion',
            'pago' => 'bg-rose-100 text-rose-900',
            'sanidad' => 'bg-alerta-fondo text-alerta',
            'otro' => 'bg-papel-hondo text-tinta',
            'retiro' => 'bg-atencion-fondo text-atencion ring-1 ring-inset ring-atencion-borde',
            'permiso' => 'bg-papel text-tinta-suave ring-1 ring-inset ring-renglon',
        ];
        $eventos = $porDia->flatten(1)->pluck('evento')->filter()->unique('id');
        $diasSemana = __('comun.dias_cortos');
    @endphp

    <x-encabezado :titulo="__('agenda.agenda')" :subtitulo="__('agenda.actividades_finca_cosechas_compras_visitas')">
        <x-boton tipo="button" data-abrir-modal="modal-evento">
            <x-icono nombre="mas" class="h-5 w-5" /> {{ __('agenda.nuevo_evento') }}
        </x-boton>
    </x-encabezado>

    <div class="mb-4 flex items-center justify-between gap-2">
        <x-boton variante="secundario" class="w-12 !px-0" :href="route('admin.agenda.index', ['mes' => $mes->subMonth()->format('Y-m')])" :aria-label="__('agenda.mes_anterior')"><x-icono nombre="abajo" class="h-5 w-5 rotate-90" /></x-boton>
        <h2 class="text-center text-xl font-semibold capitalize text-tinta">{{ $mes->translatedFormat('F Y') }}</h2>
        <x-boton variante="secundario" class="w-12 !px-0" :href="route('admin.agenda.index', ['mes' => $mes->addMonth()->format('Y-m')])" :aria-label="__('agenda.mes_siguiente')"><x-icono nombre="abajo" class="h-5 w-5 -rotate-90" /></x-boton>
    </div>

    {{-- Escritorio: calendario mensual --}}
    <div class="hidden overflow-hidden rounded-ficha bg-ficha shadow-ficha ring-1 ring-renglon md:block">
        <div class="grid grid-cols-7 border-b border-renglon bg-papel-hondo text-center text-sm font-bold text-tinta">
            @foreach ($diasSemana as $nombre)
                <div class="py-2">{{ $nombre }}</div>
            @endforeach
        </div>
        <div class="grid grid-cols-7">
            @foreach ($dias as $dia)
                @php $elementos = $porDia->get($dia->toDateString(), collect()); @endphp
                <div @class([
                    'min-h-28 border-b border-r border-renglon p-1.5 [&:nth-child(7n)]:border-r-0',
                    'bg-papel' => ! $dia->isSameMonth($mes),
                ])>
                    <p @class([
                        'mb-1 flex h-7 w-7 items-center justify-center rounded-full text-sm font-semibold',
                        'bg-marca-600 text-white' => $dia->isToday(),
                        'text-tinta-suave' => ! $dia->isToday() && ! $dia->isSameMonth($mes),
                        'text-tinta' => ! $dia->isToday() && $dia->isSameMonth($mes),
                    ])>{{ $dia->day }}</p>

                    <ul class="space-y-1">
                        @foreach ($elementos->take(3) as $elemento)
                            <li>
                                @if ($elemento['evento'])
                                    <button type="button" data-abrir-modal="evento-{{ $elemento['evento']->id }}"
                                            @class(['block w-full truncate rounded-md px-1.5 py-0.5 text-left text-sm font-medium', $colores[$elemento['color']], 'line-through opacity-60' => $elemento['evento']->estaCompletado()])>
                                        @if ($elemento['hora']){{ $elemento['hora'] }} @endif{{ $elemento['titulo'] }}
                                    </button>
                                @else
                                    <span class="block truncate rounded-md px-1.5 py-0.5 text-sm {{ $colores[$elemento['color']] }}">{{ $elemento['titulo'] }}</span>
                                @endif
                            </li>
                        @endforeach
                        @if ($elementos->count() > 3)
                            <li class="px-1.5 text-sm text-tinta-suave">{{ __('agenda.mas', ['cantidad' => $elementos->count() - 3]) }}</li>
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
            <section @class(['rounded-ficha bg-ficha p-4 shadow-ficha ring-1', 'ring-marca-300' => $dia->isToday(), 'ring-renglon' => ! $dia->isToday()])>
                <h3 class="mb-2 font-semibold capitalize text-tinta">{{ $dia->translatedFormat('l j') }}</h3>
                <ul class="space-y-2">
                    @foreach ($porDia[$dia->toDateString()] as $elemento)
                        <li>
                            @if ($elemento['evento'])
                                <button type="button" data-abrir-modal="evento-{{ $elemento['evento']->id }}"
                                        @class(['flex min-h-12 w-full items-center gap-2 rounded-ficha px-3 text-left text-base font-medium', $colores[$elemento['color']], 'line-through opacity-60' => $elemento['evento']->estaCompletado()])>
                                    @if ($elemento['hora'])<span class="tabular-nums">{{ $elemento['hora'] }}</span>@endif
                                    <span class="min-w-0 truncate">{{ $elemento['titulo'] }}</span>
                                </button>
                            @else
                                <span class="flex min-h-12 items-center rounded-ficha px-3 text-base {{ $colores[$elemento['color']] }}">{{ $elemento['titulo'] }}</span>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </section>
        @empty
            <x-vacio icono="calendario" :mensaje="__('agenda.no_hay_nada_agendado_este')" />
        @endforelse
    </div>

    <ul class="mt-4 flex flex-wrap gap-2 text-sm" aria-label="{{ __('agenda.colores') }}">
        @foreach ($tipos + ['retiro' => __('agenda.fin_retiro'), 'permiso' => __('agenda.permiso_personal')] as $clave => $texto)
            <li class="rounded-full px-2.5 py-1 font-medium {{ $colores[$clave] }}">{{ $texto }}</li>
        @endforeach
    </ul>

    @foreach ($eventos as $evento)
        <x-modal id="evento-{{ $evento->id }}" :titulo="$evento->titulo">
            <dl class="space-y-2 text-base">
                <div class="flex justify-between gap-3"><dt class="text-tinta-suave">{{ __('agenda.tipo') }}</dt><dd class="font-semibold">{{ $evento->textoTipo() }}</dd></div>
                <div class="flex justify-between gap-3">
                    <dt class="text-tinta-suave">{{ __('agenda.cuando') }}</dt>
                    <dd class="text-right font-semibold first-letter:uppercase">
                        {{ $evento->inicia_at->translatedFormat(__('comun.formatos.dia_mes'))
                            .($evento->todo_el_dia ? '' : ', '.$evento->inicia_at->format('H:i'))
                            .($evento->termina_at ? ' a '.$evento->termina_at->format('H:i') : '') }}
                    </dd>
                </div>
                @if ($evento->lago)
                    <div class="flex justify-between gap-3"><dt class="text-tinta-suave">{{ __('agenda.lago') }}</dt><dd class="font-semibold">{{ $evento->lago->nombre }}</dd></div>
                @endif
                @if ($evento->recordar_dias_antes > 0)
                    <div class="flex justify-between gap-3"><dt class="text-tinta-suave">{{ __('agenda.aviso') }}</dt><dd class="font-semibold">{{ __('agenda.dias_antes', ['dias' => $evento->recordar_dias_antes]) }}</dd></div>
                @endif
            </dl>
            @if ($evento->descripcion)
                <p class="mt-4 rounded-ficha bg-papel p-3 text-base text-tinta">{{ $evento->descripcion }}</p>
            @endif

            @if ($evento->estaCompletado())
                <p class="mt-4 text-base font-medium text-marca-700">{{ __('agenda.hecho_el', ['momento' => $evento->completado_at->format('d/m/Y H:i')]) }}</p>
            @else
                <div class="mt-5 grid grid-cols-3 gap-2">
                    <x-formulario :action="route('admin.agenda.destroy', $evento)" metodo="DELETE" class="!space-y-0">
                        <x-boton variante="secundario" class="w-full !px-2" onclick="return confirm(@js(__('agenda.confirmar_eliminar')))">
                            <x-icono nombre="basura" class="h-5 w-5" /> {{ __('agenda.eliminar') }}
                        </x-boton>
                    </x-formulario>
                    <x-boton tipo="button" variante="secundario" class="w-full !px-2" data-abrir-modal="editar-evento-{{ $evento->id }}">{{ __('agenda.editar') }}</x-boton>
                    <x-formulario :action="route('admin.agenda.completar', $evento)" class="!space-y-0">
                        <x-boton class="w-full !px-2"><x-icono nombre="ok" class="h-5 w-5" /> {{ __('agenda.hecho') }}</x-boton>
                    </x-formulario>
                </div>
            @endif
        </x-modal>
    @endforeach

    {{-- Un solo formulario de evento: "Nuevo evento" (null) y la edición de cada evento sin completar --}}
    @foreach ([null, ...$eventos->reject->estaCompletado()] as $editar)
        @php
            $id = $editar ? 'editar-evento-'.$editar->id : 'modal-evento';
            $sufijo = $editar ? '-'.$editar->id : '';
        @endphp
        <x-modal :id="$id" :titulo="$editar ? __('agenda.editar_evento') : __('agenda.nuevo_evento')">
            <x-formulario :action="$editar ? route('admin.agenda.update', $editar) : route('admin.agenda.store')" :metodo="$editar ? 'PUT' : 'POST'">
                <x-campo nombre="titulo" id="titulo{{ $sufijo }}" :etiqueta="__('agenda.titulo')" :placeholder="__('agenda.ej_cosecha_lago_l02')" :valor="$editar?->titulo" required />
                <div class="grid grid-cols-2 gap-4">
                    <x-seleccion nombre="tipo" id="tipo{{ $sufijo }}" :etiqueta="__('agenda.tipo')" :opciones="$tipos" :valor="$editar?->tipo" required />
                    <x-campo nombre="fecha" id="fecha{{ $sufijo }}" :etiqueta="__('comun.fecha')" tipo="date" :valor="($editar?->inicia_at ?? \App\Support\Region::hoy())->toDateString()" required />
                    <x-campo nombre="hora" id="hora{{ $sufijo }}" :etiqueta="__('agenda.hora_opcional')" tipo="time"
                             :valor="$editar && ! $editar->todo_el_dia ? $editar->inicia_at->format('H:i') : null" />
                    <x-campo nombre="hora_fin" id="hora-fin{{ $sufijo }}" :etiqueta="__('agenda.hasta_opcional')" tipo="time" :valor="$editar?->termina_at?->format('H:i')" />
                    <x-seleccion nombre="lago_id" id="lago{{ $sufijo }}" :etiqueta="__('comun.lago_opcional')" :opciones="['' => __('comun.ninguno')] + $lagos" :valor="$editar?->lago_id" />
                    <x-campo nombre="recordar_dias_antes" id="aviso{{ $sufijo }}" :etiqueta="__('agenda.avisar_dias_antes')" tipo="number" min="0" max="60" inputmode="numeric"
                             :valor="$editar?->recordar_dias_antes ?? 1" required />
                </div>
                <x-campo nombre="descripcion" id="descripcion{{ $sufijo }}" :etiqueta="__('agenda.notas_opcional')" :valor="$editar?->descripcion" />
                <p class="text-sm text-tinta-suave">{{ __('agenda.sin_hora_evento_es_todo') }}</p>

                <div class="grid grid-cols-2 gap-3 pt-2">
                    <x-boton tipo="button" variante="secundario" data-cerrar-modal>{{ __('comun.cancelar') }}</x-boton>
                    <x-boton>{{ $editar ? __('comun.guardar_cambios') : __('agenda.agendar') }}</x-boton>
                </div>
            </x-formulario>
        </x-modal>
    @endforeach
@endsection
