<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#0d7e75">
    <title>@hasSection('titulo')@yield('titulo') · @endif{{ config('app.name') }}</title>
    <link rel="icon" href="/favicon.ico" sizes="32x32">
    <link rel="icon" href="/icono.svg" type="image/svg+xml">
    <link rel="apple-touch-icon" href="/icons/apple-touch-icon.png">
    <link rel="manifest" href="/manifest.webmanifest">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen">
    @guest
        <div class="fixed inset-0 -z-10" aria-hidden="true">
            <img src="/images/login-fondo.webp" alt="" class="h-full w-full object-cover" decoding="async">
            <div class="absolute inset-0 bg-gradient-to-b from-marca-950/80 via-marca-900/70 to-marca-950/90"></div>
        </div>
    @endguest

    @auth
        @php
            $usuarioActual = auth()->user()->loadMissing('roles');
            $enlaces = [['ruta' => 'inicio', 'texto' => 'Inicio', 'visible' => true]];
            $enlaces[] = ['ruta' => 'admin.bodega.index', 'texto' => 'Bodega', 'visible' => $usuarioActual->tieneRol('propietario')];
            $enlaces[] = ['ruta' => 'admin.personal.index', 'texto' => 'Personal', 'visible' => $usuarioActual->tieneRol('propietario')];
            $enlaces[] = ['ruta' => 'admin.lagos.index', 'texto' => 'Lagos', 'visible' => $usuarioActual->tieneRol('propietario') || $usuarioActual->tieneRol('administrador') || $usuarioActual->tieneRol('trabajador')];
        @endphp

        <header class="sticky top-0 z-30 bg-marca-700 text-white shadow">
            <div class="mx-auto flex max-w-5xl items-center justify-between gap-3 px-4 py-3">
                <a href="{{ route('inicio') }}" class="flex items-center gap-2 text-lg font-bold">
                    <x-logo class="h-9 w-9 rounded-xl ring-1 ring-white/20" />
                    <span>{{ config('app.name') }}</span>
                </a>

                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="min-h-11 rounded-lg px-3 text-sm font-semibold text-marca-50 hover:bg-marca-600">
                        Salir
                    </button>
                </form>
            </div>

            <nav class="mx-auto flex max-w-5xl gap-1 overflow-x-auto px-2 pb-2" aria-label="Principal">
                @foreach ($enlaces as $enlace)
                    @continue(! $enlace['visible'])
                    <a href="{{ route($enlace['ruta']) }}"
                       @class([
                           'min-h-11 shrink-0 rounded-lg px-4 py-2.5 text-base font-medium',
                           'bg-white text-marca-800' => request()->routeIs($enlace['ruta']),
                           'text-marca-50 hover:bg-marca-600' => ! request()->routeIs($enlace['ruta']),
                       ])>
                        {{ $enlace['texto'] }}
                    </a>
                @endforeach
            </nav>
        </header>
    @endauth

    <main @class([
        'mx-auto w-full px-4 py-6',
        'max-w-5xl' => auth()->check(),
        'flex min-h-screen max-w-md flex-col justify-center' => ! auth()->check(),
    ])>
        @if (session('estado'))
            <div role="status" class="mb-5 rounded-xl border border-marca-200 bg-marca-50 px-4 py-3 text-base font-medium text-marca-800">
                {{ session('estado') }}
            </div>
        @endif

        @yield('contenido')
    </main>
</body>
</html>

