@extends('layouts.app')

@section('titulo', 'Lagos y siembras')

@section('contenido')
    @php
        $esTecnico = auth()->user()->tieneRol('propietario') || auth()->user()->tieneRol('administrador');
        $numero = fn ($valor, $decimales = 0) => number_format($valor, $decimales, ',', '.');
    @endphp

    <x-encabezado titulo="Lagos y siembras" subtitulo="Estanques de la finca y sus ciclos productivos.">
        @if ($esTecnico)
            <x-boton tipo="button" data-abrir-modal="modal-lago">
                <x-icono nombre="mas" class="h-5 w-5" /> Nuevo lago
            </x-boton>
        @endif
    </x-encabezado>

    @if ($lagos->isEmpty())
        <x-vacio icono="lagos" mensaje="Aún no hay lagos registrados en la finca.">
            @if ($esTecnico)
                <x-boton tipo="button" data-abrir-modal="modal-lago">
                    <x-icono nombre="mas" class="h-5 w-5" /> Registrar el primer lago
                </x-boton>
            @endif
        </x-vacio>
    @endif

    <div class="grid gap-5 md:grid-cols-2 lg:grid-cols-3">
        @foreach ($lagos as $lago)
            @php
                $siembra = $lago->siembraActiva;
                $foto = $siembra ? \App\Models\Siembra::fotoEspecie($siembra->especie) : null;
            @endphp

            <x-tarjeta class="flex flex-col overflow-hidden !p-0">
                <div class="flex items-center gap-4 border-b border-slate-100 p-5">
                    @if ($foto)
                        <img src="{{ $foto }}" alt="{{ ucfirst($siembra->especie) }}" loading="lazy" decoding="async"
                             class="h-14 w-14 shrink-0 rounded-2xl object-cover ring-1 ring-slate-200">
                    @else
                        <span class="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl bg-marca-50 text-marca-600">
                            <x-icono :nombre="$siembra ? 'pez' : 'lagos'" class="h-7 w-7" />
                        </span>
                    @endif

                    <div class="min-w-0 flex-1">
                        <h2 class="truncate text-xl font-bold text-slate-900">{{ $lago->nombre }}</h2>
                        <p class="text-sm text-slate-500">
                            Lago en {{ $lago->tipo }}@if ($siembra) · {{ ucfirst($siembra->especie) }}@endif
                        </p>
                    </div>

                    <span @class([
                        'shrink-0 rounded-full px-3 py-1 text-xs font-semibold',
                        'bg-marca-100 text-marca-800' => $siembra,
                        'bg-slate-100 text-slate-600' => ! $siembra,
                    ])>{{ $siembra ? 'Activo' : 'Libre' }}</span>
                </div>

                @if ($siembra)
                    <dl class="grid grid-cols-2 gap-px bg-slate-100 text-center">
                        <div class="bg-white p-3">
                            <dt class="text-xs font-medium text-slate-500">Peces vivos</dt>
                            <dd class="text-lg font-bold tabular-nums text-slate-900">{{ $numero($siembra->peces_vivos) }}</dd>
                        </div>
                        <div class="bg-white p-3">
                            <dt class="text-xs font-medium text-slate-500">Biomasa</dt>
                            <dd class="text-lg font-bold tabular-nums text-slate-900">{{ $numero($siembra->biomasa_kg, 2) }} <span class="text-sm font-medium text-slate-500">kg</span></dd>
                        </div>
                        <div class="bg-white p-3">
                            <dt class="text-xs font-medium text-slate-500">Peso promedio</dt>
                            <dd class="text-lg font-bold tabular-nums text-slate-900">{{ $numero($siembra->peso_actual_g, 2) }} <span class="text-sm font-medium text-slate-500">g</span></dd>
                        </div>
                        <div class="bg-white p-3">
                            <dt class="text-xs font-medium text-slate-500">Días de cultivo</dt>
                            <dd class="text-lg font-bold tabular-nums text-slate-900">{{ $siembra->dias_cultivo }}</dd>
                        </div>
                    </dl>

                    <p class="px-5 pt-3 text-sm text-slate-500">Sembrado el {{ $siembra->fecha->format('d/m/Y') }}</p>

                    <div @class(['mt-auto grid gap-2 p-5', 'grid-cols-3' => $esTecnico, 'grid-cols-2' => ! $esTecnico])>
                        <x-boton tipo="button" class="min-h-16 flex-col !gap-1 !px-1 !py-2 !text-sm" data-abrir-modal="modal-racion-{{ $lago->id }}">
                            <x-icono nombre="alimentar" /> Alimentar
                        </x-boton>
                        <x-boton tipo="button" variante="secundario" class="min-h-16 flex-col !gap-1 !px-1 !py-2 !text-sm" data-abrir-modal="modal-mortalidad-{{ $siembra->id }}">
                            <x-icono nombre="mortalidad" /> Mortalidad
                        </x-boton>
                        @if ($esTecnico)
                            <x-boton tipo="button" variante="secundario" class="min-h-16 flex-col !gap-1 !px-1 !py-2 !text-sm" data-abrir-modal="modal-muestreo-{{ $siembra->id }}">
                                <x-icono nombre="biomasa" /> Muestreo
                            </x-boton>
                        @endif
                    </div>

                    <x-modal id="modal-racion-{{ $lago->id }}" titulo="Alimentar · {{ $lago->nombre }}">
                        <x-formulario :action="route('admin.raciones.store', $lago->id)">
                            <x-campo nombre="fecha" id="racion-fecha-{{ $lago->id }}" etiqueta="Fecha" tipo="date" :valor="now()->format('Y-m-d')" required />

                            @if ($stocksDisponibles->isEmpty())
                                <p class="flex items-start gap-2 rounded-xl bg-red-50 p-4 text-base text-red-700">
                                    <x-icono nombre="alerta" class="mt-0.5 h-5 w-5" /> No hay alimento en bodega.
                                </p>
                            @else
                                <x-seleccion nombre="tipo_concentrado" id="racion-concentrado-{{ $lago->id }}" etiqueta="Concentrado" required
                                             :opciones="$stocksDisponibles->mapWithKeys(fn ($stock) => [$stock->tipo_concentrado => $stock->tipo_concentrado.' ('.$numero($stock->cantidad_kg, 1).' kg disponibles)'])->all()" />
                            @endif

                            <x-campo nombre="cantidad_kg" id="racion-cantidad-{{ $lago->id }}" etiqueta="Cantidad (kg)" tipo="number" step="0.1" min="0.1" inputmode="decimal" required />

                            <div class="grid grid-cols-2 gap-3 pt-2">
                                <x-boton tipo="button" variante="secundario" data-cerrar-modal>Cancelar</x-boton>
                                <x-boton :disabled="$stocksDisponibles->isEmpty()">Guardar</x-boton>
                            </div>
                        </x-formulario>
                    </x-modal>

                    <x-modal id="modal-mortalidad-{{ $siembra->id }}" titulo="Mortalidad · {{ $lago->nombre }}">
                        <x-formulario :action="route('admin.mortalidades.store', $siembra->id)">
                            <x-campo nombre="fecha" id="mortalidad-fecha-{{ $siembra->id }}" etiqueta="Fecha" tipo="date" :valor="now()->format('Y-m-d')" required />
                            <x-campo nombre="cantidad" id="mortalidad-cantidad-{{ $siembra->id }}" etiqueta="Peces muertos" tipo="number" min="1" :max="$siembra->peces_vivos" inputmode="numeric" required
                                     ayuda="Hay {{ $numero($siembra->peces_vivos) }} peces vivos en este lago." />

                            <div class="grid grid-cols-2 gap-3 pt-2">
                                <x-boton tipo="button" variante="secundario" data-cerrar-modal>Cancelar</x-boton>
                                <x-boton variante="peligro">Registrar</x-boton>
                            </div>
                        </x-formulario>
                    </x-modal>

                    @if ($esTecnico)
                        <x-modal id="modal-muestreo-{{ $siembra->id }}" titulo="Muestreo · {{ $lago->nombre }}">
                            <x-formulario :action="route('admin.muestreos.store', $siembra->id)">
                                <x-campo nombre="fecha" id="muestreo-fecha-{{ $siembra->id }}" etiqueta="Fecha" tipo="date" :valor="now()->format('Y-m-d')" required />
                                <div class="grid grid-cols-2 gap-4">
                                    <x-campo nombre="peces_pesados" id="muestreo-peces-{{ $siembra->id }}" etiqueta="Peces pesados" tipo="number" min="1" inputmode="numeric" required />
                                    <x-campo nombre="peso_total_g" id="muestreo-peso-{{ $siembra->id }}" etiqueta="Peso total (g)" tipo="number" step="0.1" min="0.1" inputmode="decimal" required />
                                </div>

                                <div class="grid grid-cols-2 gap-3 pt-2">
                                    <x-boton tipo="button" variante="secundario" data-cerrar-modal>Cancelar</x-boton>
                                    <x-boton>Guardar</x-boton>
                                </div>
                            </x-formulario>
                        </x-modal>
                    @endif
                @else
                    <div class="mt-auto p-5">
                        <p class="mb-4 text-base text-slate-500">Este lago no tiene una siembra activa.</p>
                        @if ($esTecnico)
                            <x-boton tipo="button" variante="secundario" class="w-full" data-abrir-modal="modal-siembra-{{ $lago->id }}">
                                <x-icono nombre="pez" class="h-5 w-5" /> Registrar siembra
                            </x-boton>
                        @endif
                    </div>

                    @if ($esTecnico)
                        <x-modal id="modal-siembra-{{ $lago->id }}" titulo="Siembra en {{ $lago->nombre }}">
                            <x-formulario :action="route('admin.siembras.store', $lago->id)">
                                <x-seleccion nombre="especie" id="siembra-especie-{{ $lago->id }}" etiqueta="Especie" :opciones="$especies" required />
                                <x-campo nombre="fecha" id="siembra-fecha-{{ $lago->id }}" etiqueta="Fecha de siembra" tipo="date" :valor="now()->format('Y-m-d')" required />
                                <div class="grid grid-cols-2 gap-4">
                                    <x-campo nombre="cantidad_alevinos" id="siembra-alevinos-{{ $lago->id }}" etiqueta="Alevinos" tipo="number" min="1" inputmode="numeric" required />
                                    <x-campo nombre="peso_promedio_inicial_g" id="siembra-peso-{{ $lago->id }}" etiqueta="Peso inicial (g)" tipo="number" step="0.1" min="0.1" inputmode="decimal" required />
                                </div>

                                <div class="grid grid-cols-2 gap-3 pt-2">
                                    <x-boton tipo="button" variante="secundario" data-cerrar-modal>Cancelar</x-boton>
                                    <x-boton>Guardar</x-boton>
                                </div>
                            </x-formulario>
                        </x-modal>
                    @endif
                @endif
            </x-tarjeta>
        @endforeach
    </div>

    @if ($esTecnico)
        <x-modal id="modal-lago" titulo="Nuevo lago">
            <x-formulario :action="route('admin.lagos.store')">
                <x-campo nombre="nombre" id="lago-nombre" etiqueta="Nombre o código" required />
                <x-seleccion nombre="tipo" id="lago-tipo" etiqueta="Tipo" :opciones="$tipos" required />

                <div class="grid grid-cols-2 gap-3 pt-2">
                    <x-boton tipo="button" variante="secundario" data-cerrar-modal>Cancelar</x-boton>
                    <x-boton>Guardar</x-boton>
                </div>
            </x-formulario>
        </x-modal>
    @endif
@endsection
