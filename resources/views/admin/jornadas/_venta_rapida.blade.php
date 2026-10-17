{{-- Venta rápida de la jornada (F6c): por especie, sin elegir lago. Recibe $jornada y $venta (listas de
     JornadaController::datosVenta más las especies). El precio sale de Ajustes › Precios: aquí no se escribe. --}}
@php
    use App\Http\Controllers\Admin\VentaJornadaController;
    use App\Support\Formato;

    $esteVolvio = old('_formulario') === 'venta';
    $tipo = $esteVolvio ? old('tipo_comprador', VentaJornadaController::PARTICULAR) : VentaJornadaController::PARTICULAR;
    $lineasPrecio = $venta['especies']->map(fn ($nombre, $id) => isset($venta['precios'][$id])
        ? ($venta['precios'][$id]->entra_en_cupo
            ? __('ventas.rapida.precio_linea_cupo', ['especie' => $nombre, 'general' => Formato::dinero($venta['precios'][$id]->precio_general), 'cupo' => Formato::dinero($venta['precios'][$id]->precio_cupo)])
            : __('ventas.rapida.precio_linea', ['especie' => $nombre, 'general' => Formato::dinero($venta['precios'][$id]->precio_general)]))
        : __('ventas.rapida.sin_precio', ['especie' => $nombre]))->implode(' · ');
@endphp

<details class="mt-4 rounded-ficha bg-papel ring-1 ring-renglon" @if ($esteVolvio) open @endif>
    <summary class="flex min-h-12 cursor-pointer items-center gap-2 px-4 font-semibold text-marca-700">
        <x-icono nombre="mas" class="h-5 w-5" /> {{ __('ventas.rapida.nueva') }}
    </summary>

    <x-formulario :action="route('admin.jornadas.ventas.store', $jornada)" class="p-4">
        <input type="hidden" name="_formulario" value="venta">
        <p class="text-sm text-tinta-suave">{{ __('ventas.rapida.precios') }}{{ $lineasPrecio }}</p>

        <x-seleccion nombre="especie_id" id="venta-especie" :etiqueta="__('ventas.rapida.especie')" :opciones="$venta['especies']->all()" required />

        <fieldset>
            <legend class="mb-1.5 text-base font-semibold text-tinta">{{ __('ventas.rapida.comprador') }}</legend>
            <div class="grid grid-cols-1 gap-2 sm:grid-cols-3">
                @foreach (VentaJornadaController::TIPOS as $opcion)
                    <label class="flex min-h-12 cursor-pointer items-center gap-2 rounded-ficha bg-ficha px-3 ring-1 ring-renglon has-checked:ring-2 has-checked:ring-marca-700">
                        <input type="radio" name="tipo_comprador" value="{{ $opcion }}" class="h-5 w-5 accent-marca-700" @checked($tipo === $opcion)>
                        {{ __('ventas.rapida.tipos.'.$opcion) }}
                    </label>
                @endforeach
            </div>
        </fieldset>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <x-campo nombre="cliente" id="venta-cliente" :etiqueta="__('ventas.rapida.cliente')" maxlength="255" />
            <x-seleccion nombre="comprador_id" id="venta-fijo" :etiqueta="__('ventas.rapida.fijo')" :opciones="['' => '—'] + $venta['fijos']" />
            <x-seleccion nombre="tercero_id" id="venta-jornalero" :etiqueta="__('ventas.rapida.jornalero')" :opciones="['' => '—'] + $venta['jornaleros']" />
        </div>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <x-campo nombre="kg" id="venta-kg" :etiqueta="__('ventas.rapida.kg')" tipo="number" step="0.01" min="0.1" inputmode="decimal" required />
            <x-seleccion nombre="forma_pago" id="venta-forma" :etiqueta="__('ventas.rapida.forma_pago')" :opciones="__('ventas.rapida.formas')" />
            <x-seleccion nombre="recibio_id" id="venta-recibio" :etiqueta="__('ventas.rapida.recibio')" :opciones="['' => '—'] + $venta['fijos']" />
        </div>

        <x-boton>{{ __('ventas.rapida.nueva') }}</x-boton>
    </x-formulario>
</details>
