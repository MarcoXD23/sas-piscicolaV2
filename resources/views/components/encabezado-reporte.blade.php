@props(['titulo', 'periodo', 'descargas' => []])

{{-- $descargas: ['excel' => url, 'pdf' => url] para bajar el mismo reporte como archivo --}}

<header class="mb-6 border-b-2 border-tinta pb-4">
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3 print:hidden">
        <a href="{{ route('admin.reportes.index') }}" class="-ml-1 inline-flex min-h-12 items-center gap-1 text-base font-semibold text-marca-700 underline decoration-renglon-fuerte underline-offset-4 hover:decoration-marca-700"><x-icono nombre="abajo" class="h-5 w-5 rotate-90" /> {{ __('componentes.reportes') }}</a>
        <div class="flex flex-wrap gap-2">
            @foreach ($descargas as $formato => $url)
                <x-boton variante="secundario" :href="$url">
                    <x-icono nombre="reporte" class="h-5 w-5" /> {{ $formato === 'excel' ? __('componentes.descargar_excel') : __('componentes.descargar_pdf') }}
                </x-boton>
            @endforeach
            <x-boton tipo="button" onclick="window.print()">
                <x-icono nombre="imprimir" class="h-5 w-5" /> {{ __('componentes.imprimir') }}
            </x-boton>
        </div>
    </div>

    <div class="flex items-start gap-4">
        <x-logo class="h-12 w-12 shrink-0 rounded-lg" />
        <div class="min-w-0">
            <p class="text-sm font-semibold text-tinta-suave">{{ auth()->user()->tenant->nombre }}</p>
            <h1 class="text-titulo font-bold text-tinta">{{ $titulo }}</h1>
            <p class="text-base text-tinta-suave">{{ $periodo }}</p>
        </div>
    </div>
    <p class="mt-2 text-xs text-tinta-suave">{{ __('componentes.generado_el', ['momento' => \App\Support\Region::ahora()->formatoHora('d/m/Y H:i'), 'nombre' => auth()->user()->name]) }}</p>
</header>
