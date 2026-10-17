{{-- Formulario de un pesaje de la jornada, con pesadas o con el total: el entero de un lago (por especie) o el limpio
     (por presentación). Usa el mismo JS de la cosecha (resources/js/pesaje.js; las cuentas las rehace el servidor con
     CalculoPesaje). Recibe $pesoCanasta, $pesoCanastilla y $formulario:
     - sufijo: único en la página (ids de los campos y para reabrir solo el formulario que volvió con errores);
     - accion: URL; ocultos: campos ocultos (nombre => valor); limpio: true para el limpio;
     - selector: nombre, etiqueta y opciones (id => texto); con una sola opción va oculta y se muestra su texto. --}}
@php
    use App\Support\CalculoPesaje;
    use App\Support\Region;

    [$decimal, $miles] = Region::separadores();
    $sufijo = $formulario['sufijo'];
    $selector = $formulario['selector'];
    $opciones = collect($selector['opciones']);
    $deLimpio = ! empty($formulario['limpio']);
    // Solo el formulario que se envió se reabre con lo escrito
    $esteVolvio = old('_formulario') === $sufijo;
    $filasPrevias = $esteVolvio ? collect(old('pesadas', []))->map(fn ($fila) => [
        'tipo' => in_array($fila['tipo'] ?? null, CalculoPesaje::TIPOS, true) ? $fila['tipo'] : CalculoPesaje::CANASTA,
        'cantidad' => (string) ($fila['cantidad'] ?? ''),
        'peso' => (string) ($fila['peso_pesado'] ?? ''),
    ])->values()->all() : [];
    $modo = $esteVolvio && old('kg') !== null && old('pesadas') === null ? 'total' : 'pesadas';
    $configPesaje = ['modo' => $modo, 'filas' => $filasPrevias, 'decimal' => $decimal, 'miles' => $miles];
    $erroresPesadas = $esteVolvio ? collect($errors->getMessages())->filter(fn ($m, $clave) => str_starts_with($clave, 'pesadas'))->flatten()->unique() : collect();
    $clasesEntrada = 'block min-h-12 w-full rounded-ficha border border-renglon-fuerte bg-ficha px-3 text-base tabular-nums text-tinta transition-[border-color,box-shadow] duration-150 hover:border-tinta-suave focus:border-marca-700 focus:outline-none focus:ring-4 focus:ring-marca-200/70';
    $textoPeso = $deLimpio ? __('jornadas.peso_limpio') : __('cosechas.peso_entero');
    $textoTotal = $deLimpio ? __('jornadas.total_limpio') : __('cosechas.total_entero');
    $textoTotalKg = $deLimpio ? __('jornadas.total_limpio_kg') : __('cosechas.total_entero_kg');
@endphp

