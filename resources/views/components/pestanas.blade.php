@props(['enlaces' => []])

{{-- $enlaces: lista de ['ruta' => nombre de ruta, 'texto' => etiqueta] --}}
<nav {{ $attributes->class('mb-6 flex gap-1 rounded-2xl bg-slate-200/70 p-1') }} aria-label="{{ __('componentes.secciones') }}">
    @foreach ($enlaces as $enlace)
        @php $activa = request()->routeIs($enlace['ruta']); @endphp
        <a href="{{ route($enlace['ruta']) }}" @if ($activa) aria-current="page" @endif
           @class([
               'flex min-h-11 flex-1 items-center justify-center rounded-xl px-4 text-base font-semibold transition',
               'bg-white text-marca-800 shadow-sm' => $activa,
               'text-slate-600 hover:text-slate-900' => ! $activa,
           ])>
            {{ $enlace['texto'] }}
        </a>
    @endforeach
</nav>
