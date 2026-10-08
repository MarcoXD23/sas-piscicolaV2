<!DOCTYPE html>
{{--
    «Registro rápido»: cascarón PÚBLICO y SIN DATOS. No usa el layout de la app (que muestra la finca, el menú y el
    token de la sesión): el service worker guarda esta página tal cual y el teléfono la arma con el paquete de trabajo
    guardado en IndexedDB (resources/js/campo.js). Aquí no debe aparecer nada de la finca ni de la persona.
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
        <div class="mx-auto flex max-w-xl items-center gap-3 px-4 py-3">
            <x-logo class="h-10 w-10 shrink-0 rounded-xl ring-1 ring-white/20" />
            <h1 class="text-lg font-bold">{{ __('campo.titulo') }}</h1>
        </div>
    </header>

    <main id="contenido" class="mx-auto w-full max-w-xl px-4 py-6" data-campo>
        <p data-campo-cargando class="text-base text-slate-600">{{ __('campo.cargando') }}</p>
        <noscript><p class="text-base text-red-700">{{ __('campo.sin_javascript') }}</p></noscript>
        <a href="/" class="mt-6 inline-flex min-h-11 items-center text-base font-medium text-marca-700 hover:underline">{{ __('campo.volver_inicio') }}</a>
    </main>
</body>
</html>
