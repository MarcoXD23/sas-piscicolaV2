<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#0f655f">
    <title>@hasSection('titulo')@yield('titulo') · @endif{{ config('app.name') }}</title>
    <link rel="icon" href="/favicon.ico" sizes="32x32">
    <link rel="icon" href="/icono.svg" type="image/svg+xml">
    <link rel="apple-touch-icon" href="/icons/apple-touch-icon.png">
    <link rel="manifest" href="/manifest.webmanifest">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen">
    <a href="#contenido" class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-50 focus:rounded-lg focus:bg-white focus:px-4 focus:py-2 focus:text-marca-800 focus:shadow">
        Saltar al contenido
    </a>

    @guest
        <div class="fixed inset-0 -z-10" aria-hidden="true">
            <img src="/images/login-fondo.webp" alt="" class="h-full w-full object-cover" decoding="async">
            <div class="absolute inset-0 bg-gradient-to-b from-marca-950/80 via-marca-900/70 to-marca-950/90"></div>
        </div>
    @endguest

    @auth
        @php
            $usuarioActual = auth()->user()->loadMissing('roles', 'tenant');
            $enlaces = collect([
                ['ruta' => 'inicio', 'texto' => 'Inicio', 'icono' => 'inicio', 'roles' => null],
                ['ruta' => 'admin.lagos.index', 'texto' => 'Lagos', 'icono' => 'lagos', 'roles' => ['propietario', 'administrador', 'trabajador']],
                ['ruta' => 'admin.bodega.index', 'texto' => 'Bodega', 'icono' => 'bodega', 'roles' => ['propietario']],
                ['ruta' => 'admin.personal.index', 'texto' => 'Personal', 'icono' => 'personal', 'roles' => ['propietario']],
            ])->filter(fn ($enlace) => $enlace['roles'] === null
                || collect($enlace['roles'])->contains(fn ($rol) => $usuarioActual->tieneRol($rol)));
            $conMenuInferior = $enlaces->count() > 1;
        @endphp

        <header class="sticky top-0 z-30 bg-marca-700 text-white shadow-md pt-[env(safe-area-inset-top)]">
            <div class="mx-auto flex max-w-5xl items-center justify-between gap-3 px-4 py-3">
                <a href="{{ route('inicio') }}" class="flex min-w-0 items-center gap-3">
                    <x-logo class="h-10 w-10 shrink-0 rounded-xl ring-1 ring-white/20" />
                    <span class="min-w-0 leading-tight">
                        <span class="block truncate text-base font-bold">{{ config('app.name') }}</span>
                        <span class="block truncate text-sm text-marca-100">{{ $usuarioActual->tenant?->nombre }}</span>
                    </span>
                </a>

                <nav class="hidden items-center gap-1 md:flex" aria-label="Principal">
                    @foreach ($enlaces as $enlace)
                        <a href="{{ route($enlace['ruta']) }}"
                           @if (request()->routeIs($enlace['ruta'])) aria-current="page" @endif
                           @class([
                               'flex min-h-11 items-center gap-2 rounded-xl px-4 text-base font-medium transition',
                               'bg-white text-marca-800' => request()->routeIs($enlace['ruta']),
                               'text-marca-50 hover:bg-marca-600' => ! request()->routeIs($enlace['ruta']),
                           ])>
                            <x-icono :nombre="$enlace['icono']" class="h-5 w-5" />
                            {{ $enlace['texto'] }}
                        </a>
                    @endforeach
                </nav>

                <form method="POST" action="{{ route('logout') }}" class="shrink-0">
                    @csrf
                    <button type="submit" class="flex min-h-11 items-center gap-2 rounded-xl px-3 text-base font-medium text-marca-50 hover:bg-marca-600">
                        <x-icono nombre="salir" class="h-5 w-5" />
                        <span>Salir</span>
                    </button>
                </form>
            </div>
        </header>

        @if ($conMenuInferior)
            <nav class="fixed inset-x-0 bottom-0 z-30 border-t border-slate-200 bg-white/95 pb-[env(safe-area-inset-bottom)] shadow-[0_-4px_12px_rgb(0_0_0/0.05)] backdrop-blur md:hidden" aria-label="Principal (móvil)">
                <ul class="mx-auto grid max-w-md" style="grid-template-columns: repeat({{ $enlaces->count() }}, minmax(0, 1fr))">
                    @foreach ($enlaces as $enlace)
                        <li>
                            <a href="{{ route($enlace['ruta']) }}"
                               @if (request()->routeIs($enlace['ruta'])) aria-current="page" @endif
                               @class([
                                   'flex min-h-16 flex-col items-center justify-center gap-1 text-xs font-semibold',
                                   'text-marca-700' => request()->routeIs($enlace['ruta']),
                                   'text-slate-500' => ! request()->routeIs($enlace['ruta']),
                               ])>
                                <span @class([
                                    'flex h-8 w-14 items-center justify-center rounded-full transition',
                                    'bg-marca-100' => request()->routeIs($enlace['ruta']),
                                ])>
                                    <x-icono :nombre="$enlace['icono']" />
                                </span>
                                {{ $enlace['texto'] }}
                            </a>
                        </li>
                    @endforeach
                </ul>
            </nav>
        @endif
    @endauth

    <main id="contenido" @class([
        'mx-auto w-full px-4 py-6',
        'max-w-5xl sm:py-8' => auth()->check(),
        'pb-28 md:pb-8' => auth()->check() && $conMenuInferior,
        'flex min-h-screen max-w-md flex-col justify-center' => ! auth()->check(),
    ])>
        @if (session('estado'))
            <div role="status" class="mb-5 flex items-start gap-3 rounded-xl border border-marca-200 bg-marca-50 px-4 py-3 text-base font-medium text-marca-800">
                <x-icono nombre="ok" class="mt-0.5 h-5 w-5" />
                <span>{{ session('estado') }}</span>
            </div>
        @endif

        @if (auth()->check() && $errors->any() && ! old('_modal') && ! old('usuario_id'))
            <div role="alert" class="mb-5 flex items-start gap-3 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-base font-medium text-red-700">
                <x-icono nombre="alerta" class="mt-0.5 h-5 w-5" />
                <span>{{ $errors->first() }}</span>
            </div>
        @endif

        @yield('contenido')
    </main>
</body>
</html>
