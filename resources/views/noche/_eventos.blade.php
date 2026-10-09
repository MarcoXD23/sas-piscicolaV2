{{-- Lista de eventos de la noche (rondas, novedades, aireadores y mediciones). $eventos, $tonos y $con_fecha --}}
<ol class="rounded-2xl bg-white shadow-suave ring-1 ring-slate-200">
    @foreach ($eventos as $evento)
        <li class="flex gap-4 border-t border-slate-100 p-4 first:border-t-0">
            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl {{ $tonos[$evento['tono']] }}">
                <x-icono :nombre="$evento['icono']" class="h-5 w-5" />
            </span>
            <div class="min-w-0 flex-1">
                <div class="flex flex-wrap items-baseline justify-between gap-x-3">
                    <p class="font-semibold text-slate-900">{{ $evento['titulo'] }}</p>
                    <time class="text-sm tabular-nums text-texto-suave" datetime="{{ $evento['momento']->toIso8601String() }}">{{ $evento['momento']->format(($con_fecha ?? false) ? 'd/m H:i' : 'H:i') }}</time>
                </div>
                @if ($evento['detalle'] !== '')
                    <p class="text-sm text-slate-600">{{ $evento['detalle'] }}</p>
                @endif
            </div>
        </li>
    @endforeach
</ol>
