@props(['titulo' => null, 'subtitulo' => null])

<section {{ $attributes->class('rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200 sm:p-6') }}>
    @if ($titulo)
        <header class="mb-4">
            <h2 class="text-lg font-semibold text-slate-900">{{ $titulo }}</h2>
            @if ($subtitulo)
                <p class="mt-1 text-sm text-slate-500">{{ $subtitulo }}</p>
            @endif
        </header>
    @endif

    {{ $slot }}
</section>

