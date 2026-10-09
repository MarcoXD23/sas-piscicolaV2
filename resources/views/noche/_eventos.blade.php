{{-- Lista de eventos de la noche (rondas, novedades, aireadores y mediciones). $eventos, $tonos y $con_fecha --}}
<ol class="divide-y divide-renglon rounded-ficha bg-ficha shadow-ficha ring-1 ring-renglon">
    @foreach ($eventos as $evento)
        <li class="flex gap-4 px-5 py-3.5">
            <span class="mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-full {{ $tonos[$evento['tono']] }}">
                <x-icono :nombre="$evento['icono']" class="h-5 w-5" />
            </span>
            <div class="min-w-0 flex-1">
                <div class="flex flex-wrap items-baseline justify-between gap-x-3">
                    <p class="font-bold">{{ $evento['titulo'] }}</p>
                    <time class="text-sm tabular-nums text-tinta-suave" datetime="{{ $evento['momento']->toIso8601String() }}">{{ $evento['momento']->format(($con_fecha ?? false) ? 'd/m H:i' : 'H:i') }}</time>
                </div>
                @if ($evento['detalle'] !== '')
                    <p class="text-sm text-tinta-suave">{{ $evento['detalle'] }}</p>
                @endif
            </div>
        </li>
    @endforeach
</ol>
