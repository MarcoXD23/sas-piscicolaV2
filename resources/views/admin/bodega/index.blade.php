@extends('layouts.app')

@section('titulo', 'Bodega de alimento')

@section('contenido')
    <x-encabezado titulo="Bodega de alimento" subtitulo="Inventario de concentrados de la finca.">
        <x-boton tipo="button" data-abrir-modal="modal-recepcion">
            <x-icono nombre="mas" class="h-5 w-5" /> Recibir alimento
        </x-boton>
    </x-encabezado>

    @if ($stocks->isEmpty())
        <x-vacio icono="bodega" mensaje="La bodega está vacía. Registra la primera entrada de alimento.">
            <x-boton tipo="button" data-abrir-modal="modal-recepcion">
                <x-icono nombre="mas" class="h-5 w-5" /> Recibir alimento
            </x-boton>
        </x-vacio>
    @endif

    <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ($stocks as $stock)
            <x-tarjeta>
                <div class="flex items-start gap-4">
                    <span @class([
                        'flex h-12 w-12 shrink-0 items-center justify-center rounded-xl',
                        'bg-marca-50 text-marca-700' => $stock->cantidad_kg > 0,
                        'bg-red-50 text-red-600' => $stock->cantidad_kg <= 0,
                    ])>
                        <x-icono nombre="bodega" />
                    </span>
                    <div class="min-w-0">
                        <h2 class="text-lg font-semibold text-slate-900">{{ $stock->tipo_concentrado }}</h2>
                        <p @class([
                            'mt-1 text-3xl font-bold tabular-nums',
                            'text-marca-700' => $stock->cantidad_kg > 0,
                            'text-red-600' => $stock->cantidad_kg <= 0,
                        ])>
                            {{ number_format($stock->cantidad_kg, 2, ',', '.') }} <span class="text-base font-medium text-slate-500">kg</span>
                        </p>
                        <p class="mt-1 text-sm text-slate-500">
                            ≈ {{ number_format($stock->cantidad_kg / 40, 1, ',', '.') }} bultos de 40 kg
                        </p>
                    </div>
                </div>
            </x-tarjeta>
        @endforeach
    </div>

    <x-modal id="modal-recepcion" titulo="Recepción de alimento">
        <x-formulario :action="route('admin.bodega.store')">
            <x-campo nombre="fecha" etiqueta="Fecha de recepción" tipo="date" :valor="now()->format('Y-m-d')" required />
            <x-campo nombre="proveedor" etiqueta="Proveedor" placeholder="Ej. nombre de la casa comercial" required />
            <x-campo nombre="tipo_concentrado" etiqueta="Tipo de concentrado" placeholder="Ej. Engorde 32 %" required />
            <x-campo nombre="lote" etiqueta="Lote de producción" required />

            <div class="grid grid-cols-2 gap-4">
                <x-campo nombre="bultos" etiqueta="Bultos" tipo="number" min="1" inputmode="numeric" required />
                <x-campo nombre="peso_bulto_kg" etiqueta="Kg por bulto" tipo="number" step="0.1" min="1" inputmode="decimal" valor="40" required />
            </div>

            <div class="grid grid-cols-2 gap-3 pt-2">
                <x-boton tipo="button" variante="secundario" data-cerrar-modal>Cancelar</x-boton>
                <x-boton>Guardar entrada</x-boton>
            </div>
        </x-formulario>
    </x-modal>
@endsection
