@extends('layouts.app')

@section('titulo', __('lagos.lagos_siembras'))

@section('contenido')
    @php
        // Solo el administrador (o el propietario que también lo es) opera los lagos; el propietario consulta
        $puedeOperar = auth()->user()->tieneRol('administrador');
        $numero = fn ($valor, $decimales = 0) => \App\Support\Formato::numero($valor, $decimales);
    @endphp

    <x-encabezado :titulo="__('lagos.lagos_siembras')" :subtitulo="__('lagos.estanques_finca_ciclos_productivos')">
        @if ($puedeOperar)
            <x-boton tipo="button" data-abrir-modal="modal-lago">
                <x-icono nombre="mas" class="h-5 w-5" /> {{ __('lagos.nuevo_lago') }}
            </x-boton>
        @endif
    </x-encabezado>

    @if ($soloLectura)
        <x-aviso tono="info" icono="lagos" class="mb-6">{{ __('lagos.solo_lectura') }}</x-aviso>
    @elseif (! $puedeRegistrar)
        <x-aviso tono="atencion" icono="reloj" class="mb-6">{{ __('lagos.estas_fuera_tu_turno_puedes') }}</x-aviso>
    @endif

    @if ($lagos->isEmpty())
        <x-vacio icono="lagos" :mensaje="__('lagos.aun_no_hay_lagos_registrados')">
            @if ($puedeOperar)
                <x-boton tipo="button" data-abrir-modal="modal-lago">
                    <x-icono nombre="mas" class="h-5 w-5" /> {{ __('lagos.registrar_primer_lago') }}
                </x-boton>
            @endif
        </x-vacio>
    @endif

    <div class="grid items-start gap-5 md:grid-cols-2 lg:grid-cols-3">
        @foreach ($lagos as $lago)
            @php
                $siembras = $lago->siembrasActivas;
                $foto = $siembras->first()?->foto_especie;
            @endphp

            @php
                // Estado del agua (mismo criterio que el inicio y EvaluadorAlertas): oxígeno fuera de rango = alerta; otro problema = atención.
                // Una medición más vieja que la vigencia de la ración no cuenta como de hoy.
                $medicion = $lago->ultimaMedicion?->medido_at->gte(now()->subHours(\App\Support\CalculadoraRacion::HORAS_MEDICION_VIGENTE)) ? $lago->ultimaMedicion : null;
                $problemas = $medicion ? $medicion->problemas($siembras->pluck('especie')) : [];
                $parametros = array_column($problemas, 'parametro');
                $estadoAgua = $siembras->isEmpty() ? 'libre' : (! $medicion ? 'sin_medicion' : (in_array('oxigeno', $parametros, true) ? 'alerta' : ($parametros ? 'atencion' : 'bien')));
                // Tono e ícono los pone <x-insignia>; «libre» es neutro con el ícono del lago
                [$tonoEstado, $textoEstado] = match ($estadoAgua) {
                    'libre' => ['neutro', __('lagos.libre')],
                    'sin_medicion' => ['info', __('inicio.estado_agua.sin_medicion')],
                    default => [$estadoAgua, __('inicio.estado_agua.'.$estadoAgua)],
                };
            @endphp

            {{-- Ficha del lago: la pestaña dice de un vistazo cómo está el agua (ícono y texto, nunca solo color) --}}
            <x-tarjeta @class([
                'relative mt-3.5 flex flex-col !p-0',
                '!ring-alerta-borde' => $estadoAgua === 'alerta',
                '!ring-atencion-borde' => $estadoAgua === 'atencion',
            ])>
                <x-insignia :tono="$tonoEstado" :icono="$estadoAgua === 'libre' ? 'lagos' : null" pestana como="p">{{ $textoEstado }}</x-insignia>

                <div class="flex items-start gap-4 border-b border-renglon px-5 pt-7 pb-4">
                    @if ($foto)
                        <img src="{{ $foto }}" alt="" loading="lazy" decoding="async"
                             class="h-14 w-14 shrink-0 rounded-ficha object-cover ring-1 ring-renglon">
                    @else
                        <span class="flex h-14 w-14 shrink-0 items-center justify-center rounded-ficha bg-papel-hondo text-marca-700">
                            <x-icono :nombre="$siembras->isNotEmpty() ? 'pez' : 'lagos'" class="h-7 w-7" />
                        </span>
                    @endif

                    <div class="min-w-0 flex-1">
                        <h2 class="truncate text-xl font-bold">
                            <a href="{{ route('admin.lagos.show', $lago) }}" class="underline decoration-transparent underline-offset-4 hover:decoration-tinta">{{ $lago->nombre }}</a>
                        </h2>
                        <p class="text-sm text-tinta-suave">
                            {{ __('lagos.lago_en_tipo', ['tipo' => __('lagos.tipos.'.$lago->tipo)]) }}@if ($siembras->count() > 1) · {{ __('lagos.policultivo') }} @endif
                        </p>
                        @if ($lago->en_retiro)
                            <x-insignia tono="atencion" como="p" class="mt-1.5">{{ __('lagos.retiro_sanitario') }}</x-insignia>
                        @endif
                    </div>
                </div>

                {{-- Lectura del agua: oxígeno y temperatura en grande --}}
                @if ($siembras->isNotEmpty())
                    <div class="border-b border-renglon px-5 py-4">
                        @if ($medicion)
                            <dl class="grid grid-cols-2 gap-4">
                                @foreach ([['oxigeno', $medicion->oxigeno_mg_l, 'mg/L'], ['temperatura', $medicion->temperatura_c, '°C']] as [$parametro, $valor, $unidad])
                                    <div>
                                        <dt class="text-sm text-tinta-suave">{{ __('lagos.ficha.'.$parametro) }}</dt>
                                        <dd @class([
                                            'mt-0.5 text-3xl font-bold tabular-nums',
                                            'text-alerta' => $parametro === 'oxigeno' && in_array('oxigeno', $parametros, true),
                                            'text-atencion' => $parametro === 'temperatura' && in_array('temperatura', $parametros, true),
                                        ])>
                                            @if ($valor !== null)
                                                {{ $numero($valor, 1) }} <span class="text-base font-normal text-tinta-suave">{{ $unidad }}</span>
                                            @else
                                                <span class="text-lg font-normal text-tinta-suave">{{ __('inicio.agua.sin_dato') }}</span>
                                            @endif
                                        </dd>
                                    </div>
                                @endforeach
                            </dl>
                            @if ($problemas)
                                <p @class(['mt-2 text-sm font-semibold', 'text-alerta' => $estadoAgua === 'alerta', 'text-atencion' => $estadoAgua === 'atencion'])>{{ $problemas[0]['texto'] }}</p>
                            @endif
                        @else
                            <p class="flex items-center gap-2 text-base text-tinta-suave">
                                <x-icono nombre="reloj" class="h-5 w-5" /> {{ __('lagos.ficha.sin_medicion') }}
                            </p>
                        @endif
                    </div>
                @endif

                {{-- Aviso «listo para pescar»: lo ven todos; el administrador lo atiende --}}
                @if ($aviso = $lago->avisoPescaPendiente)
                    <div data-aviso-pesca class="flex flex-wrap items-center justify-between gap-2 border-b border-renglon bg-trucha/5 px-5 py-3">
                        <p class="min-w-0 text-sm">
                            <span class="inline-flex items-center gap-1.5 font-bold text-trucha"><x-icono nombre="pez" class="h-4 w-4" />{{ __('lagos.aviso_pesca_listo', ['especie' => $aviso->siembra->nombre_especie]) }}</span>
                            <span class="block text-tinta-suave">{{ __('lagos.aviso_pesca_reportado', ['nombre' => $aviso->usuario->name, 'momento' => $aviso->reportado_at->format('d/m H:i')]) }}@if ($aviso->nota) · {{ $aviso->nota }}@endif</span>
                        </p>
                        @if ($puedeOperar)
                            <x-formulario :action="route('admin.avisos-pesca.atender', $aviso)" class="!space-y-0">
                                <x-boton variante="secundario" class="!min-h-12 !py-2 !text-sm">{{ __('lagos.marcar_atendido') }}</x-boton>
                            </x-formulario>
                        @endif
                    </div>
                @elseif ($puedeAvisarPesca && $siembras->isNotEmpty())
                    <div class="border-b border-renglon px-5 py-3">
                        <x-boton tipo="button" variante="secundario" class="w-full" data-abrir-modal="modal-aviso-pesca-{{ $lago->id }}">
                            <x-icono nombre="pez" class="h-5 w-5" /> {{ __('lagos.reportar_listo_pescar') }}
                        </x-boton>
                    </div>
                    <x-modal id="modal-aviso-pesca-{{ $lago->id }}" :titulo="__('lagos.reportar_listo_pescar_en', ['lago' => $lago->nombre])">
                        <x-formulario :action="route('admin.avisos-pesca.store', $lago)" :data-guardar-sin-conexion="__('layout.cola.resumen_aviso_pesca', ['lago' => $lago->nombre])">
                            @if ($siembras->count() > 1)
                                <x-seleccion nombre="siembra_id" id="aviso-siembra-{{ $lago->id }}" :etiqueta="__('lagos.especie')" :opciones="$siembras->pluck('nombre_especie', 'id')->all()" required />
                            @else
                                <input type="hidden" name="siembra_id" value="{{ $siembras->first()->id }}">
                            @endif
                            <x-campo nombre="nota" id="aviso-nota-{{ $lago->id }}" :etiqueta="__('lagos.nota_opcional')" :placeholder="__('lagos.nota_aviso_ejemplo')" />
                            <div class="grid grid-cols-2 gap-3 pt-2">
                                <x-boton tipo="button" variante="secundario" data-cerrar-modal>{{ __('comun.cancelar') }}</x-boton>
                                <x-boton>{{ __('lagos.enviar_aviso') }}</x-boton>
                            </div>
                        </x-formulario>
                    </x-modal>
                @endif

                @forelse ($siembras as $siembra)
                    <div class="border-b border-renglon">
                        <div class="flex items-center justify-between gap-3 px-5 pt-4">
                            <h3 class="font-bold">{{ $siembra->nombre_especie }}</h3>
                            <span class="text-sm text-tinta-suave">{{ __('lagos.desde_fecha', ['fecha' => $siembra->fecha->format('d/m/Y')]) }}</span>
                        </div>

                        <dl class="mt-3 grid grid-cols-2 gap-px border-y border-renglon bg-renglon">
                            <div class="bg-ficha px-5 py-2.5">
                                <dt class="text-sm text-tinta-suave">{{ __('lagos.peces_vivos') }}</dt>
                                <dd class="text-xl font-bold tabular-nums">{{ $numero($siembra->peces_vivos) }}</dd>
                            </div>
                            <div class="bg-ficha px-5 py-2.5">
                                <dt class="text-sm text-tinta-suave">{{ __('lagos.biomasa') }}</dt>
                                <dd class="text-xl font-bold tabular-nums">{{ $numero($siembra->biomasa_kg, 2) }} <span class="text-sm font-normal text-tinta-suave">kg</span></dd>
                            </div>
                            <div class="bg-ficha px-5 py-2.5">
                                <dt class="text-sm text-tinta-suave">{{ __('lagos.peso_promedio') }}</dt>
                                <dd class="text-xl font-bold tabular-nums">{{ $numero($siembra->peso_actual_g, 2) }} <span class="text-sm font-normal text-tinta-suave">g</span></dd>
                            </div>
                            <div class="bg-ficha px-5 py-2.5">
                                <dt class="text-sm text-tinta-suave">{{ __('lagos.dias_cultivo') }}</dt>
                                <dd class="text-xl font-bold tabular-nums">{{ $siembra->dias_cultivo }}</dd>
                            </div>
                        </dl>

                        @unless ($soloLectura)
                        <div class="space-y-2 p-4">
                            <x-boton tipo="button" variante="secundario" class="w-full" data-abrir-modal="modal-mortalidad-{{ $siembra->id }}" :disabled="! $puedeRegistrar">
                                <x-icono nombre="mortalidad" class="h-5 w-5" /> {{ __('lagos.mortalidad') }}
                            </x-boton>
                            {{-- Muestreo y Finalizar quedan a un paso: finalizar casi no se deshace y no debe estar junto a la acción diaria --}}
                            @if ($puedeOperar)
                                <details class="group">
                                    <summary class="flex min-h-12 cursor-pointer list-none items-center justify-center gap-1.5 rounded-ficha text-base font-semibold text-marca-700 hover:bg-papel-hondo [&::-webkit-details-marker]:hidden">
                                        {{ __('lagos.ficha.mas_acciones') }}
                                        <x-icono nombre="abajo" class="h-5 w-5 transition-transform group-open:rotate-180" />
                                    </summary>
                                    <div class="mt-2 grid grid-cols-2 gap-2">
                                        <x-boton tipo="button" variante="secundario" data-abrir-modal="modal-muestreo-{{ $siembra->id }}">
                                            <x-icono nombre="biomasa" class="h-5 w-5" /> {{ __('lagos.muestreo') }}
                                        </x-boton>
                                        <x-boton tipo="button" variante="secundario" class="!text-alerta" data-abrir-modal="modal-finalizar-{{ $siembra->id }}">
                                            <x-icono nombre="ok" class="h-5 w-5" /> {{ __('lagos.finalizar') }}
                                        </x-boton>
                                    </div>
                                </details>
                            @endif
                        </div>
                        @endunless
                    </div>

                    @unless ($soloLectura)
                    <x-modal id="modal-mortalidad-{{ $siembra->id }}" :titulo="__('lagos.mortalidad_de', ['especie' => $siembra->nombre_especie, 'lago' => $lago->nombre])">
                        <x-formulario :action="route('admin.mortalidades.store', $siembra->id)" :data-guardar-sin-conexion="__('layout.cola.resumen_mortalidad', ['especie' => $siembra->nombre_especie, 'lago' => $lago->nombre])">
                            <x-campo nombre="fecha" id="mortalidad-fecha-{{ $siembra->id }}" :etiqueta="__('comun.fecha')" tipo="date" :valor="\App\Support\Region::ahora()->format('Y-m-d')" required />
                            <x-campo nombre="cantidad" id="mortalidad-cantidad-{{ $siembra->id }}" :etiqueta="__('lagos.peces_muertos')" tipo="number" min="1" :max="$siembra->peces_vivos" inputmode="numeric" required
                                     :ayuda="__('lagos.hay_peces_vivos', ['cantidad' => $numero($siembra->peces_vivos)])" />

                            <div class="grid grid-cols-2 gap-3 pt-2">
                                <x-boton tipo="button" variante="secundario" data-cerrar-modal>{{ __('comun.cancelar') }}</x-boton>
                                <x-boton>{{ __('lagos.registrar') }}</x-boton>
                            </div>
                        </x-formulario>
                    </x-modal>
                    @endunless

                    @if ($puedeOperar)
                        <x-modal id="modal-muestreo-{{ $siembra->id }}" :titulo="__('lagos.muestreo_de', ['especie' => $siembra->nombre_especie, 'lago' => $lago->nombre])">
                            <x-formulario :action="route('admin.muestreos.store', $siembra->id)">
                                <x-campo nombre="fecha" id="muestreo-fecha-{{ $siembra->id }}" :etiqueta="__('comun.fecha')" tipo="date" :valor="\App\Support\Region::ahora()->format('Y-m-d')" required />
                                <div class="grid grid-cols-2 gap-4" data-muestreo>
                                    <x-campo nombre="peces_pesados" id="muestreo-peces-{{ $siembra->id }}" :etiqueta="__('lagos.peces_pesados')" tipo="number" min="1" inputmode="numeric" required data-peces />
                                    <x-campo nombre="peso_total_g" id="muestreo-peso-{{ $siembra->id }}" :etiqueta="__('lagos.peso_total_g')" tipo="number" step="0.1" min="0.1" inputmode="decimal" required data-peso-total />
                                    {{-- Solo informativo: el servidor calcula el promedio que se guarda --}}
                                    <div class="col-span-2">
                                        <label for="muestreo-promedio-{{ $siembra->id }}" class="mb-1.5 block text-base font-semibold text-tinta">{{ __('lagos.peso_promedio_calculado') }}</label>
                                        <output id="muestreo-promedio-{{ $siembra->id }}" data-promedio aria-live="polite"
                                                class="flex min-h-12 w-full items-center rounded-ficha border border-renglon bg-papel-hondo px-4 text-xl font-bold tabular-nums text-tinta">—</output>
                                        <p class="mt-1 text-sm text-tinta-suave">{{ __('lagos.peso_anterior', ['gramos' => $numero($siembra->peso_actual_g, 2)]) }}</p>
                                    </div>
                                </div>

                                <div class="grid grid-cols-2 gap-3 pt-2">
                                    <x-boton tipo="button" variante="secundario" data-cerrar-modal>{{ __('comun.cancelar') }}</x-boton>
                                    <x-boton>{{ __('comun.guardar') }}</x-boton>
                                </div>
                            </x-formulario>
                        </x-modal>

                        <x-modal id="modal-finalizar-{{ $siembra->id }}" :titulo="__('lagos.finalizar_siembra')">
                            <x-formulario :action="route('admin.siembras.finalizar', $siembra->id)">
                                <p class="text-base text-tinta">
                                    {!! __('lagos.confirmar_finalizar', [
                                        'especie' => '<strong>'.e($siembra->nombre_especie).'</strong>',
                                        'lago' => '<strong>'.e($lago->nombre).'</strong>',
                                        'peces' => e($numero($siembra->peces_vivos)),
                                        'kg' => e($numero($siembra->biomasa_kg, 2)),
                                    ]) !!}
                                </p>

                                <div class="grid grid-cols-2 gap-3 pt-2">
                                    <x-boton tipo="button" variante="secundario" data-cerrar-modal>{{ __('comun.cancelar') }}</x-boton>
                                    <x-boton variante="peligro">{{ __('lagos.finalizar') }}</x-boton>
                                </div>
                            </x-formulario>
                        </x-modal>
                    @endif
                @empty
                    <p class="px-5 py-4 text-base text-tinta-suave">{{ __('lagos.este_lago_no_tiene_siembras') }}</p>
                @endforelse

                <div @class(['mt-auto grid gap-2 p-5', 'grid-cols-2' => $puedeOperar && $siembras->isNotEmpty()])>
                    @if ($siembras->isNotEmpty() && ! $soloLectura)
                        <x-boton tipo="button" data-abrir-modal="modal-racion-{{ $lago->id }}" :disabled="! $puedeRegistrar">
                            <x-icono nombre="alimentar" class="h-5 w-5" /> {{ __('lagos.alimentar') }}
                        </x-boton>
                    @endif
                    @if ($puedeOperar)
                        <x-boton tipo="button" variante="secundario" data-abrir-modal="modal-siembra-{{ $lago->id }}">
                            <x-icono nombre="mas" class="h-5 w-5" /> {{ $siembras->isNotEmpty() ? __('lagos.especie') : __('lagos.registrar_siembra') }}
                        </x-boton>
                    @endif
                    <a href="{{ route('admin.lagos.show', $lago) }}" @class([
                        'flex min-h-12 items-center justify-center rounded-ficha text-base font-semibold text-marca-700 underline decoration-renglon-fuerte underline-offset-4 hover:decoration-marca-700',
                        'col-span-2' => $puedeOperar && $siembras->isNotEmpty(),
                    ])>{{ __('lagos.ver_detalle_e_historial') }}</a>
                </div>

                @if ($siembras->isNotEmpty() && ! $soloLectura)
                    <x-modal id="modal-racion-{{ $lago->id }}" :titulo="__('lagos.alimentar_lago', ['lago' => $lago->nombre])">
                        <x-formulario :action="route('admin.raciones.store', $lago->id)" :data-guardar-sin-conexion="__('layout.cola.resumen_racion', ['lago' => $lago->nombre])">
                            <x-campo nombre="fecha" id="racion-fecha-{{ $lago->id }}" :etiqueta="__('comun.fecha')" tipo="date" :valor="\App\Support\Region::ahora()->format('Y-m-d')" required />

                            @if ($stocksDisponibles->isEmpty())
                                <x-aviso tono="alerta">{{ __('lagos.no_hay_alimento_bodega') }}</x-aviso>
                            @else
                                <x-seleccion nombre="tipo_concentrado" id="racion-concentrado-{{ $lago->id }}" :etiqueta="__('lagos.concentrado')" required
                                             :opciones="$stocksDisponibles->mapWithKeys(fn ($stock) => [$stock->tipo_concentrado => __('lagos.concentrado_disponible', ['tipo' => $stock->tipo_concentrado, 'kg' => $numero($stock->cantidad_kg, 1)])])->all()" />
                            @endif

                            @php $sugerida = $raciones[$lago->id]; @endphp
                            <x-campo nombre="cantidad_kg" id="racion-cantidad-{{ $lago->id }}" :etiqueta="__('lagos.cantidad_esta_toma_kg')" tipo="number" step="0.1" min="0.1" inputmode="decimal" required
                                     :valor="$sugerida['kg_por_toma'] > 0 ? $sugerida['kg_por_toma'] : null"
                                     :ayuda="$sugerida['tomas'] > 0
                                         ? __('lagos.racion_sugerida_ayuda', ['kg' => $numero($sugerida['kg_por_toma'], 1), 'tomas' => $sugerida['tomas'], 'total' => $numero($sugerida['total_kg_dia'], 1)])
                                             .($sugerida['factor'] < 1 ? ' '.__('lagos.racion_reducida_agua') : '')
                                         : null" />

                            <div class="grid grid-cols-2 gap-3 pt-2">
                                <x-boton tipo="button" variante="secundario" data-cerrar-modal>{{ __('comun.cancelar') }}</x-boton>
                                <x-boton :disabled="$stocksDisponibles->isEmpty()">{{ __('comun.guardar') }}</x-boton>
                            </div>
                        </x-formulario>
                    </x-modal>
                @endif

                @if ($puedeOperar)
                    <x-modal id="modal-siembra-{{ $lago->id }}" :titulo="$siembras->isNotEmpty() ? __('lagos.agregar_especie_a', ['lago' => $lago->nombre]) : __('lagos.siembra_en', ['lago' => $lago->nombre])">
                        <x-formulario :action="route('admin.siembras.store', $lago->id)">
                            @if ($siembras->isNotEmpty())
                                <x-aviso tono="info" icono="pez">
                                    {{ __('lagos.ayuda_policultivo', ['especies' => $siembras->pluck('nombre_especie')->join(', ', ' '.__('comun.y').' ')]) }}
                                </x-aviso>
                            @endif
                            <x-seleccion nombre="especie_id" id="siembra-especie-{{ $lago->id }}" :etiqueta="__('lagos.especie')" :opciones="$especies" required />
                            <x-campo nombre="fecha" id="siembra-fecha-{{ $lago->id }}" :etiqueta="__('lagos.fecha_siembra')" tipo="date" :valor="\App\Support\Region::ahora()->format('Y-m-d')" required />
                            <div class="grid grid-cols-2 gap-4">
                                <x-campo nombre="cantidad_alevinos" id="siembra-alevinos-{{ $lago->id }}" :etiqueta="__('lagos.alevinos')" tipo="number" min="1" inputmode="numeric" required />
                                <x-campo nombre="peso_promedio_inicial_g" id="siembra-peso-{{ $lago->id }}" :etiqueta="__('lagos.peso_inicial_g')" tipo="number" step="0.1" min="0.1" inputmode="decimal" required />
                                <x-campo nombre="proveedor_alevinos" id="siembra-proveedor-{{ $lago->id }}" :etiqueta="__('lagos.proveedor_opcional')" />
                                <x-campo nombre="lote_alevinos" id="siembra-lote-{{ $lago->id }}" :etiqueta="__('lagos.lote_opcional')" />
                            </div>

                            @if ($siembras->isNotEmpty())
                                {{-- Agua fría con cálida: es una advertencia que se confirma, no un bloqueo --}}
                                <label class="flex min-h-12 items-start gap-3 rounded-ficha border border-atencion-borde bg-atencion-fondo p-4 text-base font-semibold text-atencion">
                                    <input type="checkbox" name="confirmar_clima" value="1" class="mt-0.5 h-5 w-5 shrink-0 rounded" @checked(old('confirmar_clima'))>
                                    <span>{{ __('lagos.confirmar_clima') }}</span>
                                </label>
                                @error('confirmar_clima')
                                    <p class="flex items-start gap-1.5 text-sm font-semibold text-alerta"><x-icono nombre="alerta" class="mt-px h-4 w-4" /> {{ $message }}</p>
                                @enderror
                            @endif

                            <div class="grid grid-cols-2 gap-3 pt-2">
                                <x-boton tipo="button" variante="secundario" data-cerrar-modal>{{ __('comun.cancelar') }}</x-boton>
                                <x-boton>{{ __('comun.guardar') }}</x-boton>
                            </div>
                        </x-formulario>
                    </x-modal>
                @endif
            </x-tarjeta>
        @endforeach
    </div>

    @if ($puedeOperar)
        <x-modal id="modal-lago" :titulo="__('lagos.nuevo_lago')">
            <x-formulario :action="route('admin.lagos.store')">
                <x-campo nombre="nombre" id="lago-nombre" :etiqueta="__('lagos.nombre_codigo')" required />
                <x-seleccion nombre="tipo" id="lago-tipo" :etiqueta="__('lagos.tipo')" :opciones="$tipos" required />

                <div class="grid grid-cols-2 gap-3 pt-2">
                    <x-boton tipo="button" variante="secundario" data-cerrar-modal>{{ __('comun.cancelar') }}</x-boton>
                    <x-boton>{{ __('comun.guardar') }}</x-boton>
                </div>
            </x-formulario>
        </x-modal>
    @endif
@endsection
