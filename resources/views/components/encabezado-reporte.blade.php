@props(['titulo', 'periodo', 'descargas' => []])

{{-- $descargas: ['excel' => url, 'pdf' => url] para bajar el mismo reporte como archivo --}}

<header class="mb-6 border-b-2 border-marca-700 pb-4">
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3 print:hidden">
        <a href="{{ route('admin.reportes.index') }}" class="inline-flex min-h-12 items-center text-base font-medium text-marca-700 hover:underline">{{ __('componentes.reportes') }}</a>
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
        <x-logo class="h-12 w-12 shrink-0 rounded-xl" />
        <div class="min-w-0">
            <p class="text-sm font-medium text-texto-suave">{{ auth()->user()->tenant->nombre }}</p>
            <h1 class="text-2xl font-bold text-slate-900">{{ $titulo }}</h1>
            <p class="text-base text-slate-600">{{ $periodo }}</p>
        </div>
    </div>
    <p class="mt-2 text-xs text-texto-suave">{{ __('componentes.generado_el', ['momento' => \App\Support\Region::ahora()->format('d/m/Y H:i'), 'nombre' => auth()->user()->name]) }}</p>
</header>
