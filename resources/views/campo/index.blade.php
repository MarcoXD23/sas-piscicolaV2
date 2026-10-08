<!DOCTYPE html>
{{--
    «Registro rápido»: cascarón PÚBLICO y SIN DATOS. No usa el layout de la app (que muestra la finca, el menú y el
    token de la sesión) ni componentes que dependan de la sesión: el service worker guarda esta página tal cual y el
    teléfono la arma con el paquete de trabajo guardado en IndexedDB (resources/js/campo.js).
    Aquí no debe aparecer nada de la finca ni de la persona.
--}}
<html lang="{{ \App\Support\Region::IDIOMA_POR_DEFECTO }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#0f655f">
    <title>{{ __('campo.titulo') }} · {{ config('app.name') }}</title>
    <link rel="icon" href="/favicon.ico" sizes="16x16 32x32 48x48">
    <link rel="manifest" href="/manifest.webmanifest">
    <script>window.textos = @json(__('js'));</script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-50">
    <header class="sticky top-0 z-30 bg-marca-700 text-white shadow-md pt-[env(safe-area-inset-top)]">
        <div class="mx-auto flex max-w-xl items-center justify-between gap-3 px-4 py-3">
            <div class="flex min-w-0 items-center gap-3">
                <x-logo class="h-10 w-10 shrink-0 rounded-xl ring-1 ring-white/20" />
                <h1 class="truncate text-lg font-bold">{{ __('campo.titulo') }}</h1>
            </div>
            {{-- Registros guardados en el teléfono sin enviar (o rechazados): solo aparece si hay alguno --}}
            <a href="#cola-sin-conexion" data-cola-boton hidden class="flex min-h-11 shrink-0 items-center gap-1.5 rounded-xl bg-amber-400 px-3 text-base font-bold text-amber-950" aria-label="{{ __('layout.cola.boton') }}">
                <x-icono nombre="reloj" class="h-5 w-5" />
                <span data-cola-total>0</span>
            </a>
        </div>
    </header>

    <main id="contenido" class="mx-auto w-full max-w-xl space-y-5 px-4 py-6"
          data-campo data-horas-viejo="{{ config('sin_conexion.horas_paquete_viejo') }}" data-minutos-paquete="{{ config('sin_conexion.minutos_paquete') }}">
        <p data-campo-mensaje hidden role="status"></p>
        <p data-campo-cargando class="text-base text-slate-600">{{ __('campo.cargando') }}</p>
        <noscript><p class="text-base text-red-700">{{ __('campo.sin_javascript') }}</p></noscript>

        {{-- Sin paquete en este teléfono: hay que entrar una vez con señal --}}
        <section data-campo-sin-paquete hidden class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
            <h2 class="text-lg font-semibold text-slate-900">{{ __('campo.sin_paquete_titulo') }}</h2>
            <p class="mt-2 text-base text-slate-600">{{ __('campo.sin_paquete') }}</p>
            <a href="/login" class="mt-4 inline-flex min-h-12 items-center justify-center rounded-xl bg-marca-600 px-5 py-3 text-base font-semibold text-white shadow-sm hover:bg-marca-700">{{ __('campo.ingresar') }}</a>
        </section>

        <section data-campo-listo hidden class="space-y-4">
            <div class="space-y-2">
                <p data-campo-quien class="text-base font-semibold text-slate-900"></p>
                <p data-campo-antiguedad></p>
                <p data-campo-truncado hidden class="text-sm text-amber-800"></p>
            </div>
            <div data-campo-secciones class="grid grid-cols-2 gap-3"></div>
            <p data-campo-sin-secciones hidden class="text-base text-slate-600">{{ __('campo.sin_secciones') }}</p>
        </section>

        <button type="button" data-campo-actualizar hidden class="inline-flex min-h-11 items-center justify-center rounded-xl bg-white px-4 text-base font-semibold text-marca-700 shadow-sm ring-1 ring-inset ring-marca-200 hover:bg-marca-50">{{ __('campo.actualizar') }}</button>

        <div data-campo-formulario></div>

        {{-- Lista de la cola (la misma de la app, sin componentes de sesión): la pinta resources/js/sin-conexion.js --}}
        <section id="cola-sin-conexion" class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
            <h2 class="mb-2 text-lg font-semibold text-slate-900">{{ __('layout.cola.titulo') }}</h2>
            <p class="mb-4 text-sm text-slate-600">{{ __('layout.cola.explicacion') }}</p>
            <p data-cola-vacia class="text-base text-slate-500">{{ __('layout.cola.vacia') }}</p>
            <section data-cola-rechazados hidden class="mb-5">
                <h3 class="mb-2 text-base font-semibold text-red-700">{{ __('layout.cola.rechazados') }}</h3>
                <p class="mb-3 text-sm text-slate-600">{{ __('layout.cola.rechazados_ayuda') }}</p>
                <ul class="space-y-3"></ul>
            </section>
            <section data-cola-pendientes hidden class="mb-5">
                <h3 class="mb-2 text-base font-semibold text-slate-900">{{ __('layout.cola.pendientes') }}</h3>
                <ul class="space-y-3"></ul>
            </section>
            <button type="button" data-cola-enviar hidden class="inline-flex min-h-12 w-full items-center justify-center rounded-xl bg-marca-600 px-5 py-3 text-base font-semibold text-white shadow-sm hover:bg-marca-700">{{ __('layout.cola.enviar_ahora') }}</button>
        </section>

        <a href="/" class="inline-flex min-h-11 items-center text-base font-medium text-marca-700 hover:underline">{{ __('campo.volver_inicio') }}</a>
    </main>
</body>
</html>
