@extends('layouts.app')

@section('titulo', 'Inicio')

@section('contenido')
    <section class="relative mb-6 overflow-hidden rounded-3xl bg-marca-800 text-white shadow-md">
        <img src="/images/estanque.webp" alt="" aria-hidden="true" decoding="async"
             class="absolute inset-0 h-full w-full object-cover opacity-35">
        <div class="absolute inset-0 bg-gradient-to-r from-marca-900/90 to-marca-800/40"></div>

        <div class="relative px-5 py-7 sm:px-8 sm:py-10">
            <p class="text-base text-marca-100">{{ $usuario->tenant->nombre }}</p>
            <h1 class="mt-1 text-2xl font-bold sm:text-3xl">Hola, {{ $usuario->name }}</h1>

            <ul class="mt-4 flex flex-wrap gap-2" aria-label="Tus roles">
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
                        <p class="text-sm font-medium text-marca-700">Estás en turno</p>
                        <p class="text-lg font-semibold text-slate-900">
                            <span class="capitalize">{{ $turnoActual->rol }}</span> hasta las {{ $turnoActual->termina_at->format('H:i') }}
                        </p>
                    @else
                        <p class="text-sm font-medium text-slate-500">Fuera de turno</p>
                        <p class="text-lg font-semibold text-slate-900">
                            @if ($proximoTurno)
                                Próximo turno: <span class="first-letter:uppercase">{{ $proximoTurno->inicia_at->translatedFormat('l j \d\e F, H:i') }}</span>
                                <span class="capitalize">({{ $proximoTurno->rol }})</span>
                            @else
                                No tienes turnos programados.
                            @endif
                        </p>
                    @endif
                </div>
            </div>
        </x-tarjeta>
    @endif

    @if ($novedadesNoche->isNotEmpty())
        <a href="{{ route('noche.index') }}" class="mb-6 flex items-start gap-3 rounded-2xl border border-red-200 bg-red-50 p-4 text-red-900 hover:bg-red-100">
            <x-icono nombre="luna" class="mt-0.5" />
            <div>
                <p class="font-semibold">Novedades de seguridad (últimas 24 horas)</p>
                <ul class="text-base">
                    @foreach ($novedadesNoche as $novedad)
                        <li>
                            {{ $novedad->registrada_at->format('H:i') }} · {{ $novedad->textoTipo() }}@if ($novedad->lago) en {{ $novedad->lago->nombre }}@endif:
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
                <span class="block text-lg font-semibold">Seguridad & Noche</span>
                <span class="block text-marca-100">Rondas, oxígeno y aireadores</span>
            </span>
        </a>
    @endif

    @if ($alertasBodega->isNotEmpty())
        <a href="{{ route('admin.bodega.index') }}" class="mb-6 flex items-start gap-3 rounded-2xl border border-amber-200 bg-amber-50 p-4 text-amber-900 hover:bg-amber-100">
            <x-icono nombre="bodega" class="mt-0.5" />
            <div>
                <p class="font-semibold">Revisa la bodega</p>
                <ul class="text-base">
                    @foreach ($alertasBodega as $alerta)
                        <li>
                            {{ $alerta['stock']->tipo_concentrado }}:
                            @if ($alerta['estado'] === \App\Support\CoberturaBodega::AGOTADO)
                                agotado.
                            @else
                                alcanza para unos {{ number_format($alerta['dias'], 1, ',', '.') }} días.
                            @endif
                        </li>
                    @endforeach
                </ul>
            </div>
        </a>
    @endif

    @if ($usuario->tieneRol('propietario') || $usuario->tieneRol('administrador'))
        <h2 class="mb-3 text-lg font-semibold text-slate-900">Resumen de producción</h2>

        <div class="grid gap-4 sm:grid-cols-3">
            <x-indicador icono="biomasa" etiqueta="Biomasa total" :valor="number_format($biomasaTotal, 2, ',', '.')" unidad="kg" />
            <x-indicador icono="pez" etiqueta="Peces vivos" :valor="number_format($pecesVivos, 0, ',', '.')" />
            <x-indicador icono="lagos" etiqueta="Lagos con siembra" :valor="$lagosActivos" />
        </div>
    @endif
@endsection