<details class="mt-5 rounded-ficha bg-papel ring-1 ring-renglon" @if ($esteVolvio) open @endif>
    <summary class="flex min-h-12 cursor-pointer items-center gap-2 px-4 font-semibold text-marca-700">
        <x-icono nombre="mas" class="h-5 w-5" /> {{ $formulario['titulo'] }}
    </summary>

    <x-formulario :action="$formulario['accion']" class="p-4" data-pesaje>
        <script type="application/json" data-pesaje-config>@json($configPesaje)</script>
        <input type="hidden" name="_formulario" value="{{ $sufijo }}">
        @foreach ($formulario['ocultos'] as $nombre => $valor)
            <input type="hidden" name="{{ $nombre }}" value="{{ $valor }}">
        @endforeach

        @if ($opciones->count() === 1)
            <input type="hidden" name="{{ $selector['nombre'] }}" value="{{ $opciones->keys()->first() }}">
            <p class="text-base">{{ $selector['etiqueta'] }}: <span class="font-bold">{{ $opciones->first() }}</span></p>
        @else
            <x-seleccion :nombre="$selector['nombre']" :id="$selector['nombre'].'-'.$sufijo" :etiqueta="$selector['etiqueta']" :opciones="$opciones->all()"
                         :valor="$esteVolvio ? old($selector['nombre']) : null" required />
        @endif

        <fieldset>
            <legend class="mb-1.5 text-base font-semibold text-tinta">{{ __('cosechas.como_se_peso') }}</legend>
            <div class="grid grid-cols-2 gap-1 rounded-ficha bg-papel-hondo p-1 ring-1 ring-renglon">
                @foreach (['pesadas' => __('cosechas.modo_pesadas'), 'total' => __('cosechas.modo_total')] as $valor => $texto)
                    <label class="flex min-h-12 cursor-pointer items-center justify-center rounded-md px-2 text-center text-base text-tinta-suave transition-colors has-checked:bg-ficha has-checked:font-bold has-checked:text-tinta has-checked:shadow-ficha has-focus-visible:ring-2 has-focus-visible:ring-marca-700">
                        <input type="radio" name="modo_pesaje" value="{{ $valor }}" class="sr-only" data-modo @checked($modo === $valor)>
                        {{ $texto }}
                    </label>
                @endforeach
            </div>
        </fieldset>

        <div data-zona="total" @if ($modo !== 'total') hidden @endif>
            <x-campo nombre="kg" :id="'kg-'.$sufijo" :etiqueta="$textoTotalKg" tipo="number" step="0.01" min="0.1" inputmode="decimal"
                     :valor="$esteVolvio ? old('kg') : null" :disabled="$modo !== 'total'" :required="$modo === 'total'" />
        </div>

        <div data-zona="pesadas" class="space-y-4" @if ($modo !== 'pesadas') hidden @endif>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <x-campo nombre="peso_canasta_kg" :id="'canasta-'.$sufijo" :etiqueta="__('cosechas.peso_canasta_vacia')" tipo="number" step="0.01" min="0.01" max="20" inputmode="decimal"
                         :valor="(string) ($esteVolvio ? old('peso_canasta_kg', $pesoCanasta) : $pesoCanasta)" :disabled="$modo !== 'pesadas'" />
                <x-campo nombre="peso_canastilla_kg" :id="'canastilla-'.$sufijo" :etiqueta="__('cosechas.peso_canastilla_vacia')" tipo="number" step="0.01" min="0.01" max="20" inputmode="decimal"
                         :valor="(string) ($esteVolvio ? old('peso_canastilla_kg', $pesoCanastilla) : $pesoCanastilla)" :disabled="$modo !== 'pesadas'" />
            </div>

            @if ($erroresPesadas->isNotEmpty())
                <ul class="space-y-1 rounded-ficha border border-alerta-borde bg-alerta-fondo p-4 text-base font-semibold text-alerta" role="alert">
                    @foreach ($erroresPesadas as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            @endif

            <ol class="space-y-3" data-pesadas></ol>
            <template data-plantilla-pesada>
                <li class="grid grid-cols-2 items-end gap-3 rounded-ficha bg-ficha p-3 ring-1 ring-renglon sm:grid-cols-[4rem_9rem_1fr_1fr_1fr_1fr_3rem] sm:gap-2 sm:p-2">
                    <p class="col-span-2 text-base font-bold sm:col-span-1 sm:self-center">
                        <span class="sm:hidden">{{ __('cosechas.pesada') }}</span> <span data-numero></span>
                    </p>
                    <label class="block">
                        <span class="mb-1 block text-sm font-semibold text-tinta-suave">{{ __('cosechas.tipo') }}</span>
                        <select data-pesada="tipo" class="{{ $clasesEntrada }}">
                            @foreach (CalculoPesaje::TIPOS as $tipo)
                                <option value="{{ $tipo }}">{{ __('cosechas.tipos.'.$tipo) }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="block">
                        <span class="mb-1 block text-sm font-semibold text-tinta-suave">{{ __('cosechas.cantidad') }}</span>
                        <input type="number" min="1" max="{{ CalculoPesaje::MAX_CANTIDAD }}" step="1" inputmode="numeric" data-pesada="cantidad" class="{{ $clasesEntrada }}">
                    </label>
                    <label class="block">
                        <span class="mb-1 block text-sm font-semibold text-tinta-suave">{{ __('cosechas.peso_pesado') }}</span>
                        <input type="number" min="0.01" max="{{ CalculoPesaje::MAX_PESO_PESADA_KG }}" step="0.01" inputmode="decimal" data-pesada="peso" class="{{ $clasesEntrada }}">
                    </label>
                    <p class="sm:self-center">
                        <span class="block text-sm font-semibold text-tinta-suave">{{ __('cosechas.descuento') }}</span>
                        <span class="text-base tabular-nums text-tinta-suave" data-descuento>—</span>
                    </p>
                    <p class="sm:self-center">
                        <span class="block text-sm font-semibold text-tinta-suave">{{ $textoPeso }}</span>
                        <span class="text-base font-bold tabular-nums" data-entero>—</span>
                    </p>
                    <button type="button" data-quitar data-texto="{{ __('cosechas.quitar_pesada', ['numero' => '__N__']) }}"
                            class="col-span-2 flex h-12 items-center justify-center rounded-ficha text-tinta-suave ring-1 ring-renglon transition-colors hover:bg-alerta-fondo hover:text-alerta sm:col-span-1 sm:w-12 sm:ring-0">
                        <x-icono nombre="basura" class="h-5 w-5" />
                    </button>
                </li>
            </template>

            <x-boton tipo="button" variante="secundario" class="w-full sm:w-auto" data-agregar-pesada>
                <x-icono nombre="mas" class="h-5 w-5" /> {{ __('cosechas.anadir_pesada') }}
            </x-boton>

            <dl class="grid grid-cols-2 gap-x-4 gap-y-3 rounded-ficha border border-renglon bg-papel-hondo p-4 text-base sm:grid-cols-3" aria-live="polite">
                <div><dt class="text-sm text-tinta-suave">{{ __('cosechas.total_pesadas') }}</dt><dd class="text-lg font-bold tabular-nums" data-total="pesadas">0</dd></div>
                <div class="col-span-2 sm:col-span-1"><dt class="text-sm text-tinta-suave">{{ __('cosechas.total_cantidad') }}</dt>
                    <dd class="text-lg font-bold tabular-nums" data-total="cantidad" data-texto="{{ __('cosechas.cantidad_por_tipo') }}"></dd></div>
                <div><dt class="text-sm text-tinta-suave">{{ __('cosechas.total_pesado') }}</dt><dd class="text-lg font-bold tabular-nums" data-total="pesado">—</dd></div>
                <div><dt class="text-sm text-tinta-suave">{{ __('cosechas.total_descuento') }}</dt><dd class="text-lg font-bold tabular-nums" data-total="descuento">—</dd></div>
                <div class="col-span-2 sm:col-span-1"><dt class="text-sm font-bold text-tinta">{{ $textoTotal }}</dt><dd class="text-cifra font-bold tabular-nums text-marca-800" data-total="entero">—</dd></div>
            </dl>
            <p class="text-base font-semibold" data-linea hidden></p>
        </div>

        <p data-aviso-pesaje hidden role="alert" class="rounded-ficha border border-alerta-borde bg-alerta-fondo p-4 text-base font-semibold text-alerta"></p>
        <x-boton class="w-full sm:w-auto">{{ __('jornadas.guardar_pesaje') }}</x-boton>
    </x-formulario>
</details>
