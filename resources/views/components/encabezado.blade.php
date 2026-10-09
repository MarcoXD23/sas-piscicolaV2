@props(['titulo', 'subtitulo' => null])

<div {{ $attributes->class('mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between') }}>
    <div>
        <h1 class="bg-gradient-to-r from-slate-900 via-marca-900 to-marca-700 bg-clip-text pb-0.5 text-2xl font-bold tracking-tight text-transparent sm:text-3xl print:text-slate-900">{{ $titulo }}</h1>
        @if ($subtitulo)
            <p class="mt-1 text-base text-texto-suave">{{ $subtitulo }}</p>
        @endif
        <span class="crece-ancho mt-3 block h-1 w-12 rounded-full bg-gradient-to-r from-marca-500 to-sky-400 print:hidden" aria-hidden="true"></span>
    </div>

    @if (trim($slot) !== '')
        <div class="flex flex-wrap gap-3">{{ $slot }}</div>
    @endif
</div>
