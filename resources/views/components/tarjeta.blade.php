@props(['titulo' => null, 'subtitulo' => null])

{{-- Ficha de la libreta: blanca, borde de renglón y sombra de papel. El título queda sobre un renglón. --}}
<section {{ $attributes->class('rounded-ficha bg-ficha p-5 shadow-ficha ring-1 ring-renglon sm:p-6') }}>
    @if ($titulo)
        <header class="mb-4 border-b border-renglon pb-3">
            <h2 class="text-lg font-bold text-tinta">{{ $titulo }}</h2>
            @if ($subtitulo)
                <p class="mt-0.5 text-sm text-tinta-suave">{{ $subtitulo }}</p>
            @endif
        </header>
    @endif

    {{ $slot }}
</section>
