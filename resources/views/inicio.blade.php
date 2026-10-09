@extends('layouts.app')

@section('titulo', __('inicio.inicio'))

@section('contenido')
    {{-- Propietario y administrador manejan datos sensibles: se les pide activar la verificación en dos pasos --}}
    @if (auth()->user()->tieneRol(\App\Models\Rol::PROPIETARIO, \App\Models\Rol::ADMINISTRADOR) && ! auth()->user()->tieneDosPasos())
        <p class="mb-4 flex flex-wrap items-center justify-between gap-3 rounded-2xl bg-gradient-to-r from-amber-50 to-white p-4 text-base text-amber-900 shadow-suave ring-1 ring-amber-200" role="status">
            <span class="flex items-start gap-3">
                <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-amber-100 text-amber-700"><x-icono nombre="alerta" class="h-5 w-5" /></span>
                <span class="pt-1">{{ __('cuenta.dos_pasos.aviso_panel') }}</span>
            </span>
            <a href="{{ route('mi-cuenta.dos-pasos.activar') }}" class="presionable inline-flex min-h-12 items-center rounded-xl bg-amber-400 px-4 font-semibold text-amber-950 shadow-sm hover:bg-amber-300">{{ __('cuenta.dos_pasos.aviso_enlace') }}</a>
        </p>
    @endif

    @if ($primerUso)
        @include('inicio._primer_uso', ['pasos' => $primerUso])
    @endif

    {{-- Saludo: foto del agua de un criadero llena de peces (dominio público, ver docs/marca/LEEME.md) con un velo oscuro para el contraste --}}
    <section class="relative mb-6 overflow-hidden rounded-3xl bg-marca-800 text-white shadow-elevada">
        <img src="/images/fondo-estanque.webp" alt="" aria-hidden="true" decoding="async" class="absolute inset-0 h-full w-full object-cover opacity-40">
        <div class="absolute inset-0 bg-gradient-to-r from-marca-950/90 via-marca-900/70 to-marca-800/30" aria-hidden="true"></div>

        {{-- Ola al pie (dibujo fijo, decorativo) --}}
        <div class="pointer-events-none absolute inset-0 overflow-hidden" aria-hidden="true">
            <svg class="absolute bottom-0 left-0 h-10 w-[200%] text-white/[0.07]" viewBox="0 0 2880 100" preserveAspectRatio="none">
                <path fill="currentColor" d="M0 50 Q360 0 720 50 T1440 50 T2160 50 T2880 50 V100 H0Z"/>
            </svg>
        </div>

        <div class="relative px-5 py-7 sm:px-8 sm:py-10">
            <p class="text-base font-medium text-marca-100">{{ $usuario->tenant->nombre }}</p>
            <h1 class="mt-1 text-3xl font-bold tracking-tight drop-shadow-sm sm:text-4xl">{{ __('inicio.hola', ['nombre' => $usuario->name]) }}</h1>

            <ul class="escalonado mt-4 flex flex-wrap gap-2" aria-label="{{ __('inicio.tus_roles') }}">
                @foreach ($usuario->roles as $rol)
                    <li class="rounded-full bg-white/15 px-3 py-1 text-sm font-semibold capitalize ring-1 ring-white/25 backdrop-blur">{{ $rol->nombre }}</li>
                @endforeach
            </ul>
        </div>
    </section>

    @if ($trabajaPorTurnos)
        <x-tarjeta class="mb-6">
            <div class="flex items-start gap-4">
                <span @class([
                    'relative flex h-12 w-12 shrink-0 items-center justify-center rounded-xl',
                    'bg-gradient-to-br from-marca-100 to-marca-50 text-marca-700 ring-1 ring-marca-200/60' => $turnoActual,
                    'bg-slate-100 text-texto-suave' => ! $turnoActual,
                ])>
                    <x-icono nombre="reloj" />
                    {{-- En turno: punto que late --}}
                    @if ($turnoActual)
                        <span class="absolute -top-1 -right-1 flex h-3.5 w-3.5" aria-hidden="true">
                            <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-marca-400 opacity-60"></span>
                            <span class="relative inline-flex h-3.5 w-3.5 rounded-full bg-marca-500 ring-2 ring-white"></span>
                        </span>
                    @endif
                </span>
                <div>
                    @if ($turnoActual)
                        <p class="text-sm font-medium text-marca-700">{{ __('inicio.estas_turno') }}</p>
                        <p class="text-lg font-semibold text-slate-900">
                            <span class="capitalize">{{ $turnoActual->rol }}</span> {{ __('inicio.hasta_las', ['hora' => $turnoActual->termina_at->format('H:i')]) }}
                        </p>
                    @else
                        <p class="text-sm font-medium text-texto-suave">{{ __('inicio.fuera_turno') }}</p>
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
                            <span class="text-sm text-texto-suave">{{ $evento->textoTipo() }}</span>
                        </a>
                        <span @class([
                            'shrink-0 rounded-full px-2.5 py-0.5 text-sm font-semibold',
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
        <a href="{{ route('mi-trabajo.index') }}" class="elevable presionable group mb-6 flex items-center gap-4 rounded-2xl bg-white p-5 shadow-suave ring-1 ring-slate-200 hover:ring-marca-300">
            <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-marca-100 to-marca-50 text-marca-700 transition duration-300 ease-resorte group-hover:scale-110 group-hover:-rotate-6"><x-icono nombre="tareas" /></span>
            <span class="text-lg font-semibold text-slate-900">{{ __('inicio.tareas_pendientes', ['cantidad' => $tareasPendientes]) }}</span>
        </a>
    @endif

    @if ($solicitudesPendientes > 0)
        <a href="{{ route('admin.solicitudes.index') }}" class="elevable presionable group mb-6 flex items-center gap-4 rounded-2xl bg-white p-5 shadow-suave ring-1 ring-slate-200 hover:ring-amber-300">
            <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-amber-100 to-amber-50 text-amber-700 transition duration-300 ease-resorte group-hover:scale-110 group-hover:-rotate-6"><x-icono nombre="personal" /></span>
            <span class="text-lg font-semibold text-slate-900">{{ __('inicio.solicitudes_pendientes', ['cantidad' => $solicitudesPendientes]) }}</span>
        </a>
    @endif

    @if ($novedadesNoche->isNotEmpty())
        <a href="{{ route('noche.index') }}" class="elevable mb-6 flex items-start gap-3 rounded-2xl border border-red-200 bg-gradient-to-r from-red-50 to-white p-4 text-red-900 shadow-suave hover:border-red-300">
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
        <a href="{{ route('noche.index') }}" class="elevable presionable group relative mb-6 flex items-center gap-4 overflow-hidden rounded-2xl bg-gradient-to-br from-slate-900 via-marca-900 to-marca-700 p-5 text-white shadow-elevada">
            <span class="pointer-events-none absolute -top-8 right-6 h-24 w-24 rounded-full bg-marca-300/20 blur-2xl" aria-hidden="true"></span>
            <span class="relative flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-white/15 ring-1 ring-white/20 transition duration-500 ease-resorte group-hover:-rotate-12"><x-icono nombre="luna" /></span>
            <span class="relative">
                <span class="block text-lg font-semibold">{{ __('inicio.seguridad_noche') }}</span>
                <span class="block text-marca-100">{{ __('inicio.rondas_oxigeno_aireadores') }}</span>
            </span>
        </a>
    @endif

    @if ($avisosPesca->isNotEmpty())
        <x-tarjeta :titulo="__('inicio.listos_para_pescar', ['cantidad' => $avisosPesca->count()])" class="mb-6">
            <ul class="divide-y divide-slate-100">
                @foreach ($avisosPesca as $aviso)
                    <li class="flex flex-wrap items-center justify-between gap-3 py-2.5">
                        <div class="min-w-0">
                            <a href="{{ route('admin.lagos.show', $aviso->lago) }}" class="font-semibold text-slate-900 hover:underline">{{ __('inicio.aviso_pesca', ['lago' => $aviso->lago->nombre, 'especie' => $aviso->siembra->nombre_especie]) }}</a>
                            <p class="text-sm text-texto-suave">{{ __('inicio.aviso_pesca_reportado', ['nombre' => $aviso->usuario->name, 'momento' => $aviso->reportado_at->format('d/m H:i')]) }}@if ($aviso->nota) · {{ $aviso->nota }}@endif</p>
                        </div>
                        <x-formulario :action="route('admin.avisos-pesca.atender', $aviso)" class="!space-y-0">
                            <x-boton variante="secundario" class="!min-h-12 !py-2">{{ __('inicio.marcar_atendido') }}</x-boton>
                        </x-formulario>
                    </li>
                @endforeach
            </ul>
        </x-tarjeta>
    @endif

    @if ($alertas->isNotEmpty())
        <section data-revelar class="mb-6 rounded-2xl border-l-4 border-red-500 bg-white p-4 shadow-suave ring-1 ring-red-200" role="alert" aria-labelledby="alertas-titulo">
            <div class="mb-2 flex items-center justify-between gap-3">
                <h2 id="alertas-titulo" class="flex items-center gap-2 text-lg font-semibold text-red-800">
                    <x-icono nombre="alerta" class="h-5 w-5 animate-latido" /> {{ __('alertas.inicio.titulo', ['cantidad' => $alertas->count()]) }}
                </h2>
                <a href="{{ route('admin.alertas.index') }}" class="inline-flex min-h-12 shrink-0 items-center text-base font-medium text-marca-700 hover:underline">{{ __('alertas.inicio.historial') }}</a>
            </div>
            <ul class="divide-y divide-slate-100">
                @foreach ($alertas as $alerta)
                    <li class="flex items-start gap-3 py-2.5">
                        <span @class([
                            'mt-0.5 shrink-0 rounded-full px-2.5 py-0.5 text-sm font-semibold',
                            'bg-red-100 text-red-800' => $alerta->esCritica(),
                            'bg-amber-100 text-amber-900' => ! $alerta->esCritica(),
                        ])>{{ __('alertas.severidades.'.$alerta->severidad) }}</span>
                        <div class="min-w-0 flex-1 text-base text-slate-800">
                            @if ($enlace = $alerta->enlace())
                                <a href="{{ $enlace }}" class="hover:underline">{{ $alerta->mensaje() }}</a>
                            @else
                                {{ $alerta->mensaje() }}
                            @endif
                            <p class="text-sm text-texto-suave">{{ __('alertas.desde', ['momento' => $alerta->abierta_at->format('d/m H:i')]) }}</p>
                        </div>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    @if ($lagosConAlertaAgua->isNotEmpty())
        <section data-revelar class="mb-6 rounded-2xl border border-red-200 border-l-4 border-l-red-500 bg-gradient-to-r from-red-50 to-white p-4 text-red-900 shadow-suave" role="alert">
            <p class="mb-1 flex items-center gap-2 font-semibold"><x-icono nombre="alerta" class="h-5 w-5" /> {{ __('inicio.calidad_agua_fuera_rango') }}</p>
            <ul class="space-y-1 text-base">
                @foreach ($lagosConAlertaAgua as $item)
                    <li>
                        <a href="{{ route('noche.index') }}" class="font-semibold underline-offset-2 hover:underline">{{ $item['lago']->nombre }}</a>:
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
                            <p class="text-sm text-texto-suave">
                                {{ __('lagos.tomas_de', ['tomas' => $item['racion']['tomas'], 'kg' => \App\Support\Formato::numero($item['racion']['kg_por_toma'], 1)]) }}
                                @if ($item['racion']['factor'] < 1) · <span class="font-semibold text-amber-700">{{ __('inicio.reducida_agua') }}</span>@endif
                            </p>
                        </div>
                        <p class="shrink-0 text-xl font-bold tabular-nums text-slate-900">
                            <span data-contar>{{ \App\Support\Formato::numero($item['racion']['total_kg_dia'], 1) }}</span> <span class="text-sm font-medium text-texto-suave">{{ __('inicio.kg_dia') }}</span>
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

        <h2 class="mb-3 flex items-center gap-2 text-lg font-semibold tracking-tight text-slate-900">
            <span class="h-5 w-1 rounded-full bg-gradient-to-b from-marca-400 to-marca-600" aria-hidden="true"></span>
            {{ $esPropietario ? __('inicio.panel_ejecutivo') : __('inicio.panel_tecnico') }}
        </h2>

        {{-- Cifra principal: una sola por vista --}}
        <section data-revelar class="relative mb-4 overflow-hidden rounded-2xl bg-white p-5 shadow-suave ring-1 ring-slate-200 sm:p-6">
            <span class="pointer-events-none absolute -top-16 -right-10 h-48 w-48 rounded-full bg-gradient-to-br from-marca-100 to-sky-100 opacity-70 blur-3xl" aria-hidden="true"></span>
            <svg class="pointer-events-none absolute bottom-0 left-0 h-12 w-[200%] text-marca-100/70" viewBox="0 0 2880 100" preserveAspectRatio="none" aria-hidden="true">
                <path fill="currentColor" d="M0 50 Q360 0 720 50 T1440 50 T2160 50 T2880 50 V100 H0Z"/>
            </svg>
            <div class="relative flex items-center gap-4">
                <span class="hidden h-14 w-14 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br from-marca-500 to-marca-700 text-white shadow-brillo sm:flex">
                    <x-icono nombre="biomasa" class="h-7 w-7" />
                </span>
                <div>
                    <p class="text-sm font-medium text-texto-suave">{{ __('inicio.biomasa_total_agua') }}</p>
                    <p class="mt-1 text-5xl font-semibold tracking-tight tabular-nums">
                        <span data-contar class="texto-degradado">{{ $numero($biomasaTotal, 2) }}</span> <span class="text-xl font-medium text-texto-suave">kg</span>
                    </p>
                </div>
            </div>
        </section>

        <div class="escalonado mb-6 grid grid-cols-2 gap-4 lg:grid-cols-3">
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
