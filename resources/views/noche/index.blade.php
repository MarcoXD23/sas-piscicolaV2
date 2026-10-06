@extends('layouts.app')

@section('titulo', __('noche.seguridad_noche'))

@section('contenido')
    @php
        $numero = fn ($valor, $decimales = 2) => \App\Support\Formato::numero((float) $valor, $decimales);
        $tonos = [
            'positivo' => 'bg-marca-100 text-marca-700',
            'alerta' => 'bg-amber-100 text-amber-700',
            'neutro' => 'bg-slate-100 text-slate-600',
        ];
    @endphp

    <x-encabezado :titulo="__('noche.seguridad_noche')" :subtitulo="__('noche.rondas_oxigeno_aireadores_finca')">
        <x-boton tipo="button" data-abrir-modal="modal-ronda" :disabled="! $puedeRegistrar">
            <x-icono nombre="ok" class="h-5 w-5" /> {{ __('noche.registrar_ronda') }}
        </x-boton>
    </x-encabezado>

    @if ($turno)
        <div role="status" class="mb-5 flex items-center gap-3 rounded-xl border border-marca-200 bg-marca-50 px-4 py-3 text-base text-marca-900">
            <x-icono nombre="luna" class="h-5 w-5" />
            <span>{{ __('noche.turno_hasta') }} <strong class="font-semibold">{{ $turno->termina_at->format('H:i') }}</strong>.</span>
        </div>
    @elseif (! $puedeRegistrar)
        <div role="status" class="mb-5 flex items-start gap-3 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-base text-amber-900">
            <x-icono nombre="reloj" class="mt-0.5 h-5 w-5" />
            <span>{{ __('noche.estas_fuera_tu_turno_puedes') }}</span>
        </div>
    @endif

    <h2 class="mb-3 text-lg font-semibold text-slate-900">{{ __('noche.lagos') }}</h2>

    @if ($lagos->isEmpty())
        <x-vacio icono="lagos" :mensaje="__('noche.aun_no_hay_lagos_registrados')" />
    @endif

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ($lagos as $lago)
            @php
                $medicion = $lago->ultimaMedicion;
                $alertas = $alertasAgua[$lago->id];
                $aireacion = $lago->aireacionActiva;
            @endphp

            <section @class([
                'flex flex-col rounded-2xl bg-white p-5 shadow-sm ring-1',
                'ring-red-300' => $alertas !== [],
                'ring-slate-200' => $alertas === [],
            ])>
                <div class="mb-3 flex items-start justify-between gap-3">
                    <div>
                        <h3 class="text-xl font-bold text-slate-900">{{ $lago->nombre }}</h3>
                        <p class="text-sm text-slate-500">
                            {{ $lago->siembrasActivas->isEmpty() ? __('noche.sin_peces') : $lago->siembrasActivas->pluck('nombre_especie')->join(', ') }}
                        </p>
                    </div>
                    <span @class([
                        'flex shrink-0 items-center gap-1.5 rounded-full px-3 py-1 text-xs font-semibold',
                        'bg-sky-100 text-sky-900' => $aireacion,
                        'bg-slate-100 text-slate-600' => ! $aireacion,
                    ])>
                        <x-icono nombre="aireador" class="h-4 w-4" /> {{ $aireacion ? __('noche.aireador_encendido') : __('noche.aireador_apagado') }}
                    </span>
                </div>

                <div class="mb-4 rounded-xl bg-slate-50 p-3">
                    @if ($medicion?->oxigeno_mg_l !== null)
                        <p class="text-sm text-slate-500">{{ __('noche.oxigeno_a_las', ['momento' => $medicion->medido_at->format('d/m H:i')]) }}</p>
                        <p @class(['text-2xl font-bold tabular-nums', 'text-red-600' => $alertas !== [], 'text-slate-900' => $alertas === []])>
                            {{ $numero($medicion->oxigeno_mg_l) }} <span class="text-base font-medium text-slate-500">mg/L</span>
                        </p>
                    @else
                        <p class="text-base text-slate-500">{{ __('noche.sin_medicion_oxigeno') }}</p>
                    @endif

                    @foreach ($alertas as $alerta)
                        <p class="mt-1 text-sm font-medium text-red-700">{{ $alerta }}</p>
                    @endforeach
                    @if ($alertas !== [] && ! $aireacion)
                        <p class="mt-2 text-sm font-semibold text-red-800">{{ __('noche.enciende_aireador_vuelve_medir') }}</p>
                    @endif
                    @if ($aireacion)
                        <p class="mt-1 text-sm text-sky-900">{{ __('noche.aireador_encendido_desde', ['hora' => $aireacion->encendido_at->format('H:i')]) }}</p>
                    @endif
                </div>

                <div class="mt-auto grid grid-cols-2 gap-2">
                    <x-boton tipo="button" variante="secundario" class="!px-2" data-abrir-modal="modal-oxigeno-{{ $lago->id }}" :disabled="! $puedeRegistrar">
                        <x-icono nombre="reloj" class="h-5 w-5" /> {{ __('noche.medir') }}
                    </x-boton>

                    @if ($aireacion)
                        <x-formulario :action="route('noche.aireador.apagar', $aireacion)" class="!space-y-0">
                            <x-boton variante="secundario" class="w-full !px-2" :disabled="! $puedeRegistrar">
                                <x-icono nombre="aireador" class="h-5 w-5" /> {{ __('noche.apagar') }}
                            </x-boton>
                        </x-formulario>
                    @else
                        <x-formulario :action="route('noche.aireador.encender', $lago)" class="!space-y-0">
                            <input type="hidden" name="motivo" value="{{ $alertas !== [] ? __('noche.motivo_oxigeno_bajo') : '' }}">
                            <x-boton class="w-full !px-2" :disabled="! $puedeRegistrar">
                                <x-icono nombre="aireador" class="h-5 w-5" /> {{ __('noche.encender') }}
                            </x-boton>
                        </x-formulario>
                    @endif
                </div>
            </section>

            @if ($puedeRegistrar)
                <x-modal id="modal-oxigeno-{{ $lago->id }}" :titulo="__('noche.medicion_de', ['lago' => $lago->nombre])">
                    <x-formulario :action="route('noche.mediciones.store', $lago)">
                        <div class="grid grid-cols-2 gap-4">
                            <x-campo nombre="oxigeno_mg_l" id="oxigeno-{{ $lago->id }}" :etiqueta="__('noche.oxigeno_mg_l')" tipo="number" step="0.01" min="0" inputmode="decimal" autofocus />
                            <x-campo nombre="temperatura_c" id="temperatura-{{ $lago->id }}" :etiqueta="__('noche.temperatura_c')" tipo="number" step="0.1" inputmode="decimal" />
                        </div>
                        <p class="text-sm text-slate-500">{{ __('noche.guarda_hora_presiones_guardar') }}</p>

                        <div class="grid grid-cols-2 gap-3 pt-2">
                            <x-boton tipo="button" variante="secundario" data-cerrar-modal>{{ __('comun.cancelar') }}</x-boton>
                            <x-boton>{{ __('comun.guardar') }}</x-boton>
                        </div>
                    </x-formulario>
                </x-modal>
            @endif
        @endforeach
    </div>

    <section class="mt-8">
        <h2 class="mb-3 text-lg font-semibold text-slate-900">{{ __('noche.bitacora_ultimas_horas', ['horas' => \App\Http\Controllers\Noche\NocheController::HORAS_BITACORA]) }}</h2>

        @if ($bitacora->isEmpty())
            <x-vacio icono="luna" :mensaje="__('noche.todavia_no_hay_registros_esta')" />
        @else
            <ol class="rounded-2xl bg-white shadow-sm ring-1 ring-slate-200">
                @foreach ($bitacora as $evento)
                    <li class="flex gap-4 border-t border-slate-100 p-4 first:border-t-0">
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl {{ $tonos[$evento['tono']] }}">
                            <x-icono :nombre="$evento['icono']" class="h-5 w-5" />
                        </span>
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-baseline justify-between gap-x-3">
                                <p class="font-semibold text-slate-900">{{ $evento['titulo'] }}</p>
                                <time class="text-sm tabular-nums text-slate-500" datetime="{{ $evento['momento']->toIso8601String() }}">{{ $evento['momento']->format('H:i') }}</time>
                            </div>
                            @if ($evento['detalle'] !== '')
                                <p class="text-sm text-slate-600">{{ $evento['detalle'] }}</p>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ol>
        @endif
    </section>

    @if ($puedeRegistrar)
        <x-modal id="modal-ronda" :titulo="__('noche.registrar_ronda')">
            <x-formulario :action="route('noche.rondas.store')">
                <fieldset>
                    <legend class="mb-1.5 text-base font-medium text-slate-700">{{ __('noche.como_estuvo_ronda') }}</legend>
                    <div class="grid gap-2">
                        @foreach ($tipos as $clave => $texto)
                            <label class="flex min-h-12 cursor-pointer items-center gap-3 rounded-xl border border-slate-300 px-4 text-base has-checked:border-marca-500 has-checked:bg-marca-50">
                                <input type="radio" name="tipo" value="{{ $clave }}" class="h-5 w-5 accent-marca-600" @checked(old('tipo', \App\Models\Ronda::SIN_NOVEDAD) === $clave) required>
                                {{ $texto }}
                            </label>
                        @endforeach
                    </div>
                    @error('tipo')
                        <p class="mt-1.5 text-sm font-medium text-red-600">{{ $message }}</p>
                    @enderror
                </fieldset>

                <x-seleccion nombre="lago_id" :etiqueta="__('comun.lago_opcional')" :opciones="['' => __('noche.toda_la_finca')] + $lagos->pluck('nombre', 'id')->all()" />

                <div>
                    <label for="ronda-descripcion" class="mb-1.5 block text-base font-medium text-slate-700">{{ __('noche.descripcion') }}</label>
                    <textarea id="ronda-descripcion" name="descripcion" rows="3"
                              class="block w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-base text-slate-900 shadow-sm focus:border-marca-500 focus:outline-none focus:ring-2 focus:ring-marca-200"
                              placeholder="{{ __('noche.obligatoria_si_hubo_novedad') }}">{{ old('descripcion') }}</textarea>
                    @error('descripcion')
                        <p class="mt-1.5 text-sm font-medium text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div class="grid grid-cols-2 gap-3 pt-2">
                    <x-boton tipo="button" variante="secundario" data-cerrar-modal>{{ __('comun.cancelar') }}</x-boton>
                    <x-boton>{{ __('comun.guardar') }}</x-boton>
                </div>
            </x-formulario>
        </x-modal>
    @endif
@endsection
