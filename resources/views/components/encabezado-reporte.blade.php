@props(['titulo', 'periodo'])

<header class="mb-6 border-b-2 border-marca-700 pb-4">
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3 print:hidden">
        <a href="{{ route('admin.reportes.index') }}" class="inline-flex min-h-11 items-center text-base font-medium text-marca-700 hover:underline">{{ __('componentes.reportes') }}</a>
        <x-boton tipo="button" onclick="window.print()">
            <x-icono nombre="imprimir" class="h-5 w-5" /> {{ __('componentes.imprimir_guardar_pdf') }}
        </x-boton>
    </div>

    <div class="flex items-start gap-4">
        <x-logo class="h-12 w-12 shrink-0 rounded-xl" />
        <div class="min-w-0">
            <p class="text-sm font-medium text-slate-500">{{ auth()->user()->tenant->nombre }}</p>
            <h1 class="text-2xl font-bold text-slate-900">{{ $titulo }}</h1>
            <p class="text-base text-slate-600">{{ $periodo }}</p>
        </div>
    </div>
    <p class="mt-2 text-xs text-slate-500">{{ __('componentes.generado_el', ['momento' => \App\Support\Region::ahora()->format('d/m/Y H:i'), 'nombre' => auth()->user()->name]) }}</p>
</header>
