@extends('layouts.app')

@section('titulo', 'Lagos y Siembras')

@section('contenido')
    <div class="mb-6 flex flex-col items-start justify-between gap-4 sm:flex-row sm:items-center">
        <div>
            <h1 class="text-2xl font-bold text-slate-900">Lagos y Siembras</h1>
            <p class="mt-1 text-base text-slate-500">Gestión de estanques y sus ciclos productivos.</p>
        </div>
        @if(auth()->user()->tieneRol('propietario') || auth()->user()->tieneRol('administrador'))
            <x-boton x-data="" @click.prevent="document.getElementById('modal-lago').showModal()">
                + Registrar Nuevo Lago
            </x-boton>
        @endif
    </div>

    <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
        @forelse ($lagos as $lago)
            <x-tarjeta>
                <div class="mb-4 flex items-start justify-between">
                    <div>
                        <h2 class="text-xl font-bold text-slate-900">{{ $lago->nombre }}</h2>
                        <span class="inline-block rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-semibold capitalize text-slate-700">
                            {{ $lago->tipo }}
                        </span>
                    </div>
                </div>

                @if ($lago->siembraActiva)
                    <div class="space-y-2 text-sm text-slate-600">
                        <p><strong class="font-medium text-slate-900">Especie:</strong> <span class="capitalize">{{ $lago->siembraActiva->especie }}</span></p>
                        <p><strong class="font-medium text-slate-900">Fecha siembra:</strong> {{ $lago->siembraActiva->fecha->format('d/m/Y') }} ({{ $lago->siembraActiva->dias_cultivo }} días)</p>
                        <p><strong class="font-medium text-slate-900">Peces vivos:</strong> {{ number_format($lago->siembraActiva->peces_vivos, 0, ',', '.') }}</p>
                        <p><strong class="font-medium text-slate-900">Peso actual:</strong> {{ number_format($lago->siembraActiva->peso_actual_g, 2, ',', '.') }} g</p>
                        <p><strong class="font-medium text-slate-900">Biomasa:</strong> {{ number_format($lago->siembraActiva->biomasa_kg, 2, ',', '.') }} kg</p>
                    </div>
                    
                    <div class="mt-4 flex flex-wrap gap-2 pt-4 border-t border-slate-100">
                        <button type="button" class="text-sm font-medium text-emerald-600 hover:text-emerald-700" onclick="document.getElementById('modal-racion-{{ $lago->id }}').showModal()">
                            Alimentar
                        </button>
                        <span class="text-slate-300">|</span>
                        <button type="button" class="text-sm font-medium text-rose-600 hover:text-rose-700" onclick="document.getElementById('modal-mortalidad-{{ $lago->siembraActiva->id }}').showModal()">
                            Mortalidad
                        </button>
                        @if(auth()->user()->tieneRol('propietario') || auth()->user()->tieneRol('administrador'))
                            <span class="text-slate-300">|</span>
                            <button type="button" class="text-sm font-medium text-marca-600 hover:text-marca-700" onclick="document.getElementById('modal-muestreo-{{ $lago->siembraActiva->id }}').showModal()">
                                Muestreo
                            </button>
                        @endif
                    </div>

                    {{-- Modal Ración --}}
                    <dialog id="modal-racion-{{ $lago->id }}" class="w-full max-w-md rounded-2xl p-0 backdrop:bg-slate-900/50 open:animate-in open:fade-in open:zoom-in-95">
                        <x-tarjeta class="border-0 ring-0" titulo="Alimentar - {{ $lago->nombre }}">
                            <x-formulario :action="route('admin.raciones.store', $lago->id)">
                                <x-campo nombre="fecha" etiqueta="Fecha" tipo="date" :valor="now()->format('Y-m-d')" required />
                                
                                <div class="mb-4">
                                    <label class="mb-1.5 block text-base font-medium text-slate-700">Tipo de concentrado disponible</label>
                                    @php
                                        $stocksDisponibles = \App\Models\StockAlimento::where('cantidad_kg', '>', 0)->get();
                                    @endphp
                                    @if($stocksDisponibles->isEmpty())
                                        <div class="rounded-lg bg-rose-50 p-3 text-sm text-rose-700">
                                            No hay alimento en bodega.
                                        </div>
                                    @else
                                        <select name="tipo_concentrado" required class="block w-full min-h-12 rounded-xl border border-slate-300 bg-white px-4 py-3 text-base text-slate-900 shadow-sm focus:border-marca-500 focus:outline-none focus:ring-2 focus:ring-marca-200">
                                            @foreach ($stocksDisponibles as $stock)
                                                <option value="{{ $stock->tipo_concentrado }}">{{ $stock->tipo_concentrado }} (Dispo: {{ number_format($stock->cantidad_kg, 1, ',', '.') }} kg)</option>
                                            @endforeach
                                        </select>
                                    @endif
                                </div>

                                <x-campo nombre="cantidad_kg" etiqueta="Cantidad a suministrar (kg)" tipo="number" step="0.1" min="0.1" required />
                                
                                <div class="mt-6 flex justify-end gap-3">
                                    <x-boton variante="secundario" type="button" onclick="this.closest('dialog').close()">Cancelar</x-boton>
                                    <x-boton {{ $stocksDisponibles->isEmpty() ? 'disabled' : '' }}>Guardar Ración</x-boton>
                                </div>
                            </x-formulario>
                        </x-tarjeta>
                    </dialog>

                    {{-- Modal Mortalidad --}}
                    <dialog id="modal-mortalidad-{{ $lago->siembraActiva->id }}" class="w-full max-w-md rounded-2xl p-0 backdrop:bg-slate-900/50 open:animate-in open:fade-in open:zoom-in-95">
                        <x-tarjeta class="border-0 ring-0" titulo="Registrar Mortalidad - {{ $lago->nombre }}">
                            <x-formulario :action="route('admin.mortalidades.store', $lago->siembraActiva->id)">
                                <x-campo nombre="fecha" etiqueta="Fecha" tipo="date" :valor="now()->format('Y-m-d')" required />
                                <x-campo nombre="cantidad" etiqueta="Cantidad de peces muertos" tipo="number" min="1" max="{{ $lago->siembraActiva->peces_vivos }}" required />
                                <div class="mt-6 flex justify-end gap-3">
                                    <x-boton variante="secundario" type="button" onclick="this.closest('dialog').close()">Cancelar</x-boton>
                                    <x-boton>Guardar Mortalidad</x-boton>
                                </div>
                            </x-formulario>
                        </x-tarjeta>
                    </dialog>

                    {{-- Modal Muestreo --}}
                    @if(auth()->user()->tieneRol('propietario') || auth()->user()->tieneRol('administrador'))
                    <dialog id="modal-muestreo-{{ $lago->siembraActiva->id }}" class="w-full max-w-md rounded-2xl p-0 backdrop:bg-slate-900/50 open:animate-in open:fade-in open:zoom-in-95">
                        <x-tarjeta class="border-0 ring-0" titulo="Registrar Muestreo - {{ $lago->nombre }}">
                            <x-formulario :action="route('admin.muestreos.store', $lago->siembraActiva->id)">
                                <x-campo nombre="fecha" etiqueta="Fecha" tipo="date" :valor="now()->format('Y-m-d')" required />
                                <div class="grid grid-cols-2 gap-4">
                                    <x-campo nombre="peces_pesados" etiqueta="Peces pesados" tipo="number" min="1" required />
                                    <x-campo nombre="peso_total_g" etiqueta="Peso total (g)" tipo="number" step="0.1" min="0.1" required />
                                </div>
                                <div class="mt-6 flex justify-end gap-3">
                                    <x-boton variante="secundario" type="button" onclick="this.closest('dialog').close()">Cancelar</x-boton>
                                    <x-boton>Guardar Muestreo</x-boton>
                                </div>
                            </x-formulario>
                        </x-tarjeta>
                    </dialog>
                    @endif
                @else
                    <div class="rounded-xl border border-dashed border-slate-300 bg-slate-50 p-4 text-center">
                        <p class="mb-3 text-sm text-slate-500">Lago sin siembra activa</p>
                        @if(auth()->user()->tieneRol('propietario') || auth()->user()->tieneRol('administrador'))
                            <x-boton variante="secundario" class="!py-2 !text-sm" x-data="" @click.prevent="document.getElementById('modal-siembra-{{ $lago->id }}').showModal()">
                                Registrar siembra
                            </x-boton>
                        @endif
                    </div>

                    {{-- Modal Siembra --}}
                    <dialog id="modal-siembra-{{ $lago->id }}" class="w-full max-w-md rounded-2xl p-0 backdrop:bg-slate-900/50 open:animate-in open:fade-in open:zoom-in-95">
                        <x-tarjeta class="border-0 ring-0" titulo="Registrar siembra en {{ $lago->nombre }}">
                            <x-formulario :action="route('admin.siembras.store', $lago->id)">
                                <div>
                                    <label class="mb-1.5 block text-base font-medium text-slate-700">Especie</label>
                                    <select name="especie" required class="block w-full min-h-12 rounded-xl border border-slate-300 bg-white px-4 py-3 text-base text-slate-900 shadow-sm capitalize focus:border-marca-500 focus:outline-none focus:ring-2 focus:ring-marca-200">
                                        @foreach ($especies as $especie)
                                            <option value="{{ $especie }}">{{ $especie }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <x-campo nombre="fecha" etiqueta="Fecha de siembra" tipo="date" :valor="now()->format('Y-m-d')" required />
                                <div class="grid grid-cols-2 gap-4">
                                    <x-campo nombre="cantidad_alevinos" etiqueta="Alevinos" tipo="number" min="1" required />
                                    <x-campo nombre="peso_promedio_inicial_g" etiqueta="Peso inicial (g)" tipo="number" step="0.1" min="0.1" required />
                                </div>

                                <div class="mt-6 flex justify-end gap-3">
                                    <x-boton variante="secundario" type="button" onclick="this.closest('dialog').close()">Cancelar</x-boton>
                                    <x-boton>Guardar</x-boton>
                                </div>
                            </x-formulario>
                        </x-tarjeta>
                    </dialog>
                @endif
            </x-tarjeta>
        @empty
            <div class="col-span-full rounded-2xl bg-white p-8 text-center shadow-sm ring-1 ring-slate-200">
                <p class="text-base text-slate-500">No hay lagos registrados en la finca.</p>
            </div>
        @endforelse
    </div>

    {{-- Modal Lago --}}
    <dialog id="modal-lago" class="w-full max-w-md rounded-2xl p-0 backdrop:bg-slate-900/50 open:animate-in open:fade-in open:zoom-in-95">
        <x-tarjeta class="border-0 ring-0" titulo="Registrar Nuevo Lago">
            <x-formulario :action="route('admin.lagos.store')">
                <x-campo nombre="nombre" etiqueta="Nombre o Código" required />
                <div>
                    <label class="mb-1.5 block text-base font-medium text-slate-700">Tipo</label>
                    <select name="tipo" required class="block w-full min-h-12 rounded-xl border border-slate-300 bg-white px-4 py-3 text-base text-slate-900 shadow-sm capitalize focus:border-marca-500 focus:outline-none focus:ring-2 focus:ring-marca-200">
                        @foreach ($tipos as $tipo)
                            <option value="{{ $tipo }}">{{ $tipo }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="mt-6 flex justify-end gap-3">
                    <x-boton variante="secundario" type="button" onclick="this.closest('dialog').close()">Cancelar</x-boton>
                    <x-boton>Guardar</x-boton>
                </div>
            </x-formulario>
        </x-tarjeta>
    </dialog>
@endsection
