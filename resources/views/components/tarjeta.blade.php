@props(['titulo' => null, 'subtitulo' => null])

<section data-revelar {{ $attributes->class('rounded-2xl bg-white p-5 shadow-suave ring-1 ring-slate-200 sm:p-6') }}>
    @if ($titulo)
        <header class="mb-4 flex gap-3">
            <span class="mt-1 h-5 w-1 shrink-0 rounded-full bg-gradient-to-b from-marca-400 to-marca-600" aria-hidden="true"></span>
            <div class="min-w-0">
                <h2 class="text-lg font-semibold tracking-tight text-slate-900">{{ $titulo }}</h2>
                @if ($subtitulo)
                    <p class="mt-1 text-sm text-texto-suave">{{ $subtitulo }}</p>
                @endif
            </div>
        </header>
    @endif

    {{ $slot }}
</section>
