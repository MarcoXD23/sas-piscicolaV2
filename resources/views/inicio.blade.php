@extends('layouts.app')

@section('titulo', __('inicio.inicio'))

@section('contenido')
    <section class="relative mb-6 overflow-hidden rounded-3xl bg-marca-800 text-white shadow-md">
        <img src="/images/estanque.webp" alt="" aria-hidden="true" decoding="async"
             class="absolute inset-0 h-full w-full object-cover opacity-35">
        <div class="absolute inset-0 bg-gradient-to-r from-marca-900/90 to-marca-800/40"></div>

        <div class="relative px-5 py-7 sm:px-8 sm:py-10">
            <p class="text-base text-marca-100">{{ $usuario->tenant->nombre }}</p>
            <h1 class="mt-1 text-2xl font-bold sm:text-3xl">{{ __('inicio.hola', ['nombre' => $usuario->name]) }}</h1>

            <ul class="mt-4 flex flex-wrap gap-2" aria-label="{{ __('inicio.tus_roles') }}">
                @foreach ($usuario->roles as $rol)
                    <li class="rounded-full bg-white/15 px-3 py-1 text-sm font-semibold capitalize ring-1 ring-white/25">{{ $rol->nombre }}</li>
                @endforeach
            </ul>
        </div>
    </section>

    @if ($trabajaPorTurnos)
        <x-tarjeta class="mb-6">
            <div class="flex items-start gap-4">
                <span @class([
                    'flex h-12 w-12 shrink-0 items-center justify-center rounded-xl',
                    'bg-marca-100 text-marca-700' => $turnoActual,
                    'bg-slate-100 text-slate-500' => ! $turnoActual,
                ])>
                    <x-icono nombre="reloj" />
                </span>
                <div>
                    @if ($turnoActual)
                        <p class="text-sm font-medium text-marca-700">{{ __('inicio.estas_turno') }}</p>
                        <p class="text-lg font-semibold text-slate-900">
                            <span class="capitalize">{{ $turnoActual->rol }}</span> {{ __('inicio.hasta_las', ['hora' => $turnoActual->termina_at->format('H:i')]) }}
                        </p>
                    @else
                        <p class="text-sm font-medium text-slate-500">{{ __('inicio.fuera_turno') }}</p>
                        <p class="text-lg font-semibold text-slate-900">
                            @if ($proximoTurno)
                                {{ __('inicio.proximo_turno') }} <span class="first-letter:uppercase">{{ $proximoTurno->inicia_at->translatedFormat(__('comun.formatos.dia_mes_hora')) }}</span>
                                <span class="capitalize">({{ $proximoTurno->rol }})</span>
                            @else
                                {{ __('inicio.sin_turnos') }}
                            @endif
                        </p>
                    @endif
                </div>
            </div>
        </x-tarjeta>
    @endif

    @if ($recordatorios->isNotEmpty())
        <x-tarjeta :titulo="__('inicio.agenda_no_olvides')" class="mb-6">
            <ul class="divide-y divide-slate-100">
                @foreach ($recordatorios as $evento)
                    <li class="flex items-center justify-between gap-3 py-2.5">
                        <a href="{{ route('admin.agenda.index', ['mes' => $evento->inicia_at->format('Y-m')]) }}" class="min-w-0 hover:underline">
                            <span class="block truncate font-medium text-slate-900">{{ $evento->titulo }}@if ($evento->lago) · {{ $evento->lago->nombre }}@endif</span>
                            <span class="text-sm text-slate-500">{{ $evento->textoTipo() }}</span>
                        </a>
                        <span @class([
                            'shrink-0 rounded-full px-2.5 py-0.5 text-xs font-semibold',
                            'bg-red-100 text-red-800' => $evento->estaVencido(),
                            'bg-marca-100 text-marca-800' => $evento->inicia_at->isToday(),
                            'bg-slate-100 text-slate-700' => ! $evento->estaVencido() && ! $evento->inicia_at->isToday(),
                        ])>
                            {{ $evento->estaVencido() ? __('inicio.vencido', ['fecha' => $evento->inicia_at->format('d/m')]) : ($evento->inicia_at->isToday() ? __('comun.hoy') : $evento->inicia_at->format('d/m')) }}
                        </span>
                    </li>
                @endforeach
            </ul>
        </x-tarjeta>
    @endif

    @if ($tareasPendientes > 0)
        <a href="{{ route('mi-trabajo.index') }}" class="mb-6 flex items-center gap-4 rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200 hover:ring-marca-300">
            <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-marca-50 text-marca-700"><x-icono nombre="tareas" /></span>
            <span class="text-lg font-semibold text-slate-900">{{ __('inicio.tareas_pendientes', ['cantidad' => $tareasPendientes]) }}</span>
        </a>
    @endif

    @if ($solicitudesPendientes > 0)
        <a href="{{ route('admin.solicitudes.index') }}" class="mb-6 flex items-center gap-4 rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200 hover:ring-marca-300">
            <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-amber-50 text-amber-700"><x-icono nombre="personal" /></span>
            <span class="text-lg font-semibold text-slate-900">{{ __('inicio.solicitudes_pendientes', ['cantidad' => $solicitudesPendientes]) }}</span>
        </a>
    @endif

    @if ($novedadesNoche->isNotEmpty())
        <a href="{{ route('noche.index') }}" class="mb-6 flex items-start gap-3 rounded-2xl border border-red-200 bg-red-50 p-4 text-red-900 hover:bg-red-100">
            <x-icono nombre="luna" class="mt-0.5" />
            <div>
                <p class="font-semibold">{{ __('inicio.novedades_seguridad_ultimas_24_horas') }}</p>
                <ul class="text-base">
                    @foreach ($novedadesNoche as $novedad)
                        <li>
                            {{ $novedad->registrada_at->format('H:i') }} · {{ $novedad->textoTipo() }}@if ($novedad->lago) {{ __('inicio.en_lago', ['lago' => $novedad->lago->nombre]) }}@endif:
                            {{ $novedad->descripcion }} <span class="text-red-700">({{ $novedad->usuario?->name }})</span>
                        </li>
                    @endforeach
                </ul>
            </div>
        </a>
    @endif

    @if ($usuario->tieneRol('celador'))
        <a href="{{ route('noche.index') }}" class="mb-6 flex items-center gap-4 rounded-2xl bg-marca-700 p-5 text-white shadow-md hover:bg-marca-800">
            <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-white/15"><x-icono nombre="luna" /></span>
            <span>
                <span class="block text-lg font-semibold">{{ __('inicio.seguridad_noche') }}</span>
                <span class="block text-marca-100">{{ __('inicio.rondas_oxigeno_aireadores') }}</span>
            </span>
        </a>
    @endif

    @if ($alertasBodega->isNotEmpty())
        <a href="{{ route('admin.bodega.index') }}" class="mb-6 flex items-start gap-3 rounded-2xl border border-amber-200 bg-amber-50 p-4 text-amber-900 hover:bg-amber-100">
            <x-icono nombre="bodega" class="mt-0.5" />
            <div>
                <p class="font-semibold">{{ __('inicio.revisa_bodega') }}</p>
                <ul class="text-base">
                    @foreach ($alertasBodega as $alerta)
                        <li>
                            {{ $alerta['stock']->tipo_concentrado }}:
                            @if ($alerta['estado'] === \App\Support\CoberturaBodega::AGOTADO)
                                {{ __('inicio.agotado') }}
                            @else
                                {{ __('inicio.alcanza_para', ['dias' => \App\Support\Formato::numero($alerta['dias'], 1)]) }}
                            @endif
                        </li>
                    @endforeach
                </ul>
            </div>
        </a>
    @endif

    @if ($lagosConAlertaAgua->isNotEmpty())
        <section class="mb-6 rounded-2xl border border-red-200 bg-red-50 p-4 text-red-900" role="alert">
            <p class="mb-1 flex items-center gap-2 font-semibold"><x-icono nombre="alerta" class="h-5 w-5" /> {{ __('inicio.calidad_agua_fuera_rango') }}</p>
            <ul class="space-y-1 text-base">
                @foreach ($lagosConAlertaAgua as $item)
                    <li>
                        <a href="{{ $esTecnico ? route('admin.lagos.show', $item['lago']) : route('noche.index') }}" class="font-semibold underline-offset-2 hover:underline">{{ $item['lago']->nombre }}</a>:
                        {{ $item['alertas'][0] }}@if (count($item['alertas']) > 1) {{ __('inicio.y_mas', ['cantidad' => count($item['alertas']) - 1]) }}@endif
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    @if ($racionesHoy->isNotEmpty())
        <x-tarjeta :titulo="__('inicio.racion_sugerida_hoy')" :subtitulo="__('inicio.segun_biomasa_peso_promedio_cada')" class="mb-6">
            <ul class="divide-y divide-slate-100">
                @foreach ($racionesHoy as $item)
                    <li class="flex items-center justify-between gap-3 py-3">
                        <div class="min-w-0">
                            <p class="font-semibold text-slate-900">{{ __('lagos.lago_nombre', ['lago' => $item['lago']->nombre]) }}</p>
                            <p class="text-sm text-slate-500">
                                {{ __('lagos.tomas_de', ['tomas' => $item['racion']['tomas'], 'kg' => \App\Support\Formato::numero($item['racion']['kg_por_toma'], 1)]) }}
                                @if ($item['racion']['factor'] < 1) · <span class="font-semibold text-amber-700">{{ __('inicio.reducida_agua') }}</span>@endif
                            </p>
                        </div>
                        <p class="shrink-0 text-xl font-bold tabular-nums text-slate-900">
                            {{ \App\Support\Formato::numero($item['racion']['total_kg_dia'], 1) }} <span class="text-sm font-medium text-slate-500">{{ __('inicio.kg_dia') }}</span>
                        </p>
                    </li>
                @endforeach
            </ul>
            <x-boton :href="route('admin.lagos.index')" variante="secundario" class="mt-3 w-full">
                <x-icono nombre="alimentar" class="h-5 w-5" /> {{ __('inicio.ir_alimentar') }}
            </x-boton>
        </x-tarjeta>
    @endif

    @if ($esTecnico)
        @php $numero = fn ($valor, $decimales = 0) => \App\Support\Formato::numero((float) $valor, $decimales); @endphp

        <h2 class="mb-3 text-lg font-semibold text-slate-900">{{ $esPropietario ? __('inicio.panel_ejecutivo') : __('inicio.panel_tecnico') }}</h2>

        {{-- Cifra principal: una sola por vista --}}
        <section class="mb-4 rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200 sm:p-6">
            <p class="text-sm font-medium text-slate-500">{{ __('inicio.biomasa_total_agua') }}</p>
            <p class="mt-1 text-5xl font-semibold tracking-tight text-slate-900">
                {{ $numero($biomasaTotal, 2) }} <span class="text-xl font-medium text-slate-500">kg</span>
            </p>
        </section>

        <div class="mb-6 grid grid-cols-2 gap-4 lg:grid-cols-3">
            <x-indicador :etiqueta="__('inicio.peces_vivos')" :valor="$numero($pecesVivos)" />
            <x-indicador :etiqueta="__('inicio.lagos_siembra')" :valor="$lagosActivos" />
            <x-indicador :etiqueta="__('inicio.mortalidad_30_dias')" :valor="$numero($mortalidad['muertos'])"
                         :unidad="__('inicio.peces_porcentaje', ['porcentaje' => $numero($mortalidad['porcentaje'], 1)])" />
            @if ($esPropietario)
                <x-indicador :etiqueta="__('inicio.alimento_30_dias')" :valor="$numero($alimento30, 1)" unidad="kg" />
                <x-indicador :etiqueta="__('inicio.ventas_mes')" :valor="\App\Support\Formato::dinero($ventasMes)" />
                <x-indicador :etiqueta="__('inicio.cobrar')" :valor="\App\Support\Formato::dinero($cartera)" />
            @endif
        </div>

        <div class="grid items-start gap-4 lg:grid-cols-2">
            <x-grafica-barras :titulo="__('inicio.biomasa_lago')" :subtitulo="__('inicio.kilogramos_cultivo_hoy')" :datos="$biomasaPorLago->all()"
                              unidad="kg" :decimales="1" :vacio="__('inicio.no_hay_lagos_siembras_activas')" />
            <x-grafica-columnas :titulo="__('inicio.mortalidad_diaria')" :subtitulo="__('inicio.peces_muertos_dia_ultimos_30')"
                                :datos="$mortalidadDiaria->all()" :unidad="__('inicio.peces')" />
            @if ($esPropietario)
                <x-grafica-columnas :titulo="__('inicio.alimento_suministrado')" :subtitulo="__('inicio.kilogramos_dia_ultimos_30_dias')"
                                    :datos="$alimentoDiario->all()" unidad="kg" :decimales="1" />
            @endif
        </div>
    @endif
@endsection
