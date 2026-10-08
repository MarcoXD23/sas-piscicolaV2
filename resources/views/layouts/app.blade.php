<!DOCTYPE html>
<html lang="{{ \App\Support\Region::etiquetaIdioma() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#0f655f">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@hasSection('titulo')@yield('titulo') · @endif{{ config('app.name') }}</title>
    <link rel="icon" href="/favicon.ico" sizes="16x16 32x32 48x48">
    <link rel="icon" href="/icons/icon-192.png" type="image/png" sizes="192x192">
    <link rel="apple-touch-icon" href="/icons/apple-touch-icon.png" sizes="180x180">
    <link rel="manifest" href="/manifest.webmanifest">
    <script>window.textos = @json(__('js'));</script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen">
    @php
        // El panel de plataforma usa su propio ingreso (guard «plataforma») y no muestra el menú de las fincas
        $rutaPlataforma = request()->routeIs('plataforma.*');
        $enFinca = auth()->check() && ! $rutaPlataforma;
        $enPlataforma = $rutaPlataforma && auth('plataforma')->check();
        $conSesion = $enFinca || $enPlataforma;
        $conMenuInferior = false;
    @endphp

    {{-- Sin conexión: la página puede ser una copia guardada (solo consulta). Lleva la hora en que se generó. --}}
    <div data-sin-conexion hidden role="status" class="sticky top-0 z-40 bg-amber-400 px-4 py-2 text-center text-sm font-semibold text-amber-950 print:hidden">
        {{ __('layout.sin_conexion', ['momento' => \App\Support\Region::ahora()->format('d/m H:i')]) }}
    </div>

    <a href="#contenido" class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-50 focus:rounded-lg focus:bg-white focus:px-4 focus:py-2 focus:text-marca-800 focus:shadow">
        {{ __('layout.saltar_contenido') }}
    </a>

    @unless ($conSesion)
        <div class="fixed inset-0 -z-10" aria-hidden="true">
            <img src="/images/login-fondo.webp" alt="" class="h-full w-full object-cover" decoding="async">
            <div class="absolute inset-0 bg-gradient-to-b from-marca-950/80 via-marca-900/70 to-marca-950/90"></div>
        </div>
    @endunless

    @if ($enPlataforma)
        <header class="sticky top-0 z-30 print:hidden bg-slate-800 text-white shadow-md pt-[env(safe-area-inset-top)]">
            <div class="mx-auto flex max-w-5xl items-center justify-between gap-3 px-4 py-3">
                <a href="{{ route('plataforma.fincas.index') }}" class="flex min-w-0 items-center gap-3">
                    <x-logo class="h-10 w-10 shrink-0 rounded-xl ring-1 ring-white/20" />
                    <span class="min-w-0 leading-tight">
                        <span class="block truncate text-base font-bold">{{ config('app.name') }}</span>
                        <span class="block truncate text-sm text-slate-300">{{ __('plataforma.panel') }}</span>
                    </span>
                </a>

                <form method="POST" action="{{ route('plataforma.logout') }}" class="shrink-0">
                    @csrf
                    <button type="submit" class="flex min-h-11 items-center gap-2 rounded-xl px-3 text-base font-medium text-slate-100 hover:bg-slate-700">
                        <x-icono nombre="salir" class="h-5 w-5" />
                        <span>{{ __('layout.salir') }}</span>
                    </button>
                </form>
            </div>
        </header>
    @endif

    @if ($enFinca)
        @php
            $usuarioActual = auth()->user()->loadMissing('roles', 'tenant');
            $enlaces = collect([
                ['ruta' => 'inicio', 'texto' => __('layout.menu.inicio'), 'icono' => 'inicio', 'roles' => null],
                ['ruta' => 'admin.lagos.index', 'activa' => ['admin.lagos.*', 'admin.cosechas.*'], 'texto' => __('layout.menu.lagos'), 'icono' => 'lagos', 'roles' => ['propietario', 'administrador', 'trabajador']],
                ['ruta' => 'admin.bodega.index', 'texto' => __('layout.menu.bodega'), 'icono' => 'bodega', 'roles' => ['propietario', 'administrador']],
                ['ruta' => 'admin.ventas.index', 'activa' => 'admin.ventas.*', 'texto' => __('layout.menu.ventas'), 'icono' => 'dinero', 'roles' => ['propietario', 'administrador']],
                ['ruta' => 'admin.agenda.index', 'activa' => 'admin.agenda.*', 'texto' => __('layout.menu.agenda'), 'icono' => 'calendario', 'roles' => ['propietario']],
                ['ruta' => 'admin.personal.index', 'activa' => ['admin.personal.*', 'admin.turnos.*', 'admin.solicitudes.*', 'admin.nomina.*'], 'texto' => __('layout.menu.personal'), 'icono' => 'personal', 'roles' => ['propietario']],
                ['ruta' => 'noche.index', 'activa' => 'noche.*', 'texto' => __('layout.menu.noche'), 'icono' => 'luna', 'roles' => ['propietario', 'administrador', 'celador']],
                ['ruta' => 'admin.tareas.index', 'activa' => 'admin.tareas.*', 'texto' => __('layout.menu.tareas'), 'icono' => 'tareas', 'roles' => ['administrador']],
                ['ruta' => 'admin.alertas.index', 'activa' => 'admin.alertas.*', 'texto' => __('layout.menu.alertas'), 'icono' => 'alerta', 'roles' => ['propietario', 'administrador']],
                ['ruta' => 'admin.conversion.index', 'activa' => 'admin.conversion.*', 'texto' => __('layout.menu.conversion'), 'icono' => 'biomasa', 'roles' => ['propietario', 'administrador']],
                ['ruta' => 'admin.bitacora.index', 'activa' => 'admin.bitacora.*', 'texto' => __('layout.menu.bitacora'), 'icono' => 'reloj', 'roles' => ['propietario']],
                ['ruta' => 'admin.reportes.index', 'activa' => 'admin.reportes.*', 'texto' => __('layout.menu.reportes'), 'icono' => 'reporte', 'roles' => ['propietario', 'administrador']],
                ['ruta' => 'admin.especies.index', 'activa' => 'admin.especies.*', 'texto' => __('especies.catalogo_finca.enlace'), 'icono' => 'pez', 'roles' => ['propietario', 'administrador']],
                ['ruta' => 'admin.ajustes-pesaje.edit', 'activa' => 'admin.ajustes-pesaje.*', 'texto' => __('cosechas.ajustes.enlace'), 'icono' => 'ajustes', 'roles' => ['administrador']],
                ['ruta' => 'admin.ajustes.edit', 'activa' => 'admin.ajustes.*', 'texto' => __('region.ajustes.enlace'), 'icono' => 'ajustes', 'roles' => ['propietario']],
                ['ruta' => 'mi-trabajo.index', 'activa' => 'mi-trabajo.*', 'texto' => __('layout.menu.mi_trabajo'), 'icono' => 'tareas', 'roles' => ['trabajador', 'celador']],
                ['ruta' => 'especies.index', 'activa' => 'especies.*', 'texto' => __('layout.menu.especies'), 'icono' => 'pez', 'roles' => ['propietario', 'administrador', 'trabajador', 'celador']],
            ])->filter(fn ($enlace) => $enlace['roles'] === null
                || collect($enlace['roles'])->contains(fn ($rol) => $usuarioActual->tieneRol($rol)))
                ->map(fn ($enlace) => $enlace + ['es_actual' => request()->routeIs($enlace['activa'] ?? $enlace['ruta'])])
                ->values();
            $conMenuInferior = $enlaces->count() > 1;
            // En celular caben 5 botones: si hay más, se muestran 4 y el resto va en "Más"
            [$enlacesInferiores, $enlacesMas] = $enlaces->count() > 5 ? [$enlaces->take(4), $enlaces->slice(4)] : [$enlaces, collect()];
        @endphp

        <header class="sticky top-0 z-30 print:hidden bg-marca-700 text-white shadow-md pt-[env(safe-area-inset-top)]">
            <div class="mx-auto flex max-w-5xl items-center justify-between gap-3 px-4 py-3">
                <a href="{{ route('inicio') }}" class="flex min-w-0 items-center gap-3">
                    <x-logo class="h-10 w-10 shrink-0 rounded-xl ring-1 ring-white/20" />
                    <span class="min-w-0 leading-tight">
                        <span class="block truncate text-base font-bold">{{ config('app.name') }}</span>
                        <span class="block truncate text-sm text-marca-100">{{ $usuarioActual->tenant?->nombre }}</span>
                    </span>
                </a>

                <div class="flex shrink-0 items-center gap-1">
                    {{-- Mi cuenta: contraseña y verificación en dos pasos (todos los roles) --}}
                    <a href="{{ route('mi-cuenta.index') }}" class="flex min-h-11 items-center gap-2 rounded-xl px-3 text-base font-medium text-marca-50 hover:bg-marca-600" aria-label="{{ __('cuenta.enlace') }}">
                        <x-icono nombre="personal" class="h-5 w-5" />
                        <span class="hidden sm:inline">{{ __('cuenta.enlace') }}</span>
                    </a>
                    <form method="POST" action="{{ route('logout') }}" class="shrink-0">
                        @csrf
                        <button type="submit" class="flex min-h-11 items-center gap-2 rounded-xl px-3 text-base font-medium text-marca-50 hover:bg-marca-600">
                            <x-icono nombre="salir" class="h-5 w-5" />
                            <span>{{ __('layout.salir') }}</span>
                        </button>
                    </form>
                </div>
            </div>

            {{-- Escritorio: segunda fila con todas las secciones (en celular va el menú inferior) --}}
            @if ($conMenuInferior)
                <nav class="mx-auto hidden max-w-5xl gap-1 overflow-x-auto px-3 pb-2 md:flex" aria-label="{{ __('layout.principal') }}">
                    @foreach ($enlaces as $enlace)
                        <a href="{{ route($enlace['ruta']) }}"
                           @if ($enlace['es_actual']) aria-current="page" @endif
                           @class([
                               'flex min-h-10 shrink-0 items-center gap-2 rounded-xl px-3 text-base font-medium transition',
                               'bg-white text-marca-800' => $enlace['es_actual'],
                               'text-marca-50 hover:bg-marca-600' => ! $enlace['es_actual'],
                           ])>
                            <x-icono :nombre="$enlace['icono']" class="h-5 w-5" />
                            {{ $enlace['texto'] }}
                        </a>
                    @endforeach
                </nav>
            @endif
        </header>

        @if ($conMenuInferior)
            <nav class="fixed inset-x-0 bottom-0 z-30 print:hidden border-t border-slate-200 bg-white/95 pb-[env(safe-area-inset-bottom)] shadow-[0_-4px_12px_rgb(0_0_0/0.05)] backdrop-blur md:hidden" aria-label="{{ __('layout.principal_movil') }}">
                <ul class="mx-auto grid max-w-md" style="grid-template-columns: repeat({{ $enlacesInferiores->count() + ($enlacesMas->isEmpty() ? 0 : 1) }}, minmax(0, 1fr))">
                    @foreach ($enlacesInferiores as $enlace)
                        <li>
                            <a href="{{ route($enlace['ruta']) }}"
                               @if ($enlace['es_actual']) aria-current="page" @endif
                               @class([
                                   'flex min-h-16 flex-col items-center justify-center gap-1 text-xs font-semibold',
                                   'text-marca-700' => $enlace['es_actual'],
                                   'text-slate-500' => ! $enlace['es_actual'],
                               ])>
                                <span @class(['flex h-8 w-14 items-center justify-center rounded-full transition', 'bg-marca-100' => $enlace['es_actual']])>
                                    <x-icono :nombre="$enlace['icono']" />
                                </span>
                                {{ $enlace['texto'] }}
                            </a>
                        </li>
                    @endforeach

                    @if ($enlacesMas->isNotEmpty())
                        @php $masActivo = $enlacesMas->contains('es_actual', true); @endphp
                        <li>
                            <button type="button" data-abrir-modal="menu-mas" @class([
                                'flex min-h-16 w-full flex-col items-center justify-center gap-1 text-xs font-semibold',
                                'text-marca-700' => $masActivo,
                                'text-slate-500' => ! $masActivo,
                            ])>
                                <span @class(['flex h-8 w-14 items-center justify-center rounded-full transition', 'bg-marca-100' => $masActivo])>
                                    <x-icono nombre="menu" />
                                </span>
                                {{ __('layout.mas') }}
                            </button>
                        </li>
                    @endif
                </ul>
            </nav>

            @if ($enlacesMas->isNotEmpty())
                <x-modal id="menu-mas" :titulo="__('layout.mas_secciones')">
                    <ul class="space-y-2">
                        @foreach ($enlacesMas as $enlace)
                            <li>
                                <a href="{{ route($enlace['ruta']) }}" @class([
                                    'flex min-h-14 items-center gap-4 rounded-2xl px-4 text-lg font-semibold',
                                    'bg-marca-100 text-marca-800' => $enlace['es_actual'],
                                    'bg-slate-50 text-slate-800 hover:bg-slate-100' => ! $enlace['es_actual'],
                                ])>
                                    <x-icono :nombre="$enlace['icono']" /> {{ $enlace['texto'] }}
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </x-modal>
            @endif
        @endif

        <x-asistente />
    @endif

    <main id="contenido" @class([
        'mx-auto w-full px-4 py-6',
        'max-w-5xl sm:py-8' => $conSesion,
        'pb-28 md:pb-8' => $enFinca && $conMenuInferior,
        'flex min-h-screen max-w-md flex-col justify-center' => ! $conSesion,
    ])>
        @if (session('estado'))
            <div role="status" class="mb-5 flex items-start gap-3 rounded-xl border border-marca-200 bg-marca-50 px-4 py-3 text-base font-medium text-marca-800">
                <x-icono nombre="ok" class="mt-0.5 h-5 w-5" />
                <span>{{ session('estado') }}</span>
            </div>
        @endif

        @if ($conSesion && $errors->any() && ! old('_modal') && ! old('usuario_id'))
            <div role="alert" class="mb-5 flex items-start gap-3 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-base font-medium text-red-700">
                <x-icono nombre="alerta" class="mt-0.5 h-5 w-5" />
                <span>{{ $errors->first() }}</span>
            </div>
        @endif

        @yield('contenido')
    </main>
</body>
</html>
