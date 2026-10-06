@props(['titulo', 'subtitulo' => null])

<div {{ $attributes->class('mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between') }}>
    <div>
        <h1 class="text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">{{ $titulo }}</h1>
        @if ($subtitulo)
            <p class="mt-1 text-base text-slate-500">{{ $subtitulo }}</p>
        @endif
    </div>

    @if (trim($slot) !== '')
        <div class="flex flex-wrap gap-3">{{ $slot }}</div>
    @endif
</div>
