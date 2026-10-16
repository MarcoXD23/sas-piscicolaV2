@extends('layouts.app')

@section('titulo', __('cosechas.titulo_crear'))

@php
    use App\Support\CalculoPesaje;
    use App\Support\Formato;
    use App\Support\Region;

    [$decimal, $miles] = Region::separadores();

    // Si el formulario volvió con errores, se reabre con lo que se escribió
    $filasPrevias = collect(old('pesadas', []))->map(fn ($fila) => [
        'tipo' => in_array($fila['tipo'] ?? null, CalculoPesaje::TIPOS, true) ? $fila['tipo'] : CalculoPesaje::CANASTA,
        'cantidad' => (string) ($fila['cantidad'] ?? ''),
        'peso' => (string) ($fila['peso_pesado'] ?? ''),
    ])->values()->all();
    $modo = old('kg') !== null && old('pesadas') === null ? 'total' : 'pesadas';
    // Lo que necesita resources/js/pesaje.js (va en un <script type="application/json">, que la CSP permite)
    $configPesaje = ['modo' => $modo, 'filas' => $filasPrevias, 'decimal' => $decimal, 'miles' => $miles];

    // Opciones id de la siembra => texto (con un ciclo: flatMap renumeraría las claves y se perderían los ids)
    $opcionesSiembra = [];
    foreach ($lagos as $lago) {
        foreach ($lago->siembrasActivas as $siembra) {
            $opcionesSiembra[$siembra->id] = __('cosechas.opcion_siembra', [
                'lago' => $lago->nombre, 'especie' => $siembra->nombre_especie,
                'fecha' => $siembra->fecha->format('d/m/Y'), 'peces' => Formato::numero($siembra->peces_vivos),
            ]);
        }
    }

    $erroresPesadas = collect($errors->getMessages())->filter(fn ($m, $clave) => str_starts_with($clave, 'pesadas'))->flatten()->unique();
@endphp

