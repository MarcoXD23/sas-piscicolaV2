@extends('layouts.app')

@section('titulo', __('noche.seguridad_noche'))

@section('contenido')
    @php
        $numero = fn ($valor, $decimales = 2) => \App\Support\Formato::numero((float) $valor, $decimales);
        // Íconos de la bitácora: el tono del evento pasa a los colores de estado (en noche, la variante oscura)
        $tonos = [
            'positivo' => 'bg-bien-fondo text-bien',
            'alerta' => 'bg-atencion-fondo text-atencion',
            'neutro' => 'bg-papel-hondo text-tinta-suave',
        ];
    @endphp

    {{-- tema-noche (app.css): la libreta de noche, oscura para no deslumbrar al celador --}}
    <div class="tema-noche">
        <x-encabezado :titulo="__('noche.seguridad_noche')" :subtitulo="__('noche.rondas_oxigeno_aireadores_finca')">
            @unless ($soloLectura)
                <x-boton tipo="button" data-abrir-modal="modal-ronda" :disabled="! $puedeRegistrar">
                    <x-icono nombre="ok" class="h-5 w-5" /> {{ __('noche.registrar_ronda') }}
                </x-boton>
            @endunless
        </x-encabezado>

        @if ($turno)
            <x-aviso tono="bien" icono="luna" class="mb-6">{{ __('noche.turno_hasta') }} <strong class="font-bold">{{ $turno->termina_at->format('H:i') }}</strong>.</x-aviso>
        @elseif ($soloLectura)
            <x-aviso tono="info" icono="luna" class="mb-6">{{ __('noche.solo_lectura') }}</x-aviso>
        @elseif (! $puedeRegistrar)
            <x-aviso tono="atencion" icono="reloj" class="mb-6">{{ __('noche.estas_fuera_tu_turno_puedes') }}</x-aviso>
        @endif

        <h2 class="mb-5 text-seccion font-bold">{{ __('noche.lagos') }}</h2>

        @if ($lagos->isEmpty())
            <x-vacio icono="lagos" :mensaje="__('noche.aun_no_hay_lagos_registrados')" />
        @endif

        <div class="grid items-start gap-x-5 gap-y-8 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($lagos as $lago)
                @php
                    $medicion = $lago->ultimaMedicion;
                    $alertas = $alertasAgua[$lago->id];
                    $aireacion = $lago->aireacionActiva;
                    $conOxigeno = $medicion?->oxigeno_mg_l !== null;
                    $estado = $alertas !== [] ? 'alerta' : ($conOxigeno ? 'bien' : 'sin_medicion');
                @endphp

                {{-- Ficha del lago: pestaña de estado y el oxígeno en cifra grande, lo primero que se mira de noche --}}
                <x-tarjeta @class(['relative flex flex-col !pt-7', '!ring-alerta-borde' => $estado === 'alerta'])>
                    <x-insignia :tono="['sin_medicion' => 'info'][$estado] ?? $estado" pestana como="p">{{ __('inicio.estado_agua.'.$estado) }}</x-insignia>

                    <div class="mb-4 flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <h3 class="text-xl font-bold">{{ $lago->nombre }}</h3>
                            <p class="text-sm text-tinta-suave">
                                {{ $lago->siembrasActivas->isEmpty() ? __('noche.sin_peces') : \App\Support\LotesPorEspecie::nombres($lago->siembrasActivas) }}
                            </p>
                        </div>
                        <x-insignia :tono="$aireacion ? 'info' : 'neutro'" icono="aireador" class="shrink-0">
                            {{ $aireacion ? __('noche.aireador_encendido') : __('noche.aireador_apagado') }}
                        </x-insignia>
                    </div>

                    <div class="mb-5 border-y border-renglon py-4">
                        @if ($conOxigeno)
                            <p class="text-sm text-tinta-suave">{{ __('noche.oxigeno_a_las', ['momento' => $medicion->medido_at->format('d/m H:i')]) }}</p>
                            <p @class(['mt-1 text-cifra-grande font-bold tabular-nums', 'text-alerta' => $alertas !== []])>
                                {{ $numero($medicion->oxigeno_mg_l) }} <span class="text-lg font-normal tracking-normal text-tinta-suave">mg/L</span>
                            </p>
                        @else
                            <p class="flex items-center gap-2 text-base text-tinta-suave"><x-icono nombre="reloj" class="h-5 w-5" /> {{ __('noche.sin_medicion_oxigeno') }}</p>
                        @endif

                        @foreach ($alertas as $alerta)
                            <p class="mt-2 text-sm font-semibold text-alerta">{{ $alerta }}</p>
                        @endforeach
                        @if ($alertas !== [] && ! $aireacion)
                            <p class="mt-2 flex items-start gap-1.5 text-base font-bold text-alerta"><x-icono nombre="aireador" class="mt-0.5 h-5 w-5" /> {{ __('noche.enciende_aireador_vuelve_medir') }}</p>
                        @endif
                        @if ($aireacion)
                            <p class="mt-2 text-sm text-info">{{ __('noche.aireador_encendido_desde', ['hora' => $aireacion->encendido_at->format('H:i')]) }}</p>
                        @endif
                    </div>

                    @unless ($soloLectura)
                    <div class="mt-auto grid grid-cols-2 gap-2">
                        <x-boton tipo="button" variante="secundario" class="!px-2" data-abrir-modal="modal-oxigeno-{{ $lago->id }}" :disabled="! $puedeRegistrar">
                            <x-icono nombre="gota" class="h-5 w-5" /> {{ __('noche.medir') }}
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
                    @endunless
                </x-tarjeta>

                @if ($puedeRegistrar)
                    <x-modal id="modal-oxigeno-{{ $lago->id }}" :titulo="__('noche.medicion_de', ['lago' => $lago->nombre])">
                        <x-formulario :action="route('noche.mediciones.store', $lago)" :data-guardar-sin-conexion="__('layout.cola.resumen_medicion', ['lago' => $lago->nombre])">
                            <div class="grid grid-cols-2 gap-4">
                                <x-campo nombre="oxigeno_mg_l" id="oxigeno-{{ $lago->id }}" :etiqueta="__('noche.oxigeno_mg_l')" tipo="number" step="0.01" min="0" inputmode="decimal" autofocus />
                                <x-campo nombre="temperatura_c" id="temperatura-{{ $lago->id }}" :etiqueta="__('noche.temperatura_c')" tipo="number" step="0.1" inputmode="decimal" />
                            </div>
                            <p class="text-sm text-tinta-suave">{{ __('noche.guarda_hora_presiones_guardar') }}</p>

                            <div class="grid grid-cols-2 gap-3 pt-2">
                                <x-boton tipo="button" variante="secundario" data-cerrar-modal>{{ __('comun.cancelar') }}</x-boton>
                                <x-boton>{{ __('comun.guardar') }}</x-boton>
                            </div>
                        </x-formulario>
                    </x-modal>
                @endif
            @endforeach
        </div>

        <section class="mt-10" aria-labelledby="bitacora-titulo">
            <h2 id="bitacora-titulo" class="mb-4 text-seccion font-bold">{{ __('noche.ultimas_horas', ['horas' => \App\Http\Controllers\Noche\NocheController::HORAS_BITACORA]) }}</h2>

            @if ($bitacora->isEmpty())
                <x-vacio icono="luna" :mensaje="__('noche.todavia_no_hay_registros_esta')" />
            @else
                @include('noche._eventos', ['eventos' => $bitacora])
            @endif
        </section>

        @if ($soloLectura)
            <section class="mt-10" aria-labelledby="reportes-titulo">
                <h2 id="reportes-titulo" class="mb-4 text-seccion font-bold">{{ __('noche.reportes_por_turno') }}</h2>

                @if ($turnosCelador->isEmpty())
                    <x-vacio icono="luna" :mensaje="__('noche.sin_turnos_celador')" />
                @else
                    {{-- Elegir turno: como las pestañas, el elegido lleva raya y negrita --}}
                    <nav class="mb-4 flex flex-wrap gap-x-1 border-b border-renglon" aria-label="{{ __('noche.elegir_turno') }}">
                        @foreach ($turnosCelador as $opcion)
                            <a href="{{ route('noche.index', ['turno' => $opcion->id]) }}"
                               @if ($opcion->is($turnoElegido)) aria-current="page" @endif
                               @class([
                                   '-mb-px flex min-h-12 items-center px-3 text-base transition-colors',
                                   'font-bold text-tinta shadow-[inset_0_-3px_0_var(--color-marca-300)]' => $opcion->is($turnoElegido),
                                   'text-tinta-suave hover:bg-papel-hondo hover:text-tinta' => ! $opcion->is($turnoElegido),
                               ])>{{ __('noche.turno_opcion', ['fecha' => $opcion->inicia_at->format('d/m'), 'nombre' => $opcion->user->name]) }}</a>
                        @endforeach
                    </nav>

                    <p class="mb-4 text-sm text-tinta-suave">{{ __('noche.turno_detalle', ['nombre' => $turnoElegido->user->name, 'desde' => $turnoElegido->inicia_at->format('d/m H:i'), 'hasta' => $turnoElegido->termina_at->format('d/m H:i')]) }}</p>

                    @if ($reporteTurno->isEmpty())
                        <x-vacio icono="luna" :mensaje="__('noche.turno_sin_registros')" />
                    @else
                        @include('noche._eventos', ['eventos' => $reporteTurno, 'con_fecha' => true])
                    @endif
                @endif
            </section>
        @endif

        @if ($puedeRegistrar)
            <x-modal id="modal-ronda" :titulo="__('noche.registrar_ronda')">
                <x-formulario :action="route('noche.rondas.store')" :data-guardar-sin-conexion="__('layout.cola.resumen_ronda')">
                    <fieldset>
                        <legend class="mb-1.5 text-base font-semibold">{{ __('noche.como_estuvo_ronda') }}</legend>
                        <div class="grid gap-2">
                            @foreach ($tipos as $clave => $texto)
                                <label class="flex min-h-12 cursor-pointer items-center gap-3 rounded-ficha border border-renglon-fuerte px-4 text-base transition-colors has-checked:border-marca-700 has-checked:bg-bien-fondo has-checked:font-bold">
                                    <input type="radio" name="tipo" value="{{ $clave }}" class="h-5 w-5 shrink-0" @checked(old('tipo', \App\Models\Ronda::SIN_NOVEDAD) === $clave) required>
                                    {{ $texto }}
                                </label>
                            @endforeach
                        </div>
                        @error('tipo')
                            <p class="mt-1.5 flex items-start gap-1.5 text-sm font-semibold text-alerta"><x-icono nombre="alerta" class="mt-px h-4 w-4" /> {{ $message }}</p>
                        @enderror
                    </fieldset>

                    <x-seleccion nombre="lago_id" :etiqueta="__('comun.lago_opcional')" :opciones="['' => __('noche.toda_la_finca')] + $lagos->pluck('nombre', 'id')->all()" />

                    <div>
                        <label for="ronda-descripcion" class="mb-1.5 block text-base font-semibold">{{ __('noche.descripcion') }}</label>
                        <textarea id="ronda-descripcion" name="descripcion" rows="3"
                                  class="block w-full rounded-ficha border border-renglon-fuerte bg-ficha px-4 py-3 text-base text-tinta transition-[border-color,box-shadow] duration-150 placeholder:text-tinta-suave/80 hover:border-tinta-suave focus:border-marca-700 focus:outline-none focus:ring-4 focus:ring-marca-200/70"
                                  placeholder="{{ __('noche.obligatoria_si_hubo_novedad') }}">{{ old('descripcion') }}</textarea>
                        @error('descripcion')
                            <p class="mt-1.5 flex items-start gap-1.5 text-sm font-semibold text-alerta"><x-icono nombre="alerta" class="mt-px h-4 w-4" /> {{ $message }}</p>
                        @enderror
                    </div>

                    <div class="grid grid-cols-2 gap-3 pt-2">
                        <x-boton tipo="button" variante="secundario" data-cerrar-modal>{{ __('comun.cancelar') }}</x-boton>
                        <x-boton>{{ __('comun.guardar') }}</x-boton>
                    </div>
                </x-formulario>
            </x-modal>
        @endif
    </div>
@endsection
