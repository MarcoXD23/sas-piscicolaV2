@extends('layouts.app')

@section('titulo', 'Bodega de alimento')

@section('contenido')
    @php
        $numero = fn ($valor, $decimales = 0) => number_format((float) $valor, $decimales, ',', '.');
        $estilos = [
            \App\Support\CoberturaBodega::AGOTADO => ['icono' => 'bg-red-50 text-red-600', 'cifra' => 'text-red-600', 'chip' => 'bg-red-100 text-red-800', 'texto' => 'Agotado'],
            \App\Support\CoberturaBodega::BAJO => ['icono' => 'bg-amber-50 text-amber-600', 'cifra' => 'text-amber-700', 'chip' => 'bg-amber-100 text-amber-900', 'texto' => 'Stock bajo'],
            \App\Support\CoberturaBodega::SUFICIENTE => ['icono' => 'bg-marca-50 text-marca-700', 'cifra' => 'text-marca-700', 'chip' => null, 'texto' => null],
        ];
    @endphp

    <x-encabezado titulo="Bodega de alimento" subtitulo="Inventario de concentrados y movimientos de la finca.">
        <x-boton tipo="button" data-abrir-modal="modal-recepcion">
            <x-icono nombre="mas" class="h-5 w-5" /> Recibir alimento
        </x-boton>
    </x-encabezado>

    @if ($cobertura->isEmpty())
        <x-vacio icono="bodega" mensaje="La bodega está vacía. Registra la primera entrada de alimento.">
            <x-boton tipo="button" data-abrir-modal="modal-recepcion">
                <x-icono nombre="mas" class="h-5 w-5" /> Recibir alimento
            </x-boton>
        </x-vacio>
    @endif

    <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ($cobertura as $item)
            @php
                $stock = $item['stock'];
                $estilo = $estilos[$item['estado']];
            @endphp
            <x-tarjeta>
                <div class="flex items-start gap-4">
                    <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl {{ $estilo['icono'] }}">
                        <x-icono nombre="bodega" />
                    </span>
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <h2 class="text-lg font-semibold text-slate-900">{{ $stock->tipo_concentrado }}</h2>
                            @if ($estilo['chip'])
                                <span class="rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $estilo['chip'] }}">{{ $estilo['texto'] }}</span>
                            @endif
                        </div>
                        <p class="mt-1 text-3xl font-bold tabular-nums {{ $estilo['cifra'] }}">
                            {{ $numero($stock->cantidad_kg, 2) }} <span class="text-base font-medium text-slate-500">kg</span>
                        </p>
                        <p class="mt-1 text-sm text-slate-500">
                            @if ($item['dias'] !== null)
                                Alcanza para unos <strong class="font-semibold text-slate-700">{{ $numero($item['dias'], 1) }} días</strong>
                                ({{ $numero($item['consumo_diario_kg'], 1) }} kg/día en promedio).
                            @else
                                Sin consumo en los últimos {{ \App\Support\CoberturaBodega::VENTANA_DIAS }} días.
                            @endif
                        </p>
                    </div>
                </div>
            </x-tarjeta>
        @endforeach
    </div>

    <section class="mt-8">
        <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
            <h2 class="text-lg font-semibold text-slate-900">Movimientos</h2>
            <nav class="flex gap-1 rounded-xl bg-slate-200/70 p-1 text-sm font-semibold" aria-label="Filtrar movimientos">
                @foreach ([null => 'Todos', 'entrada' => 'Entradas', 'salida' => 'Salidas'] as $valor => $texto)
                    <a href="{{ route('admin.bodega.index', array_filter(['tipo' => $valor ?: null])) }}"
                       @if ($tipo === ($valor ?: null)) aria-current="page" @endif
                       @class([
                           'flex min-h-10 items-center rounded-lg px-3',
                           'bg-white text-marca-800 shadow-sm' => $tipo === ($valor ?: null),
                           'text-slate-600' => $tipo !== ($valor ?: null),
                       ])>{{ $texto }}</a>
                @endforeach
            </nav>
        </div>

        <x-tabla :columnas="['Fecha', 'Movimiento', 'Concentrado', 'Cantidad', 'Detalle']" vacio="Aún no hay movimientos.">
            @foreach ($movimientos as $movimiento)
                @php $entrada = $movimiento->tipo_movimiento === 'entrada'; @endphp
                <tr>
                    <td data-label="Fecha">{{ $movimiento->fecha->format('d/m/Y') }}</td>
                    <td data-label="Movimiento">
                        <span @class([
                            'rounded-full px-2.5 py-0.5 text-sm font-semibold',
                            'bg-marca-100 text-marca-800' => $entrada,
                            'bg-slate-100 text-slate-700' => ! $entrada,
                        ])>{{ $entrada ? 'Entrada' : 'Salida' }}</span>
                    </td>
                    <td data-label="Concentrado">{{ $movimiento->tipo_concentrado }}</td>
                    <td data-label="Cantidad" class="font-semibold tabular-nums">{{ $entrada ? '+' : '−' }}{{ $numero($movimiento->cantidad_kg, 1) }} kg</td>
                    <td data-label="Detalle" class="text-sm text-slate-600">
                        @if ($entrada)
                            {{ $movimiento->proveedor }} · lote {{ $movimiento->lote }} · {{ $movimiento->bultos }} bultos
                        @else
                            Lago {{ $movimiento->lago?->nombre }}
                        @endif
                        · {{ $movimiento->usuario?->name }}
                    </td>
                </tr>
            @endforeach
        </x-tabla>

        @if ($movimientos->hasPages())
            <nav class="mt-4 flex items-center justify-between gap-3" aria-label="Páginas">
                @if ($movimientos->onFirstPage())
                    <span></span>
                @else
                    <x-boton variante="secundario" :href="$movimientos->previousPageUrl()">← Anteriores</x-boton>
                @endif
                <span class="text-sm text-slate-500">Página {{ $movimientos->currentPage() }} de {{ $movimientos->lastPage() }}</span>
                @if ($movimientos->hasMorePages())
                    <x-boton variante="secundario" :href="$movimientos->nextPageUrl()">Siguientes →</x-boton>
                @else
                    <span></span>
                @endif
            </nav>
        @endif
    </section>

    <x-modal id="modal-recepcion" titulo="Recepción de alimento">
        <x-formulario :action="route('admin.bodega.store')">
            <x-campo nombre="fecha" etiqueta="Fecha de recepción" tipo="date" :valor="now()->format('Y-m-d')" required />
            <x-campo nombre="proveedor" etiqueta="Proveedor" placeholder="Ej. nombre de la casa comercial" required />
            <x-campo nombre="tipo_concentrado" etiqueta="Tipo de concentrado" placeholder="Ej. Engorde 32 %" list="concentrados" required
                     ayuda="Si ya existe en la bodega, escríbelo igual para sumarlo al mismo stock." />
            <datalist id="concentrados">
                @foreach ($cobertura as $item)
                    <option value="{{ $item['stock']->tipo_concentrado }}"></option>
                @endforeach
            </datalist>
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
