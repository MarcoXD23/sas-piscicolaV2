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
@php
    // El panel de plataforma usa su propio ingreso (guard «plataforma») y no muestra el menú de las fincas
    $rutaPlataforma = request()->routeIs('plataforma.*');
    $enFinca = auth()->check() && ! $rutaPlataforma;
    $enPlataforma = $rutaPlataforma && auth('plataforma')->check();
    $conSesion = $enFinca || $enPlataforma;
    $conMenuInferior = false;
@endphp
{{-- Registros sin conexión (resources/js/sin-conexion.js): la cola del teléfono va separada por finca y persona --}}
<body class="min-h-screen" @if ($enFinca) data-cola-clave="{{ auth()->user()->tenant_id }}|{{ auth()->id() }}" data-cola-horas="{{ config('sin_conexion.horas') }}" data-paquete-minutos="{{ config('sin_conexion.minutos_paquete') }}" @endif>

    {{-- Sin conexión: la página puede ser una copia guardada (solo consulta). Lleva la hora en que se generó. --}}
    <div data-sin-conexion hidden role="status" class="sticky top-0 z-40 animate-aparecer bg-gradient-to-r from-amber-300 via-amber-400 to-amber-300 px-4 py-2 text-center text-sm font-semibold text-amber-950 shadow-md print:hidden">
        {{ __('layout.sin_conexion', ['momento' => \App\Support\Region::ahora()->format('d/m H:i')]) }}
        @auth
            <a href="{{ route('campo') }}" class="ml-1 underline underline-offset-2">{{ __('layout.abrir_registro_rapido') }}</a>
        @endauth
    </div>

    <a href="#contenido" class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-50 focus:rounded-lg focus:bg-white focus:px-4 focus:py-2 focus:text-marca-800 focus:shadow">
        {{ __('layout.saltar_contenido') }}
    </a>

    @unless ($conSesion)
        {{-- Fondo del ingreso: foto de un estanque piscícola (CC0, ver docs/marca/LEEME.md) con un velo oscuro para el contraste --}}
        <div class="fixed inset-0 -z-10 overflow-hidden bg-marca-900" aria-hidden="true">
            <img src="/images/fondo-login.webp" alt="" class="h-full w-full object-cover" decoding="async">
            <div class="absolute inset-0 bg-gradient-to-b from-marca-950/80 via-marca-900/65 to-marca-950/90"></div>
            <div class="absolute -top-32 -left-24 h-80 w-80 rounded-full bg-marca-400/20 blur-3xl"></div>
            <div class="absolute -right-24 bottom-24 h-80 w-80 rounded-full bg-sky-400/15 blur-3xl"></div>
            {{-- Olas al pie (dibujo fijo) --}}
            <svg class="absolute bottom-0 left-0 h-28 w-[200%] text-marca-400/15" viewBox="0 0 2880 100" preserveAspectRatio="none">
                <path fill="currentColor" d="M0 50 Q360 0 720 50 T1440 50 T2160 50 T2880 50 V100 H0Z"/>
            </svg>
            <svg class="absolute bottom-0 left-0 h-20 w-[200%] text-marca-950/50" viewBox="0 0 2880 100" preserveAspectRatio="none">
                <path fill="currentColor" d="M0 60 Q360 20 720 60 T1440 60 T2160 60 T2880 60 V100 H0Z"/>
            </svg>
        </div>
    @endunless

    @if ($enPlataforma)
        <header class="cabecera sticky top-0 z-30 bg-gradient-to-r from-slate-900 via-slate-800 to-slate-700 pt-[env(safe-area-inset-top)] text-white [view-transition-name:cabecera] print:hidden">
            <div class="mx-auto flex max-w-5xl items-center justify-between gap-3 px-4 py-3">
                <a href="{{ route('plataforma.fincas.index') }}" class="group flex min-w-0 items-center gap-3">
                    <x-logo class="h-10 w-10 shrink-0 rounded-xl shadow-lg ring-1 ring-white/20 transition duration-300 ease-resorte group-hover:-rotate-6 group-hover:scale-105" />
                    <span class="min-w-0 leading-tight">
                        <span class="block truncate text-base font-bold">{{ config('app.name') }}</span>
                        <span class="block truncate text-sm text-slate-300">{{ __('plataforma.panel') }}</span>
                    </span>
                </a>

                <form method="POST" action="{{ route('plataforma.logout') }}" class="shrink-0">
                    @csrf
                    <button type="submit" class="presionable flex min-h-12 items-center gap-2 rounded-xl px-3 text-base font-medium text-slate-100 hover:bg-white/10">
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
                // Registro rápido: registrar sin conexión con el paquete de trabajo del teléfono (no para el propietario)
                ['ruta' => 'campo', 'texto' => __('layout.menu.registro_rapido'), 'icono' => 'mas', 'roles' => ['administrador', 'trabajador', 'celador']],
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

        <header class="cabecera sticky top-0 z-30 bg-gradient-to-r from-marca-900 via-marca-700 to-marca-600 pt-[env(safe-area-inset-top)] text-white [view-transition-name:cabecera] print:hidden">
            {{-- Reflejo de fondo, muy tenue --}}
            <div class="pointer-events-none absolute inset-0 overflow-hidden" aria-hidden="true">
                <div class="absolute -top-16 right-10 h-40 w-40 rounded-full bg-marca-300/20 blur-3xl"></div>
            </div>

            <div class="relative mx-auto flex max-w-5xl items-center justify-between gap-3 px-4 py-3">
                <a href="{{ route('inicio') }}" class="group flex min-w-0 items-center gap-3">
                    <x-logo class="h-10 w-10 shrink-0 rounded-xl shadow-lg ring-1 ring-white/25 transition duration-300 ease-resorte group-hover:-rotate-6 group-hover:scale-105" />
                    <span class="min-w-0 leading-tight">
                        <span class="block truncate text-base font-bold">{{ config('app.name') }}</span>
                        <span class="block truncate text-sm text-marca-100">{{ $usuarioActual->tenant?->nombre }}</span>
                    </span>
                </a>

                <div class="flex shrink-0 items-center gap-1">
                    {{-- Registros guardados en el teléfono sin enviar (o rechazados): solo aparece si hay alguno --}}
                    <button type="button" data-cola-boton hidden data-abrir-modal="cola-sin-conexion" class="presionable flex min-h-12 animate-aparecer-escala items-center gap-1.5 rounded-xl bg-gradient-to-b from-amber-300 to-amber-400 px-3 text-base font-bold text-amber-950 shadow-[0_4px_14px_-4px_rgb(251_191_36/0.8)]" aria-label="{{ __('layout.cola.boton') }}">
                        <x-icono nombre="reloj" class="h-5 w-5" />
                        <span data-cola-total>0</span>
                    </button>
                    {{-- Mi cuenta: contraseña y verificación en dos pasos (todos los roles) --}}
                    <a href="{{ route('mi-cuenta.index') }}" class="presionable flex min-h-12 items-center gap-2 rounded-xl px-3 text-base font-medium text-marca-50 hover:bg-white/10" aria-label="{{ __('cuenta.enlace') }}">
                        <x-icono nombre="personal" class="h-5 w-5" />
                        <span class="hidden sm:inline">{{ __('cuenta.enlace') }}</span>
                    </a>
                    <form method="POST" action="{{ route('logout') }}" class="shrink-0">
                        @csrf
                        <button type="submit" class="presionable flex min-h-12 items-center gap-2 rounded-xl px-3 text-base font-medium text-marca-50 hover:bg-white/10">
                            <x-icono nombre="salir" class="h-5 w-5" />
                            <span>{{ __('layout.salir') }}</span>
                        </button>
                    </form>
                </div>
            </div>

            {{-- Escritorio: segunda fila con todas las secciones (en celular va el menú inferior) --}}
            @if ($conMenuInferior)
                <nav class="relative mx-auto hidden max-w-5xl gap-1 overflow-x-auto px-3 pb-2 [scrollbar-width:none] md:flex" aria-label="{{ __('layout.principal') }}">
                    @foreach ($enlaces as $enlace)
                        <a href="{{ route($enlace['ruta']) }}"
                           @if ($enlace['es_actual']) aria-current="page" @endif
                           @class([
                               'group presionable flex min-h-10 shrink-0 items-center gap-2 rounded-xl px-3 text-base font-medium',
                               'animate-aparecer-escala bg-white text-marca-800 shadow-lg shadow-marca-950/20' => $enlace['es_actual'],
                               'text-marca-50 hover:bg-white/10 hover:text-white' => ! $enlace['es_actual'],
                           ])>
                            <x-icono :nombre="$enlace['icono']" class="h-5 w-5 transition duration-300 ease-resorte group-hover:scale-110" />
                            {{ $enlace['texto'] }}
                        </a>
                    @endforeach
                </nav>
            @endif
        </header>

        @if ($conMenuInferior)
            <nav class="fixed inset-x-0 bottom-0 z-30 border-t border-slate-200/70 bg-white/80 pb-[env(safe-area-inset-bottom)] shadow-[0_-8px_24px_-12px_rgb(4_40_39/0.18)] backdrop-blur-xl backdrop-saturate-150 [view-transition-name:menu-inferior] md:hidden print:hidden" aria-label="{{ __('layout.principal_movil') }}">
                <ul class="mx-auto grid max-w-md" style="grid-template-columns: repeat({{ $enlacesInferiores->count() + ($enlacesMas->isEmpty() ? 0 : 1) }}, minmax(0, 1fr))">
                    @foreach ($enlacesInferiores as $enlace)
                        <li>
                            <a href="{{ route($enlace['ruta']) }}"
                               @if ($enlace['es_actual']) aria-current="page" @endif
                               @class([
                                   'presionable flex min-h-16 flex-col items-center justify-center gap-1 text-[0.8125rem] leading-tight font-semibold',
                                   'text-marca-700' => $enlace['es_actual'],
                                   'text-texto-suave active:text-marca-700' => ! $enlace['es_actual'],
                               ])>
                                <span @class([
                                    'flex h-8 w-14 items-center justify-center rounded-full transition',
                                    'animate-aparecer-escala bg-gradient-to-br from-marca-100 to-marca-200/80 shadow-sm' => $enlace['es_actual'],
                                ])>
                                    <x-icono :nombre="$enlace['icono']" @class(['animate-saltar' => $enlace['es_actual']]) />
                                </span>
                                {{ $enlace['texto'] }}
                            </a>
                        </li>
                    @endforeach

                    @if ($enlacesMas->isNotEmpty())
                        @php $masActivo = $enlacesMas->contains('es_actual', true); @endphp
                        <li>
                            <button type="button" data-abrir-modal="menu-mas" @class([
                                'presionable flex min-h-16 w-full flex-col items-center justify-center gap-1 text-[0.8125rem] leading-tight font-semibold',
                                'text-marca-700' => $masActivo,
                                'text-texto-suave active:text-marca-700' => ! $masActivo,
                            ])>
                                <span @class([
                                    'flex h-8 w-14 items-center justify-center rounded-full transition',
                                    'animate-aparecer-escala bg-gradient-to-br from-marca-100 to-marca-200/80 shadow-sm' => $masActivo,
                                ])>
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
                    <ul class="escalonado space-y-2">
                        @foreach ($enlacesMas as $enlace)
                            <li>
                                <a href="{{ route($enlace['ruta']) }}" @class([
                                    'presionable flex min-h-14 items-center gap-4 rounded-2xl px-4 text-lg font-semibold',
                                    'bg-gradient-to-r from-marca-100 to-marca-50 text-marca-800 ring-1 ring-marca-200' => $enlace['es_actual'],
                                    'bg-slate-50 text-slate-800 hover:bg-marca-50 hover:text-marca-800' => ! $enlace['es_actual'],
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

        <x-modal id="cola-sin-conexion" :titulo="__('layout.cola.titulo')">
            <p class="mb-4 text-base text-slate-600">{{ __('layout.cola.explicacion') }}</p>
            <p data-cola-vacia class="text-base text-texto-suave">{{ __('layout.cola.vacia') }}</p>
            <section data-cola-rechazados hidden class="mb-5">
                <h3 class="mb-2 text-base font-semibold text-red-700">{{ __('layout.cola.rechazados') }}</h3>
                <p class="mb-3 text-sm text-slate-600">{{ __('layout.cola.rechazados_ayuda') }}</p>
                <ul class="space-y-3"></ul>
            </section>
            <section data-cola-pendientes hidden class="mb-5">
                <h3 class="mb-2 text-base font-semibold text-slate-900">{{ __('layout.cola.pendientes') }}</h3>
                <ul class="space-y-3"></ul>
            </section>
            <x-boton tipo="button" class="w-full" data-cola-enviar hidden>{{ __('layout.cola.enviar_ahora') }}</x-boton>
        </x-modal>

        <x-modal id="salir-con-pendientes" :titulo="__('layout.cola.salir_titulo')">
            <p class="text-base text-slate-700">{!! __('layout.cola.salir_texto', ['cantidad' => '<strong data-salir-total>0</strong>']) !!}</p>
            <div class="mt-5 grid grid-cols-2 gap-3">
                <x-boton tipo="button" variante="secundario" data-cerrar-modal>{{ __('layout.cola.esperar') }}</x-boton>
                <x-boton tipo="button" variante="peligro" data-salir-borrar>{{ __('layout.cola.salir_borrar') }}</x-boton>
            </div>
        </x-modal>
    @endif

    <main id="contenido" @class([
        'mx-auto w-full px-4 py-6',
        'max-w-5xl sm:py-8' => $conSesion,
        'pb-28 md:pb-8' => $enFinca && $conMenuInferior,
        'flex min-h-screen max-w-md flex-col justify-center' => ! $conSesion,
    ])>
        @if (session('estado'))
            <div role="status" class="aviso-temporal mb-5 flex items-start gap-3 rounded-xl border border-marca-200 bg-gradient-to-r from-marca-50 to-white px-4 py-3 text-base font-medium text-marca-800 shadow-suave">
                <span class="flex h-6 w-6 shrink-0 animate-aparecer-escala items-center justify-center rounded-full bg-marca-500 text-white [animation-delay:200ms]">
                    <x-icono nombre="ok" class="h-4 w-4" />
                </span>
                <span>{{ session('estado') }}</span>
            </div>
        @endif

        @if ($conSesion && $errors->any() && ! old('_modal') && ! old('usuario_id'))
            <div role="alert" class="mb-5 flex animate-sacudir items-start gap-3 rounded-xl border border-red-200 bg-gradient-to-r from-red-50 to-white px-4 py-3 text-base font-medium text-red-700 shadow-suave">
                <x-icono nombre="alerta" class="mt-0.5 h-5 w-5" />
                <span>{{ $errors->first() }}</span>
            </div>
        @endif

        @yield('contenido')

        @if ($enFinca)
            <footer class="mt-12 border-t border-slate-200/70 pt-5 text-center text-sm text-texto-suave print:hidden">
                <a href="{{ route('legal.terminos') }}" class="underline decoration-slate-300 underline-offset-4 transition-colors hover:text-marca-700 hover:decoration-marca-400">{{ __('legal.terminos') }}</a>
                ·
                <a href="{{ route('legal.privacidad') }}" class="underline decoration-slate-300 underline-offset-4 transition-colors hover:text-marca-700 hover:decoration-marca-400">{{ __('legal.privacidad') }}</a>
            </footer>
        @endif
    </main>
</body>
</html>
