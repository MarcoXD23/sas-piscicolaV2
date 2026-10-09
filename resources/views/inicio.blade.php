@extends('layouts.app')

@section('titulo', __('inicio.inicio'))

{{--
    Inicio: la hoja del día de la libreta. Arriba lo urgente (alertas, agua, novedades), luego el turno y lo pendiente,
    y para propietario y administrador el agua de cada lago y la producción como un libro de cuentas.
    Las pestañas de estado (ficha de archivo) llevan siempre ícono y texto, nunca solo color.
--}}
@php
    $numero = fn ($valor, $decimales = 0) => \App\Support\Formato::numero((float) $valor, $decimales);
@endphp

@section('contenido')
    {{-- Propietario y administrador manejan datos sensibles: se les pide activar la verificación en dos pasos --}}
    @if (auth()->user()->tieneRol(\App\Models\Rol::PROPIETARIO, \App\Models\Rol::ADMINISTRADOR) && ! auth()->user()->tieneDosPasos())
        <x-aviso tono="atencion" class="mb-6">
            {{ __('cuenta.dos_pasos.aviso_panel') }}
            <x-slot:accion>
                <a href="{{ route('mi-cuenta.dos-pasos.activar') }}" class="inline-flex min-h-12 items-center rounded-ficha bg-tinta px-4 font-semibold text-white transition-colors hover:bg-marca-900">{{ __('cuenta.dos_pasos.aviso_enlace') }}</a>
            </x-slot:accion>
        </x-aviso>
    @endif

    {{-- Encabezado de la hoja: foto del agua de un criadero (dominio público, ver docs/marca/LEEME.md) bajo un velo de tinta --}}
    <header class="relative mb-8 overflow-hidden rounded-ficha bg-tinta text-white shadow-ficha">
        <img src="/images/fondo-estanque.webp" alt="" aria-hidden="true" decoding="async" class="absolute inset-0 h-full w-full object-cover opacity-35">
        <div class="absolute inset-0 bg-tinta/55" aria-hidden="true"></div>

        <div class="relative flex flex-wrap items-end justify-between gap-x-6 gap-y-3 px-5 py-6 sm:px-7">
            <div class="min-w-0">
                <h1 class="text-2xl font-bold text-balance sm:text-3xl">{{ __('inicio.hola', ['nombre' => $usuario->name]) }}</h1>
                <p class="mt-1 text-base text-white/85 first-letter:uppercase">{{ \App\Support\Region::ahora()->translatedFormat(__('comun.formatos.dia_mes')) }}</p>
            </div>
            <ul class="flex flex-wrap gap-2" aria-label="{{ __('inicio.tus_roles') }}">
                @foreach ($usuario->roles as $rol)
                    <li class="rounded-md bg-white/15 px-2.5 py-1 text-sm font-semibold capitalize ring-1 ring-white/25">{{ $rol->nombre }}</li>
                @endforeach
            </ul>
        </div>
    </header>

    {{-- ── Lo urgente ─────────────────────────────────────────────────────────────── --}}

    @if ($alertas->isNotEmpty())
        <section class="relative mb-8 rounded-ficha bg-ficha px-5 pt-6 pb-2 shadow-ficha ring-1 ring-alerta-borde" role="alert" aria-labelledby="alertas-titulo">
            <x-insignia tono="alerta" pestana latido como="h2" id="alertas-titulo">{{ __('alertas.inicio.titulo', ['cantidad' => $alertas->count()]) }}</x-insignia>
            <ul class="divide-y divide-renglon">
                @foreach ($alertas as $alerta)
                    <li class="flex items-start gap-3 py-3">
                        <x-insignia :tono="$alerta->esCritica() ? 'alerta' : 'atencion'" class="mt-0.5 shrink-0">{{ __('alertas.severidades.'.$alerta->severidad) }}</x-insignia>
                        <div class="min-w-0 flex-1 text-base">
                            @if ($enlace = $alerta->enlace())
                                <a href="{{ $enlace }}" class="font-semibold underline decoration-renglon-fuerte underline-offset-4 hover:decoration-tinta">{{ $alerta->mensaje() }}</a>
                            @else
                                <span class="font-semibold">{{ $alerta->mensaje() }}</span>
                            @endif
                            <p class="text-sm text-tinta-suave">{{ __('alertas.desde', ['momento' => $alerta->abierta_at->format('d/m H:i')]) }}</p>
                        </div>
                    </li>
                @endforeach
            </ul>
            <a href="{{ route('admin.alertas.index') }}" class="-mx-5 flex min-h-12 items-center justify-center border-t border-renglon text-base font-semibold text-marca-700 hover:bg-papel-hondo">{{ __('alertas.inicio.historial') }}</a>
        </section>
    @endif

    {{-- Celador: lagos con el agua fuera de rango en las últimas 24 horas --}}
    @if ($lagosConAlertaAgua->isNotEmpty())
        <section class="relative mb-8 rounded-ficha bg-ficha px-5 pt-6 pb-3 shadow-ficha ring-1 ring-alerta-borde" role="alert" aria-labelledby="agua-celador-titulo">
            <x-insignia tono="alerta" pestana como="h2" id="agua-celador-titulo">{{ __('inicio.calidad_agua_fuera_rango') }}</x-insignia>
            <ul class="divide-y divide-renglon">
                @foreach ($lagosConAlertaAgua as $item)
                    <li class="py-2.5 text-base">
                        <a href="{{ route('noche.index') }}" class="font-bold underline decoration-renglon-fuerte underline-offset-4 hover:decoration-tinta">{{ $item['lago']->nombre }}</a>:
                        {{ $item['alertas'][0] }}@if (count($item['alertas']) > 1) {{ __('inicio.y_mas', ['cantidad' => count($item['alertas']) - 1]) }}@endif
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    @if ($novedadesNoche->isNotEmpty())
        <section class="relative mb-8 rounded-ficha bg-ficha px-5 pt-6 pb-3 shadow-ficha ring-1 ring-atencion-borde" aria-labelledby="novedades-titulo">
            <x-insignia tono="atencion" icono="luna" pestana como="h2" id="novedades-titulo">{{ __('inicio.novedades_seguridad_ultimas_24_horas') }}</x-insignia>
            <ul class="divide-y divide-renglon">
                @foreach ($novedadesNoche as $novedad)
                    <li class="flex gap-3 py-2.5 text-base">
                        <span class="shrink-0 font-bold tabular-nums">{{ $novedad->registrada_at->format('H:i') }}</span>
                        <span class="min-w-0">
                            <span class="font-semibold">{{ $novedad->textoTipo() }}@if ($novedad->lago) {{ __('inicio.en_lago', ['lago' => $novedad->lago->nombre]) }}@endif:</span>
                            {{ $novedad->descripcion }} <span class="text-tinta-suave">({{ $novedad->usuario?->name }})</span>
                        </span>
                    </li>
                @endforeach
            </ul>
            <a href="{{ route('noche.index') }}" class="-mx-5 -mb-3 flex min-h-12 items-center justify-center border-t border-renglon text-base font-semibold text-marca-700 hover:bg-papel-hondo">{{ __('inicio.seguridad_noche') }}</a>
        </section>
    @endif

    {{-- «Listo para pescar»: el único lugar donde aparece el color de la trucha (algo que se celebra, no un estado) --}}
    @if ($avisosPesca->isNotEmpty())
        <section class="relative mb-8 rounded-ficha bg-ficha px-5 pt-6 pb-2 shadow-ficha ring-1 ring-trucha/30" aria-labelledby="pesca-titulo">
            <x-insignia tono="trucha" pestana como="h2" id="pesca-titulo">{{ __('inicio.listos_para_pescar', ['cantidad' => $avisosPesca->count()]) }}</x-insignia>
            <ul class="divide-y divide-renglon">
                @foreach ($avisosPesca as $aviso)
                    <li class="flex flex-wrap items-center justify-between gap-3 py-3">
                        <div class="min-w-0">
                            <a href="{{ route('admin.lagos.show', $aviso->lago) }}" class="font-bold underline decoration-renglon-fuerte underline-offset-4 hover:decoration-tinta">{{ __('inicio.aviso_pesca', ['lago' => $aviso->lago->nombre, 'especie' => $aviso->siembra->nombre_especie]) }}</a>
                            <p class="text-sm text-tinta-suave">{{ __('inicio.aviso_pesca_reportado', ['nombre' => $aviso->usuario->name, 'momento' => $aviso->reportado_at->format('d/m H:i')]) }}@if ($aviso->nota) · {{ $aviso->nota }}@endif</p>
                        </div>
                        <x-formulario :action="route('admin.avisos-pesca.atender', $aviso)" class="!space-y-0">
                            <x-boton variante="secundario">{{ __('inicio.marcar_atendido') }}</x-boton>
                        </x-formulario>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    @if ($primerUso)
        @include('inicio._primer_uso', ['pasos' => $primerUso])
    @endif

    {{-- ── El turno y lo pendiente ────────────────────────────────────────────────── --}}

    @if ($trabajaPorTurnos)
        <section @class([
            'relative mb-8 flex items-center gap-4 rounded-ficha bg-ficha px-5 pt-7 pb-5 shadow-ficha ring-1',
            'ring-bien-borde' => $turnoActual,
            'ring-renglon' => ! $turnoActual,
        ]) aria-labelledby="turno-titulo">
            @if ($turnoActual)
                <x-insignia tono="bien" icono="" pestana como="h2" id="turno-titulo">
                    {{-- En turno: un punto que late (es un estado vivo, no adorno) --}}
                    <span class="relative mr-1 inline-flex h-2.5 w-2.5 align-middle" aria-hidden="true">
                        <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-bien opacity-50"></span>
                        <span class="relative inline-flex h-2.5 w-2.5 rounded-full bg-bien"></span>
                    </span>
                    {{ __('inicio.estas_turno') }}
                </x-insignia>
            @else
                <x-insignia tono="neutro" icono="reloj" pestana como="h2" id="turno-titulo">{{ __('inicio.fuera_turno') }}</x-insignia>
            @endif
            <x-icono nombre="reloj" @class(['h-8 w-8', 'text-bien' => $turnoActual, 'text-tinta-suave' => ! $turnoActual]) />
            <p class="text-xl font-bold">
                @if ($turnoActual)
                    <span class="capitalize">{{ $turnoActual->rol }}</span> {{ __('inicio.hasta_las', ['hora' => $turnoActual->termina_at->format('H:i')]) }}
                @elseif ($proximoTurno)
                    {{ __('inicio.proximo_turno') }} <span class="first-letter:uppercase">{{ $proximoTurno->inicia_at->translatedFormat(__('comun.formatos.dia_mes_hora')) }}</span>
                    <span class="capitalize">({{ $proximoTurno->rol }})</span>
                @else
                    {{ __('inicio.sin_turnos') }}
                @endif
            </p>
        </section>
    @endif

    @if ($tareasPendientes > 0 || $solicitudesPendientes > 0)
        <nav class="mb-8 divide-y divide-renglon overflow-hidden rounded-ficha bg-ficha shadow-ficha ring-1 ring-renglon" aria-label="{{ __('inicio.urgente') }}">
            @if ($tareasPendientes > 0)
                <a href="{{ route('mi-trabajo.index') }}" class="flex min-h-16 items-center gap-4 px-5 py-3 text-lg font-semibold transition-colors hover:bg-papel-hondo">
                    <x-icono nombre="tareas" class="text-marca-700" />
                    {{ __('inicio.tareas_pendientes', ['cantidad' => $tareasPendientes]) }}
                </a>
            @endif
            @if ($solicitudesPendientes > 0)
                <a href="{{ route('admin.solicitudes.index') }}" class="flex min-h-16 items-center gap-4 px-5 py-3 text-lg font-semibold transition-colors hover:bg-papel-hondo">
                    <x-icono nombre="personal" class="text-atencion" />
                    {{ __('inicio.solicitudes_pendientes', ['cantidad' => $solicitudesPendientes]) }}
                </a>
            @endif
        </nav>
    @endif

    {{-- Trabajador: ración sugerida del día, como renglones de la libreta --}}
    @if ($racionesHoy->isNotEmpty())
        <x-tarjeta :titulo="__('inicio.racion_sugerida_hoy')" :subtitulo="__('inicio.segun_biomasa_peso_promedio_cada')" class="mb-8">
            <ul class="-mt-1 divide-y divide-renglon">
                @foreach ($racionesHoy as $item)
                    <li class="flex items-center justify-between gap-3 py-3">
                        <div class="min-w-0">
                            <p class="font-bold">{{ __('lagos.lago_nombre', ['lago' => $item['lago']->nombre]) }}</p>
                            <p class="text-sm text-tinta-suave">
                                {{ __('lagos.tomas_de', ['tomas' => $item['racion']['tomas'], 'kg' => $numero($item['racion']['kg_por_toma'], 1)]) }}
                                @if ($item['racion']['factor'] < 1) · <span class="font-bold text-atencion">{{ __('inicio.reducida_agua') }}</span>@endif
                            </p>
                        </div>
                        <p class="shrink-0 text-2xl font-bold tabular-nums">
                            {{ $numero($item['racion']['total_kg_dia'], 1) }} <span class="text-sm font-normal text-tinta-suave">{{ __('inicio.kg_dia') }}</span>
                        </p>
                    </li>
                @endforeach
            </ul>
            <x-boton :href="route('admin.lagos.index')" class="mt-4 w-full">
                <x-icono nombre="alimentar" class="h-5 w-5" /> {{ __('inicio.ir_alimentar') }}
            </x-boton>
        </x-tarjeta>
    @endif

    {{-- Celador: la entrada a Seguridad & Noche (su pantalla es oscura; esta ficha la anticipa) --}}
    @if ($usuario->tieneRol('celador'))
        <a href="{{ route('noche.index') }}" class="mb-8 flex min-h-20 items-center gap-4 rounded-ficha bg-tinta px-5 py-4 text-white shadow-ficha transition-colors hover:bg-marca-950">
            <x-icono nombre="luna" class="h-7 w-7 text-marca-300" />
            <span>
                <span class="block text-lg font-bold">{{ __('inicio.seguridad_noche') }}</span>
                <span class="block text-white/80">{{ __('inicio.rondas_oxigeno_aireadores') }}</span>
            </span>
        </a>
    @endif

    @if ($recordatorios->isNotEmpty())
        <x-tarjeta :titulo="__('inicio.agenda_no_olvides')" class="mb-8">
            <ul class="-mt-1 divide-y divide-renglon">
                @foreach ($recordatorios as $evento)
                    <li class="flex items-center justify-between gap-3 py-2.5">
                        <a href="{{ route('admin.agenda.index', ['mes' => $evento->inicia_at->format('Y-m')]) }}" class="min-w-0 hover:underline">
                            <span class="block truncate font-semibold">{{ $evento->titulo }}@if ($evento->lago) · {{ $evento->lago->nombre }}@endif</span>
                            <span class="text-sm text-tinta-suave">{{ $evento->textoTipo() }}</span>
                        </a>
                        <x-insignia class="shrink-0" icono="calendario"
                                    :tono="$evento->estaVencido() ? 'alerta' : ($evento->inicia_at->isToday() ? 'bien' : 'neutro')">
                            {{ $evento->estaVencido() ? __('inicio.vencido', ['fecha' => $evento->inicia_at->format('d/m')]) : ($evento->inicia_at->isToday() ? __('comun.hoy') : $evento->inicia_at->format('d/m')) }}
                        </x-insignia>
                    </li>
                @endforeach
            </ul>
        </x-tarjeta>
    @endif

    {{-- ── Panel de propietario y administrador ───────────────────────────────────── --}}

    @if ($esTecnico)
        <h2 class="mb-4 text-xl font-bold">{{ $esPropietario ? __('inicio.panel_ejecutivo') : __('inicio.panel_tecnico') }}</h2>

        {{-- Agua de los lagos: la lectura del día, en cifras grandes. Oxígeno fuera de rango = alerta (como EvaluadorAlertas); otro problema = atención. --}}
        @if ($aguaLagos->isNotEmpty())
            <section class="mb-6 overflow-hidden rounded-ficha bg-ficha shadow-ficha ring-1 ring-renglon" aria-labelledby="agua-titulo">
                <header class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1 border-b border-renglon px-5 py-4 sm:px-6">
                    <h3 id="agua-titulo" class="text-lg font-bold">{{ __('inicio.agua.titulo') }}</h3>
                    <p class="text-sm text-tinta-suave">{{ __('inicio.agua.subtitulo', ['horas' => \App\Support\CalculadoraRacion::HORAS_MEDICION_VIGENTE]) }}</p>
                </header>

                <ul class="divide-y divide-renglon">
                    @foreach ($aguaLagos as $item)
                        @php
                            $medicion = $item['medicion'];
                            $parametros = array_column($item['problemas'], 'parametro');
                            $estado = ! $medicion ? 'sin_medicion' : (in_array('oxigeno', $parametros, true) ? 'alerta' : ($parametros ? 'atencion' : 'bien'));
                            $tonoEstado = ['sin_medicion' => 'info'][$estado] ?? $estado;
                        @endphp
                        <li class="grid grid-cols-2 gap-x-4 gap-y-3 px-5 py-4 sm:grid-cols-[minmax(0,1.4fr)_minmax(0,1fr)_minmax(0,1fr)_8.5rem] sm:items-center sm:px-6">
                            <div class="col-span-2 flex items-start justify-between gap-3 sm:col-span-1">
                                <div class="min-w-0">
                                    <a href="{{ route('admin.lagos.show', $item['lago']) }}" class="text-lg font-bold underline decoration-transparent underline-offset-4 hover:decoration-tinta">{{ $item['lago']->nombre }}</a>
                                    <p class="truncate text-sm text-tinta-suave">
                                        {{ $item['lago']->siembrasActivas->pluck('nombre_especie')->join(', ') }}
                                    </p>
                                    @if ($medicion)
                                        <p class="text-sm text-tinta-suave">{{ __('inicio.agua.medido', ['hora' => $medicion->medido_at->format('H:i')]) }}</p>
                                    @endif
                                </div>
                                <span class="sm:hidden"><x-insignia :tono="$tonoEstado">{{ __('inicio.estado_agua.'.$estado) }}</x-insignia></span>
                            </div>

                            @if ($medicion)
                                @foreach ([['oxigeno', $medicion->oxigeno_mg_l, 'mg/L', 'alerta'], ['temperatura', $medicion->temperatura_c, '°C', 'atencion']] as [$parametro, $valor, $unidad, $tonoProblema])
                                    <div>
                                        <p class="text-sm text-tinta-suave">{{ __('inicio.agua.'.$parametro) }}</p>
                                        @if ($valor !== null)
                                            <p @class([
                                                'mt-1 text-cifra font-bold tabular-nums',
                                                'text-alerta' => in_array($parametro, $parametros, true) && $tonoProblema === 'alerta',
                                                'text-atencion' => in_array($parametro, $parametros, true) && $tonoProblema === 'atencion',
                                            ])>{{ $numero($valor, 1) }} <span class="text-base font-normal tracking-normal text-tinta-suave">{{ $unidad }}</span></p>
                                        @else
                                            <p class="mt-1 text-lg text-tinta-suave">{{ __('inicio.agua.sin_dato') }}</p>
                                        @endif
                                    </div>
                                @endforeach
                            @else
                                <p class="col-span-2 flex items-center gap-2 text-base text-tinta-suave">
                                    <x-icono nombre="reloj" class="h-5 w-5" /> {{ __('inicio.agua.sin_medicion') }}
                                </p>
                            @endif

                            <span class="hidden justify-self-end sm:block"><x-insignia :tono="$tonoEstado">{{ __('inicio.estado_agua.'.$estado) }}</x-insignia></span>

                            @if ($item['problemas'])
                                <p @class(['col-span-2 text-sm font-semibold sm:col-span-4', 'text-alerta' => $estado === 'alerta', 'text-atencion' => $estado === 'atencion'])>
                                    {{ $item['problemas'][0]['texto'] }}@if (count($item['problemas']) > 1) {{ __('inicio.y_mas', ['cantidad' => count($item['problemas']) - 1]) }}@endif
                                </p>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif

        {{-- Producción: la biomasa manda; el resto son renglones de un libro de cuentas --}}
        <section class="mb-6 overflow-hidden rounded-ficha bg-ficha shadow-ficha ring-1 ring-renglon" aria-labelledby="produccion-titulo">
            <div class="flex flex-wrap items-end justify-between gap-x-8 gap-y-3 border-b border-renglon px-5 py-5 sm:px-6">
                <div>
                    <h3 id="produccion-titulo" class="text-lg font-bold">{{ __('inicio.produccion') }}</h3>
                    <p class="mt-4 text-sm text-tinta-suave">{{ __('inicio.biomasa_total_agua') }}</p>
                </div>
                <p class="text-cifra-grande font-bold tabular-nums">
                    {{ $numero($biomasaTotal, 2) }} <span class="text-xl font-normal tracking-normal text-tinta-suave">kg</span>
                </p>
            </div>
            {{-- Renglones del libro de cuentas: una columna en celular, dos en pantallas anchas --}}
            <dl class="grid px-5 sm:grid-cols-2 sm:gap-x-10 sm:px-6 [&>div]:border-b [&>div]:border-renglon [&>div:last-child]:border-b-0 sm:[&>div:nth-last-child(2):nth-child(odd)]:border-b-0">
                @foreach (array_filter([
                    [__('inicio.peces_vivos'), $numero($pecesVivos), null],
                    [__('inicio.lagos_siembra'), $lagosActivos, null],
                    [__('inicio.mortalidad_30_dias'), $numero($mortalidad['muertos']), __('inicio.peces_porcentaje', ['porcentaje' => $numero($mortalidad['porcentaje'], 1)])],
                    $esPropietario ? [__('inicio.alimento_30_dias'), $numero($alimento30, 1), 'kg'] : null,
                    $esPropietario ? [__('inicio.ventas_mes'), \App\Support\Formato::dinero($ventasMes), null] : null,
                    $esPropietario ? [__('inicio.cobrar'), \App\Support\Formato::dinero($cartera), null] : null,
                ]) as [$etiqueta, $valor, $unidad])
                    <div class="flex items-baseline justify-between gap-4 py-3.5">
                        <dt class="text-tinta-suave">{{ $etiqueta }}</dt>
                        <dd class="text-right text-xl font-bold tabular-nums">
                            {{ $valor }}@if ($unidad) <span class="text-sm font-normal text-tinta-suave">{{ $unidad }}</span>@endif
                        </dd>
                    </div>
                @endforeach
            </dl>
        </section>

        <div class="grid items-start gap-6 lg:grid-cols-2">
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
