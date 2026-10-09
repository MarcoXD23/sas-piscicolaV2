@props(['enlaces' => []])

{{-- $enlaces: lista de ['ruta' => nombre de ruta, 'texto' => etiqueta]. Como el menú: la activa lleva raya y negrita. --}}
<nav {{ $attributes->class('mb-6 flex gap-1 border-b border-renglon') }} aria-label="{{ __('componentes.secciones') }}">
    @foreach ($enlaces as $enlace)
        @php $activa = request()->routeIs($enlace['ruta']); @endphp
        <a href="{{ route($enlace['ruta']) }}" @if ($activa) aria-current="page" @endif
           @class([
               '-mb-px flex min-h-12 flex-1 items-center justify-center px-4 text-base transition-colors sm:flex-none',
               'font-bold text-tinta shadow-[inset_0_-3px_0_var(--color-marca-700)]' => $activa,
               'text-tinta-suave hover:bg-papel-hondo hover:text-tinta' => ! $activa,
           ])>
            {{ $enlace['texto'] }}
        </a>
    @endforeach
</nav>
