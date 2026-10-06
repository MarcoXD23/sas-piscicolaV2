@extends('layouts.app')
@use('App\Support\Formato')

@section('titulo', 'Ventas')

@section('contenido')

    <x-encabezado titulo="Ventas de pescado" subtitulo="Pescado cosechado disponible, ventas y créditos por cobrar.">
        <x-boton tipo="button" data-abrir-modal="modal-venta" :disabled="$disponibles->isEmpty()">
            <x-icono nombre="mas" class="h-5 w-5" /> Nueva venta
        </x-boton>
    </x-encabezado>

    <div class="mb-6 grid grid-cols-2 gap-4 lg:grid-cols-3">
        <x-indicador etiqueta="Vendido este mes" :valor="Formato::pesos($vendidoMes)" />
        <x-indicador etiqueta="Kg vendidos este mes" :valor="Formato::numero($kgMes, 1)" unidad="kg" />
        <x-indicador etiqueta="Por cobrar" :valor="Formato::pesos($cartera)" class="col-span-2 lg:col-span-1" />
    </div>

    <div class="grid gap-6 lg:grid-cols-2">
        <x-tarjeta titulo="Pescado disponible" subtitulo="Kg cosechados que aún no se han vendido.">
            @forelse ($disponibles as $cosecha)
                <div class="flex items-center justify-between gap-3 border-t border-slate-100 py-3 first:border-t-0 first:pt-0">
                    <div class="min-w-0">
                        <p class="font-semibold text-slate-900">{{ $cosecha->siembra->nombre_especie }} · Lago {{ $cosecha->lago->nombre }}</p>
                        <p class="text-sm text-slate-500">Cosechado el {{ $cosecha->fecha->format('d/m/Y') }} · {{ Formato::numero($cosecha->peso_promedio_g) }} g en promedio</p>
                    </div>
                    <p class="shrink-0 text-lg font-bold tabular-nums text-slate-900">{{ Formato::numero($cosecha->kgDisponibles(), 1) }} <span class="text-sm font-medium text-slate-500">kg</span></p>
                </div>
            @empty
                <p class="text-base text-slate-500">No hay pescado cosechado sin vender. Las cosechas se registran en el detalle de cada lago.</p>
            @endforelse
        </x-tarjeta>

        <x-tarjeta titulo="Créditos por cobrar">
            @forelse ($creditos as $credito)
                <div class="flex items-center justify-between gap-3 border-t border-slate-100 py-3 first:border-t-0 first:pt-0">
                    <div class="min-w-0">
                        <p class="font-semibold text-slate-900">{{ $credito->cliente }}@if ($credito->comprador_id) <span class="text-sm font-normal text-slate-500">(empleado)</span>@endif</p>
                        <p class="text-sm text-slate-500">{{ $credito->fecha->format('d/m/Y') }} · {{ Formato::numero($credito->kg, 1) }} kg · total {{ Formato::pesos($credito->total) }}</p>
                    </div>
                    <div class="shrink-0 text-right">
                        <p class="font-bold tabular-nums text-slate-900">{{ Formato::pesos($credito->saldo) }}</p>
                        <button type="button" data-abrir-modal="abono-{{ $credito->id }}" class="min-h-11 text-sm font-semibold text-marca-700 hover:underline">Registrar abono</button>
                    </div>
                </div>

                <x-modal id="abono-{{ $credito->id }}" titulo="Abono de {{ $credito->cliente }}">
                    <p class="mb-4 text-base text-slate-600">Saldo pendiente: <strong class="text-slate-900">{{ Formato::pesos($credito->saldo) }}</strong></p>
                    <x-formulario :action="route('admin.ventas.abonar', $credito)">
                        <div class="grid grid-cols-2 gap-4">
                            <x-campo nombre="valor" id="abono-valor-{{ $credito->id }}" etiqueta="Valor ($)" tipo="number" min="1" :max="$credito->saldo" inputmode="numeric" required />
                            <x-campo nombre="fecha" id="abono-fecha-{{ $credito->id }}" etiqueta="Fecha" tipo="date" :valor="today()->toDateString()" required />
                        </div>
                        <x-seleccion nombre="medio" id="abono-medio-{{ $credito->id }}" etiqueta="Medio de pago" :opciones="$medios" required />
                        <div class="grid grid-cols-2 gap-3 pt-2">
                            <x-boton tipo="button" variante="secundario" data-cerrar-modal>Cancelar</x-boton>
                            <x-boton>Guardar</x-boton>
                        </div>
                    </x-formulario>
                </x-modal>
            @empty
                <p class="text-base text-slate-500">No hay créditos pendientes.</p>
            @endforelse
        </x-tarjeta>
    </div>

    <section class="mt-8">
        <h2 class="mb-3 text-lg font-semibold text-slate-900">Ventas</h2>
        <x-tabla :columnas="['Fecha', 'Cliente', 'Pescado', 'Kg', 'Precio/kg', 'Total', 'Pago']" vacio="Aún no hay ventas.">
            @foreach ($ventas as $venta)
                <tr>
                    <td data-label="Fecha">{{ $venta->fecha->format('d/m/Y') }}</td>
                    <td data-label="Cliente" class="font-medium">{{ $venta->cliente }}</td>
                    <td data-label="Pescado">Lago {{ $venta->cosecha->lago->nombre }}</td>
                    <td data-label="Kg" class="tabular-nums">{{ Formato::numero($venta->kg, 1) }}</td>
                    <td data-label="Precio/kg" class="tabular-nums">{{ Formato::pesos($venta->precio_kg) }}</td>
                    <td data-label="Total" class="font-semibold tabular-nums">{{ Formato::pesos($venta->total) }}</td>
                    <td data-label="Pago">
                        @if (! $venta->esCredito())
                            <span class="rounded-full bg-marca-100 px-2.5 py-0.5 text-sm font-semibold text-marca-800">Contado</span>
                        @elseif ($venta->saldo > 0)
                            <span class="rounded-full bg-amber-100 px-2.5 py-0.5 text-sm font-semibold text-amber-900">Debe {{ Formato::pesos($venta->saldo) }}</span>
                        @else
                            <span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-sm font-semibold text-slate-700">Crédito pagado</span>
                        @endif
                    </td>
                </tr>
            @endforeach
        </x-tabla>

        @if ($ventas->hasPages())
            <nav class="mt-4 flex items-center justify-between gap-3" aria-label="Páginas">
                @if ($ventas->onFirstPage()) <span></span> @else <x-boton variante="secundario" :href="$ventas->previousPageUrl()">← Anteriores</x-boton> @endif
                <span class="text-sm text-slate-500">Página {{ $ventas->currentPage() }} de {{ $ventas->lastPage() }}</span>
                @if ($ventas->hasMorePages()) <x-boton variante="secundario" :href="$ventas->nextPageUrl()">Siguientes →</x-boton> @else <span></span> @endif
            </nav>
        @endif
    </section>

    @if ($disponibles->isNotEmpty())
        <x-modal id="modal-venta" titulo="Nueva venta">
            <x-formulario :action="route('admin.ventas.store')">
                <x-seleccion nombre="cosecha_id" etiqueta="Pescado de" required
                             :opciones="$disponibles->mapWithKeys(fn ($c) => [$c->id => $c->siembra->nombre_especie.' · Lago '.$c->lago->nombre.' ('.Formato::numero($c->kgDisponibles(), 1).' kg disponibles)'])->all()" />
                <x-campo nombre="cliente" etiqueta="Cliente" placeholder="Nombre del comprador" ayuda="Si compra un empleado, elígelo abajo y deja este campo vacío." />
                <x-seleccion nombre="comprador_id" etiqueta="Empleado que compra (opcional)" :opciones="['' => 'No es un empleado'] + $empleados" />
                <div class="grid grid-cols-2 gap-4">
                    <x-campo nombre="kg" etiqueta="Kg" tipo="number" step="0.01" min="0.1" inputmode="decimal" required />
                    <x-campo nombre="precio_kg" etiqueta="Precio por kg ($)" tipo="number" min="1" inputmode="numeric" required />
                    <x-campo nombre="fecha" etiqueta="Fecha" tipo="date" :valor="today()->toDateString()" required />
                    <x-seleccion nombre="forma_pago" etiqueta="Pago" :opciones="$formasPago" required />
                </div>

                <div class="grid grid-cols-2 gap-3 pt-2">
                    <x-boton tipo="button" variante="secundario" data-cerrar-modal>Cancelar</x-boton>
                    <x-boton>Registrar venta</x-boton>
                </div>
            </x-formulario>
        </x-modal>
    @endif
@endsection