@section('contenido')
    <x-encabezado :titulo="__('cosechas.titulo_crear')" :subtitulo="__('cosechas.subtitulo_crear')" />

    @if ($lagos->isEmpty())
        <x-vacio icono="lagos" :mensaje="__('cosechas.sin_lagos')" />
    @else
        {{-- Tabla de pesadas en JS sin Alpine (resources/js/pesaje.js; las cuentas en pesaje-logica.js): la
             Content-Security-Policy no permite evaluar código escrito en el HTML --}}
        <x-formulario :action="route('admin.cosechas.store')" class="max-w-4xl" data-pesaje>
            <script type="application/json" data-pesaje-config>@json($configPesaje)</script>

            {{-- A. Encabezado --}}
            <x-tarjeta>
                <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <x-seleccion nombre="siembra_id" :etiqueta="__('cosechas.lago_pescado')" :opciones="$opcionesSiembra" :valor="$siembraElegida"
                                     :ayuda="__('cosechas.lago_pescado_ayuda')" required />
                    </div>
                    <div class="sm:col-span-2">
                        <x-campo nombre="destino" :etiqueta="__('cosechas.destino')" :ayuda="__('cosechas.destino_ayuda')" list="destinos-usados"
                                 maxlength="120" autocomplete="off" required />
                        <datalist id="destinos-usados">
                            @foreach ($destinos as $destino)
                                <option value="{{ $destino }}"></option>
                            @endforeach
                        </datalist>
                    </div>
                    <x-campo nombre="fecha" :etiqueta="__('cosechas.fecha')" tipo="date" :valor="Region::hoy()->toDateString()" :max="Region::hoy()->toDateString()"
                             :ayuda="__('cosechas.fecha_ayuda')" required />
                    <div>
                        <p class="mb-1.5 block text-base font-semibold text-tinta">{{ __('cosechas.hora') }}</p>
                        <p class="flex min-h-12 items-center rounded-ficha border border-renglon bg-papel-hondo px-4 text-base font-bold tabular-nums text-tinta">{{ Region::ahora()->formatoHora('H:i') }}</p>
                        <p class="mt-1.5 text-sm text-tinta-suave">{{ __('cosechas.hora_ayuda', ['hora' => Region::ahora()->formatoHora('H:i')]) }}</p>
                    </div>
                    <x-seleccion nombre="despachado_por_id" :etiqueta="__('cosechas.despacho')" :opciones="$personal->pluck('name', 'id')->all()"
                                 :valor="auth()->id()" :ayuda="__('cosechas.despacho_ayuda')" />
                    <x-campo nombre="peces" :etiqueta="__('cosechas.peces')" tipo="number" min="1" inputmode="numeric" required />
                </div>
                <x-aviso tono="info" icono="pez" class="mt-5">{{ __('cosechas.si_cosechas_todo') }}</x-aviso>
            </x-tarjeta>

            {{-- Modo: tabla de pesadas o solo el total entero --}}
            <x-tarjeta>
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

                <div data-zona="total" class="mt-5" @if ($modo !== 'total') hidden @endif>
                    <x-campo nombre="kg" :etiqueta="__('cosechas.total_entero_kg')" tipo="number" step="0.01" min="0.1" inputmode="decimal"
                             :ayuda="__('cosechas.total_entero_ayuda')" :disabled="$modo !== 'total'" :required="$modo === 'total'" />
                </div>

                {{-- B. Tabla de pesadas --}}
                <div data-zona="pesadas" class="mt-5 space-y-5" @if ($modo !== 'pesadas') hidden @endif>
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <x-campo nombre="peso_canasta_kg" :etiqueta="__('cosechas.peso_canasta_vacia')" tipo="number" step="0.01" min="0.01" max="20" inputmode="decimal"
                                 :valor="(string) old('peso_canasta_kg', $pesoCanasta)" :disabled="$modo !== 'pesadas'" />
                        <x-campo nombre="peso_canastilla_kg" :etiqueta="__('cosechas.peso_canastilla_vacia')" tipo="number" step="0.01" min="0.01" max="20" inputmode="decimal"
                                 :valor="(string) old('peso_canastilla_kg', $pesoCanastilla)" :disabled="$modo !== 'pesadas'" />
                    </div>
                    <p class="text-sm text-tinta-suave">{{ __('cosechas.pesos_vacias_ayuda') }}</p>

                    @if ($erroresPesadas->isNotEmpty())
                        <ul class="space-y-1 rounded-ficha border border-alerta-borde bg-alerta-fondo p-4 text-base font-semibold text-alerta" role="alert">
                            @foreach ($erroresPesadas as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    @endif

                    {{-- En el celular cada pesada es un bloque; en pantallas anchas, una fila de tabla --}}
                    <div class="hidden grid-cols-[4rem_9rem_1fr_1fr_1fr_1fr_3rem] gap-2 px-2 text-sm font-bold text-tinta ancho:grid" aria-hidden="true">
                        <span>{{ __('cosechas.pesada') }}</span><span>{{ __('cosechas.tipo') }}</span><span>{{ __('cosechas.cantidad') }}</span>
                        <span>{{ __('cosechas.peso_pesado') }}</span><span>{{ __('cosechas.descuento') }}</span><span>{{ __('cosechas.peso_entero') }}</span><span></span>
                    </div>
                    <ol class="space-y-3" data-pesadas></ol>
                    <template data-plantilla-pesada>
                        <li class="grid grid-cols-2 items-end gap-3 rounded-ficha bg-papel p-3 ring-1 ring-renglon ancho:grid-cols-[4rem_9rem_1fr_1fr_1fr_1fr_3rem] ancho:gap-2 ancho:p-2">
                            <p class="col-span-2 text-base font-bold ancho:col-span-1 ancho:self-center">
                                <span class="ancho:hidden">{{ __('cosechas.pesada') }}</span> <span data-numero></span>
                            </p>
                            <label class="block">
                                <span class="mb-1 block text-sm font-semibold text-tinta-suave ancho:sr-only">{{ __('cosechas.tipo') }}</span>
                                <select data-pesada="tipo"
                                        class="block min-h-12 w-full rounded-ficha border border-renglon-fuerte bg-ficha px-3 text-base text-tinta transition-[border-color,box-shadow] duration-150 hover:border-tinta-suave focus:border-marca-700 focus:outline-none focus:ring-4 focus:ring-marca-200/70">
                                    @foreach (CalculoPesaje::TIPOS as $tipo)
                                        <option value="{{ $tipo }}">{{ __('cosechas.tipos.'.$tipo) }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <label class="block">
                                <span class="mb-1 block text-sm font-semibold text-tinta-suave ancho:sr-only">{{ __('cosechas.cantidad') }}</span>
                                <input type="number" min="1" max="{{ CalculoPesaje::MAX_CANTIDAD }}" step="1" inputmode="numeric" data-pesada="cantidad"
                                       class="block min-h-12 w-full rounded-ficha border border-renglon-fuerte bg-ficha px-3 text-base tabular-nums text-tinta transition-[border-color,box-shadow] duration-150 hover:border-tinta-suave focus:border-marca-700 focus:outline-none focus:ring-4 focus:ring-marca-200/70">
                            </label>
                            <label class="block">
                                <span class="mb-1 block text-sm font-semibold text-tinta-suave ancho:sr-only">{{ __('cosechas.peso_pesado') }}</span>
                                <input type="number" min="0.01" max="{{ CalculoPesaje::MAX_PESO_PESADA_KG }}" step="0.01" inputmode="decimal" data-pesada="peso"
                                       class="block min-h-12 w-full rounded-ficha border border-renglon-fuerte bg-ficha px-3 text-base tabular-nums text-tinta transition-[border-color,box-shadow] duration-150 hover:border-tinta-suave focus:border-marca-700 focus:outline-none focus:ring-4 focus:ring-marca-200/70">
                            </label>
                            <p class="ancho:self-center">
                                <span class="block text-sm font-semibold text-tinta-suave ancho:sr-only">{{ __('cosechas.descuento') }}</span>
                                <span class="text-base tabular-nums text-tinta-suave" data-descuento>—</span>
                            </p>
                            <p class="ancho:self-center">
                                <span class="block text-sm font-semibold text-tinta-suave ancho:sr-only">{{ __('cosechas.peso_entero') }}</span>
                                <span class="text-base font-bold tabular-nums" data-entero>—</span>
                            </p>
                            <button type="button" data-quitar data-texto="{{ __('cosechas.quitar_pesada', ['numero' => '__N__']) }}"
                                    class="col-span-2 flex h-12 items-center justify-center rounded-ficha text-tinta-suave ring-1 ring-renglon transition-colors hover:bg-alerta-fondo hover:text-alerta ancho:col-span-1 ancho:w-12 ancho:ring-0">
                                <x-icono nombre="basura" class="h-5 w-5" />
                            </button>
                        </li>
                    </template>

                    <x-boton tipo="button" variante="secundario" class="w-full sm:w-auto" data-agregar-pesada>
                        <x-icono nombre="mas" class="h-5 w-5" /> {{ __('cosechas.anadir_pesada') }}
                    </x-boton>

                    {{-- D. Meta opcional (solo ayuda en pantalla) --}}
                    <div class="space-y-3 border-t border-renglon pt-5">
                        <label class="block max-w-xs">
                            <span class="mb-1.5 block text-base font-semibold text-tinta">{{ __('cosechas.meta') }}</span>
                            <input type="number" min="0" step="0.01" inputmode="decimal" data-meta
                                   class="block min-h-12 w-full rounded-ficha border border-renglon-fuerte bg-ficha px-4 text-base tabular-nums text-tinta transition-[border-color,box-shadow] duration-150 hover:border-tinta-suave focus:border-marca-700 focus:outline-none focus:ring-4 focus:ring-marca-200/70">
                            <span class="mt-1.5 block text-sm text-tinta-suave">{{ __('cosechas.meta_ayuda') }}</span>
                        </label>
                        <div data-meta-zona hidden class="space-y-2" aria-live="polite">
                            {{-- <progress> en lugar de un ancho en línea: se ve sin JS y la CSP no lo bloquea --}}
                            <progress max="100" value="0" data-meta-avance aria-label="{{ __('cosechas.meta') }}"
                                      class="block h-3 w-full appearance-none overflow-hidden rounded-full ring-1 ring-renglon [&::-moz-progress-bar]:bg-marca-700 [&::-webkit-progress-bar]:bg-papel-hondo [&::-webkit-progress-value]:rounded-full [&::-webkit-progress-value]:bg-marca-700"></progress>
                            <div data-meta-textos></div>
                        </div>
                    </div>

                    {{-- E. Totales en vivo --}}
                    <dl class="grid grid-cols-2 gap-x-4 gap-y-3 rounded-ficha border border-renglon bg-papel-hondo p-4 text-base sm:grid-cols-3" aria-live="polite">
                        <div><dt class="text-sm text-tinta-suave">{{ __('cosechas.total_pesadas') }}</dt><dd class="text-lg font-bold tabular-nums" data-total="pesadas">0</dd></div>
                        <div class="col-span-2 sm:col-span-1"><dt class="text-sm text-tinta-suave">{{ __('cosechas.total_cantidad') }}</dt>
                            <dd class="text-lg font-bold tabular-nums" data-total="cantidad" data-texto="{{ __('cosechas.cantidad_por_tipo') }}"></dd></div>
                        <div><dt class="text-sm text-tinta-suave">{{ __('cosechas.total_pesado') }}</dt><dd class="text-lg font-bold tabular-nums" data-total="pesado">—</dd></div>
                        <div><dt class="text-sm text-tinta-suave">{{ __('cosechas.total_descuento') }}</dt><dd class="text-lg font-bold tabular-nums" data-total="descuento">—</dd></div>
                        <div class="col-span-2 sm:col-span-1"><dt class="text-sm font-bold text-tinta">{{ __('cosechas.total_entero') }}</dt><dd class="text-cifra font-bold tabular-nums text-marca-800" data-total="entero">—</dd></div>
                    </dl>
                    <p class="text-base font-semibold" data-linea hidden></p>
                </div>
            </x-tarjeta>

            <x-tarjeta>
                <x-campo nombre="observaciones" :etiqueta="__('cosechas.observaciones')" maxlength="500" />
                <p data-aviso-pesaje hidden role="alert" class="mt-4 rounded-ficha border border-alerta-borde bg-alerta-fondo p-4 text-base font-semibold text-alerta"></p>
                <div class="mt-5 grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <x-boton variante="secundario" :href="url()->previous()">{{ __('comun.cancelar') }}</x-boton>
                    <x-boton>{{ __('cosechas.registrar') }}</x-boton>
                </div>
            </x-tarjeta>
        </x-formulario>
    @endif
@endsection
