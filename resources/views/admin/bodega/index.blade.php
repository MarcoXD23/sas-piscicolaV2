@extends('layouts.app')

@section('titulo', 'Bodega de Alimento')

@section('contenido')
    <div class="mb-6 flex flex-col items-start justify-between gap-4 sm:flex-row sm:items-center">
        <div>
            <h1 class="text-2xl font-bold text-slate-900">Bodega de Alimento</h1>
            <p class="mt-1 text-base text-slate-500">Gestión de inventario de concentrados.</p>
        </div>
        <x-boton x-data="" @click.prevent="document.getElementById('modal-recepcion').showModal()">
            + Recibir Alimento
        </x-boton>
    </div>

    <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
        @forelse ($stocks as $stock)
            <x-tarjeta>
                <div class="mb-2 flex items-start justify-between">
                    <h2 class="text-lg font-bold text-slate-900">{{ $stock->tipo_concentrado }}</h2>
                </div>
                <p class="text-3xl font-bold {{ $stock->cantidad_kg > 0 ? 'text-marca-600' : 'text-rose-600' }}">
                    {{ number_format($stock->cantidad_kg, 2, ',', '.') }} <span class="text-base text-slate-500 font-medium">kg</span>
                </p>
                <p class="mt-1 text-sm text-slate-500">
                    Aprox {{ number_format($stock->cantidad_kg / 40, 1, ',', '.') }} bultos (de 40kg)
                </p>
            </x-tarjeta>
        @empty
            <div class="col-span-full rounded-2xl bg-white p-8 text-center shadow-sm ring-1 ring-slate-200">
                <p class="text-base text-slate-500">El inventario está vacío. Registra una entrada de alimento.</p>
            </div>
        @endforelse
    </div>

    {{-- Modal Recepción --}}
    <dialog id="modal-recepcion" class="w-full max-w-md rounded-2xl p-0 backdrop:bg-slate-900/50 open:animate-in open:fade-in open:zoom-in-95">
        <x-tarjeta class="border-0 ring-0" titulo="Recepción de Alimento">
            <x-formulario :action="route('admin.bodega.store')">
                <x-campo nombre="fecha" etiqueta="Fecha de recepción" tipo="date" :valor="now()->format('Y-m-d')" required />
                <x-campo nombre="proveedor" etiqueta="Proveedor" placeholder="Ej. Solla, Italcol..." required />
                <x-campo nombre="tipo_concentrado" etiqueta="Tipo de concentrado" placeholder="Ej. Mojarra 32% Engorde" required />
                <x-campo nombre="lote" etiqueta="Lote de producción" required />
                
                <div class="grid grid-cols-2 gap-4">
                    <x-campo nombre="bultos" etiqueta="Bultos recibidos" tipo="number" min="1" required />
                    <x-campo nombre="peso_bulto_kg" etiqueta="Peso por bulto (kg)" tipo="number" step="0.1" min="1" valor="40" required />
                </div>

                <div class="mt-6 flex justify-end gap-3">
                    <x-boton variante="secundario" type="button" onclick="this.closest('dialog').close()">Cancelar</x-boton>
                    <x-boton>Guardar Entrada</x-boton>
                </div>
            </x-formulario>
        </x-tarjeta>
    </dialog>
@endsection
