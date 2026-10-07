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
        <x-formulario :action="route('admin.cosechas.store')" class="max-w-4xl"
                      x-data="pesaje({{ Js::from(['modo' => $modo, 'canasta' => (string) old('peso_canasta_kg', $pesoCanasta), 'canastilla' => (string) old('peso_canastilla_kg', $pesoCanastilla), 'filas' => $filasPrevias, 'decimal' => $decimal, 'miles' => $miles]) }})"
                      x-on:submit="validar($event)">

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
                        <p class="mb-1.5 block text-base font-medium text-slate-700">{{ __('cosechas.hora') }}</p>
                        <p class="flex min-h-12 items-center rounded-xl bg-slate-100 px-4 text-base tabular-nums text-slate-700">{{ Region::ahora()->format('H:i') }}</p>
                        <p class="mt-1.5 text-sm text-slate-500">{{ __('cosechas.hora_ayuda', ['hora' => Region::ahora()->format('H:i')]) }}</p>
                    </div>
                    <x-seleccion nombre="despachado_por_id" :etiqueta="__('cosechas.despacho')" :opciones="$personal->pluck('name', 'id')->all()"
                                 :valor="auth()->id()" :ayuda="__('cosechas.despacho_ayuda')" />
                    <x-campo nombre="peces" :etiqueta="__('cosechas.peces')" tipo="number" min="1" inputmode="numeric" required />
                </div>
                <p class="mt-4 rounded-xl bg-marca-50 p-4 text-base text-marca-900">{{ __('cosechas.si_cosechas_todo') }}</p>
            </x-tarjeta>

            {{-- Modo: tabla de pesadas o solo el total limpio --}}
            <x-tarjeta>
                <fieldset>
                    <legend class="mb-1.5 text-base font-medium text-slate-700">{{ __('cosechas.como_se_peso') }}</legend>
                    <div class="grid grid-cols-2 gap-1 rounded-xl bg-slate-100 p-1">
                        @foreach (['pesadas' => __('cosechas.modo_pesadas'), 'total' => __('cosechas.modo_total')] as $valor => $texto)
                            <label class="flex min-h-12 cursor-pointer items-center justify-center rounded-lg px-2 text-center text-base font-semibold text-slate-600 has-checked:bg-white has-checked:text-marca-800 has-checked:shadow-sm">
                                <input type="radio" value="{{ $valor }}" class="sr-only" x-model="modo">
                                {{ $texto }}
                            </label>
                        @endforeach
                    </div>
                </fieldset>

                <div x-show="modo === 'total'" x-cloak class="mt-5">
                    <x-campo nombre="kg" :etiqueta="__('cosechas.total_limpio_kg')" tipo="number" step="0.01" min="0.1" inputmode="decimal"
                             :ayuda="__('cosechas.total_limpio_ayuda')" x-bind:disabled="modo !== 'total'" x-bind:required="modo === 'total'" />
                </div>

                {{-- B. Tabla de pesadas --}}
                <div x-show="modo === 'pesadas'" class="mt-5 space-y-5">
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <x-campo nombre="peso_canasta_kg" :etiqueta="__('cosechas.peso_canasta_vacia')" tipo="number" step="0.01" min="0.01" max="20" inputmode="decimal"
                                 x-model="vacias.canasta" x-bind:disabled="modo !== 'pesadas'" />
                        <x-campo nombre="peso_canastilla_kg" :etiqueta="__('cosechas.peso_canastilla_vacia')" tipo="number" step="0.01" min="0.01" max="20" inputmode="decimal"
                                 x-model="vacias.canastilla" x-bind:disabled="modo !== 'pesadas'" />
                    </div>
                    <p class="text-sm text-slate-500">{{ __('cosechas.pesos_vacias_ayuda') }}</p>

                    @if ($erroresPesadas->isNotEmpty())
                        <ul class="space-y-1 rounded-xl bg-red-50 p-4 text-base text-red-800" role="alert">
                            @foreach ($erroresPesadas as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    @endif

                    {{-- En el celular cada pesada es un bloque; en pantallas anchas, una fila de tabla --}}
                    <div class="hidden grid-cols-[4rem_9rem_1fr_1fr_1fr_1fr_3rem] gap-2 px-2 text-sm font-semibold text-marca-900 ancho:grid" aria-hidden="true">
                        <span>{{ __('cosechas.pesada') }}</span><span>{{ __('cosechas.tipo') }}</span><span>{{ __('cosechas.cantidad') }}</span>
                        <span>{{ __('cosechas.peso_pesado') }}</span><span>{{ __('cosechas.descuento') }}</span><span>{{ __('cosechas.peso_limpio') }}</span><span></span>
                    </div>
                    <ol class="space-y-3">
                        <template x-for="(fila, indice) in filas" :key="indice">
                            <li class="grid grid-cols-2 items-end gap-3 rounded-xl bg-slate-50 p-3 ring-1 ring-slate-200 ancho:grid-cols-[4rem_9rem_1fr_1fr_1fr_1fr_3rem] ancho:gap-2 ancho:p-2">
                                <p class="col-span-2 text-base font-semibold text-slate-900 ancho:col-span-1 ancho:self-center">
                                    <span class="ancho:hidden">{{ __('cosechas.pesada') }}</span> <span x-text="indice + 1"></span>
                                </p>
                                <label class="block">
                                    <span class="mb-1 block text-sm font-medium text-slate-600 ancho:sr-only">{{ __('cosechas.tipo') }}</span>
                                    <select x-model="fila.tipo" :name="`pesadas[${indice}][tipo]`" :disabled="modo !== 'pesadas'"
                                            class="block min-h-12 w-full rounded-xl border border-slate-300 bg-white px-3 text-base text-slate-900 shadow-sm focus:border-marca-500 focus:outline-none focus:ring-2 focus:ring-marca-200">
                                        @foreach (CalculoPesaje::TIPOS as $tipo)
                                            <option value="{{ $tipo }}">{{ __('cosechas.tipos.'.$tipo) }}</option>
                                        @endforeach
                                    </select>
                                </label>
                                <label class="block">
                                    <span class="mb-1 block text-sm font-medium text-slate-600 ancho:sr-only">{{ __('cosechas.cantidad') }}</span>
                                    <input type="number" min="1" max="{{ CalculoPesaje::MAX_CANTIDAD }}" step="1" inputmode="numeric" x-model="fila.cantidad"
                                           :name="`pesadas[${indice}][cantidad]`" :data-cantidad="indice" :disabled="modo !== 'pesadas'"
                                           class="block min-h-12 w-full rounded-xl border border-slate-300 bg-white px-3 text-base tabular-nums text-slate-900 shadow-sm focus:border-marca-500 focus:outline-none focus:ring-2 focus:ring-marca-200">
                                </label>
                                <label class="block">
                                    <span class="mb-1 block text-sm font-medium text-slate-600 ancho:sr-only">{{ __('cosechas.peso_pesado') }}</span>
                                    <input type="number" min="0.01" max="{{ CalculoPesaje::MAX_PESO_PESADA_KG }}" step="0.01" inputmode="decimal" x-model="fila.peso"
                                           :name="`pesadas[${indice}][peso_pesado]`" :disabled="modo !== 'pesadas'" x-on:keydown.enter.prevent="siguiente(indice)"
                                           class="block min-h-12 w-full rounded-xl border border-slate-300 bg-white px-3 text-base tabular-nums text-slate-900 shadow-sm focus:border-marca-500 focus:outline-none focus:ring-2 focus:ring-marca-200">
                                </label>
                                <p class="ancho:self-center">
                                    <span class="block text-sm font-medium text-slate-600 ancho:sr-only">{{ __('cosechas.descuento') }}</span>
                                    <span class="text-base tabular-nums text-slate-700" x-text="kg(descuentoC(fila))"></span>
                                </p>
                                <p class="ancho:self-center">
                                    <span class="block text-sm font-medium text-slate-600 ancho:sr-only">{{ __('cosechas.peso_limpio') }}</span>
                                    <span class="text-base font-semibold tabular-nums text-slate-900" x-text="kg(limpioC(fila))"></span>
                                </p>
                                <button type="button" x-on:click="quitar(indice)" :aria-label="@js(__('cosechas.quitar_pesada', ['numero' => '__N__'])).replace('__N__', indice + 1)"
                                        class="col-span-2 flex h-12 items-center justify-center rounded-xl text-slate-500 ring-1 ring-slate-200 hover:bg-red-50 hover:text-red-600 ancho:col-span-1 ancho:w-12 ancho:ring-0">
                                    <x-icono nombre="basura" class="h-5 w-5" />
                                </button>
                            </li>
                        </template>
                    </ol>

                    <x-boton tipo="button" variante="secundario" class="w-full sm:w-auto" x-on:click="agregar()">
                        <x-icono nombre="mas" class="h-5 w-5" /> {{ __('cosechas.anadir_pesada') }}
                    </x-boton>

                    {{-- D. Meta opcional (solo ayuda en pantalla) --}}
                    <div class="space-y-3 border-t border-slate-200 pt-5">
                        <label class="block max-w-xs">
                            <span class="mb-1.5 block text-base font-medium text-slate-700">{{ __('cosechas.meta') }}</span>
                            <input type="number" min="0" step="0.01" inputmode="decimal" x-model="meta"
                                   class="block min-h-12 w-full rounded-xl border border-slate-300 bg-white px-4 text-base tabular-nums text-slate-900 shadow-sm focus:border-marca-500 focus:outline-none focus:ring-2 focus:ring-marca-200">
                            <span class="mt-1.5 block text-sm text-slate-500">{{ __('cosechas.meta_ayuda') }}</span>
                        </label>
                        <div x-show="metaC !== null" x-cloak class="space-y-2" aria-live="polite">
                            <div class="h-3 overflow-hidden rounded-full bg-slate-200">
                                <div class="h-full rounded-full bg-marca-600 transition-all" :style="`width: ${avance}%`"></div>
                            </div>
                            <template x-for="texto in metaTexto"><p class="text-base text-slate-800" x-text="texto"></p></template>
                        </div>
                    </div>

                    {{-- E. Totales en vivo --}}
                    <dl class="grid grid-cols-2 gap-3 rounded-xl bg-marca-50 p-4 text-base sm:grid-cols-3" aria-live="polite">
                        <div><dt class="text-sm text-marca-800">{{ __('cosechas.total_pesadas') }}</dt><dd class="font-semibold tabular-nums" x-text="totales.pesadas"></dd></div>
                        <div class="col-span-2 sm:col-span-1"><dt class="text-sm text-marca-800">{{ __('cosechas.total_cantidad') }}</dt>
                            <dd class="font-semibold tabular-nums" x-text="@js(__('cosechas.cantidad_por_tipo')).replace(':canastas', totales.porTipo.canasta).replace(':canastillas', totales.porTipo.canastilla)"></dd></div>
                        <div><dt class="text-sm text-marca-800">{{ __('cosechas.total_pesado') }}</dt><dd class="font-semibold tabular-nums" x-text="kg(totales.pesado)"></dd></div>
                        <div><dt class="text-sm text-marca-800">{{ __('cosechas.total_descuento') }}</dt><dd class="font-semibold tabular-nums" x-text="kg(totales.descuento)"></dd></div>
                        <div class="col-span-2 sm:col-span-1"><dt class="text-sm font-bold text-marca-900">{{ __('cosechas.total_limpio') }}</dt><dd class="text-xl font-bold tabular-nums text-marca-900" x-text="kg(totales.limpio)"></dd></div>
                    </dl>
                    <p class="text-base text-slate-700" x-show="linea" x-text="linea"></p>
                </div>
            </x-tarjeta>

            <x-tarjeta>
                <x-campo nombre="observaciones" :etiqueta="__('cosechas.observaciones')" maxlength="500" />
                <p x-show="aviso" x-cloak x-text="aviso" role="alert" class="mt-4 rounded-xl bg-red-50 p-4 text-base font-medium text-red-800"></p>
                <div class="mt-5 grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <x-boton variante="secundario" :href="url()->previous()">{{ __('comun.cancelar') }}</x-boton>
                    <x-boton>{{ __('cosechas.registrar') }}</x-boton>
                </div>
            </x-tarjeta>
        </x-formulario>
    @endif
@endsection
