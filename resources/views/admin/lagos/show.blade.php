@extends('layouts.app')

@section('titulo', __('lagos.lago_nombre', ['lago' => $lago->nombre]))

@section('contenido')
    @php
        // Solo el administrador (o el propietario que también lo es) opera los lagos; el propietario consulta
        $puedeOperar = auth()->user()->tieneRol('administrador');
        $numero = fn ($valor, $decimales = 0) => \App\Support\Formato::numero((float) $valor, $decimales);
        $medicion = $lago->ultimaMedicion;
        // Estado del agua, con el mismo criterio que la lista de lagos y el inicio (EvaluadorAlertas):
        // oxígeno fuera de rango = alerta; otro problema = atención; una medición vieja no cuenta como de hoy.
        $vigente = $medicion?->medido_at->gte(now()->subHours(\App\Support\CalculadoraRacion::HORAS_MEDICION_VIGENTE));
        $parametros = $medicion ? array_column($medicion->problemas($lago->siembrasActivas->pluck('especie')), 'parametro') : [];
        $estadoAgua = ! $vigente ? 'sin_medicion' : (in_array('oxigeno', $parametros, true) ? 'alerta' : ($parametros ? 'atencion' : 'bien'));
        // Íconos del historial: el tono del evento pasa a los colores de estado de la libreta
        $tonos = [
            'positivo' => 'bg-bien-fondo text-bien',
            'alerta' => 'bg-atencion-fondo text-atencion',
            'neutro' => 'bg-papel-hondo text-tinta-suave',
        ];
    @endphp

    {{-- Volver a la lista: la flecha sale del juego de íconos (no un carácter de texto) --}}
    <a href="{{ route('admin.lagos.index') }}" class="-ml-1 mb-2 inline-flex min-h-12 items-center gap-1 text-base font-semibold text-marca-700 underline decoration-renglon-fuerte underline-offset-4 hover:decoration-marca-700">
        <x-icono nombre="abajo" class="h-5 w-5 rotate-90" /> {{ __('lagos.lagos') }}
    </a>

    <x-encabezado :titulo="__('lagos.lago_nombre', ['lago' => $lago->nombre])"
                  :subtitulo="__('lagos.lago_en_tipo', ['tipo' => __('lagos.tipos.'.$lago->tipo)]).' · '.($lago->siembrasActivas->isEmpty() ? __('lagos.sin_siembras_activas') : __('lagos.especies_en_cultivo', ['cantidad' => \App\Support\LotesPorEspecie::agrupar($lago->siembrasActivas)->count()]))">
        @if ($puedeOperar)
            {{-- Quién mide el agua: ajuste de la finca (F4) --}}
            @if (\App\Support\Ajustes\QuienMideAgua::puedeMedir(\App\Models\Rol::ADMINISTRADOR))
                <x-boton tipo="button" variante="secundario" data-abrir-modal="modal-medicion">
                    <x-icono nombre="gota" class="h-5 w-5" /> {{ __('lagos.medir_agua') }}
                </x-boton>
            @else
                <p class="text-sm text-tinta-suave">{{ \App\Support\Ajustes\QuienMideAgua::aviso() }}</p>
            @endif
            <x-boton tipo="button" variante="secundario" data-abrir-modal="modal-tratamiento">
                <x-icono nombre="alerta" class="h-5 w-5" /> {{ __('lagos.tratamiento') }}
            </x-boton>
        @endif
    </x-encabezado>

    @foreach ($lago->tratamientosEnRetiro as $tratamiento)
        <x-aviso tono="atencion" class="mb-3">
            <strong class="font-bold">{{ __('lagos.retiro_hasta', ['fecha' => $tratamiento->finRetiro()->format('d/m/Y')]) }}</strong>
            <span class="font-normal">{{ __('lagos.retiro_detalle', ['producto' => $tratamiento->producto, 'motivo' => $tratamiento->motivo]) }}</span>
        </x-aviso>
    @endforeach

    @if ($alertasAgua !== [])
        <x-aviso tono="alerta" class="mb-3">
            <p>{{ __('lagos.alertas_ultima_medicion', ['momento' => $medicion->medido_at->formatoHora('d/m H:i')]) }}</p>
            <ul class="mt-1 list-disc space-y-0.5 pl-5 font-normal">
                @foreach ($alertasAgua as $alerta)
                    <li>{{ $alerta }}</li>
                @endforeach
            </ul>
        </x-aviso>
    @endif

    <div class="mt-8 grid items-start gap-8 lg:grid-cols-3">
        <div class="space-y-8 lg:col-span-2">
            <section aria-labelledby="siembras-titulo">
                <h2 id="siembras-titulo" class="mb-4 text-seccion font-bold">{{ __('lagos.siembras_activas') }}</h2>

                @php
                    $grupos = \App\Support\LotesPorEspecie::agrupar($lago->siembrasActivas);
                    $etiquetasLote = \App\Support\LotesPorEspecie::etiquetas($lago->siembrasActivas);
                @endphp
                @forelse ($grupos as $grupo)
                    @if ($grupo['lotes'] === 1)
                        {{-- Un solo lote de la especie: como siempre --}}
                        <x-tarjeta class="mb-4 overflow-hidden !p-0">
                            @include('admin.lagos.partials.lote-detalle', ['siembra' => $grupo['siembras']->first()])
                        </x-tarjeta>
                    @else
                        {{-- Varios lotes de la misma especie: el total y cada lote como detalle que se abre --}}
                        <x-tarjeta class="mb-4 overflow-hidden !p-0" data-especie-lotes="{{ $grupo['lotes'] }}">
                            <div class="flex items-center gap-4 px-5 py-4">
                                @if ($grupo['foto'])
                                    <img src="{{ $grupo['foto'] }}" alt="" loading="lazy" decoding="async" class="h-12 w-12 rounded-ficha object-cover ring-1 ring-renglon">
                                @else
                                    <span class="flex h-12 w-12 items-center justify-center rounded-ficha bg-papel-hondo text-marca-700"><x-icono nombre="pez" /></span>
                                @endif
                                <div class="min-w-0 flex-1">
                                    <h3 class="text-lg font-bold">{{ $grupo['especie'] }}</h3>
                                    <p class="text-sm text-tinta-suave">{{ __('lagos.lotes.n_lotes', ['lotes' => $grupo['lotes']]) }}</p>
                                </div>
                            </div>
                            {{-- Peces y biomasa arriba; el peso con su rango en una fila completa (no cabe en una columna del teléfono) --}}
                            <dl class="grid grid-cols-2 gap-px border-y border-renglon bg-renglon">
                                @foreach ([
                                    [__('lagos.peces_vivos'), $numero($grupo['peces'])],
                                    [__('lagos.biomasa'), $numero($grupo['biomasa_kg'], 2).' kg'],
                                    [__('lagos.peso_promedio'), \App\Support\LotesPorEspecie::textoPeso($grupo)],
                                ] as $posicion => [$etiqueta, $valor])
                                    <div @class(['bg-ficha px-4 py-3 sm:px-5', 'col-span-2' => $posicion === 2])>
                                        <dt class="text-sm text-tinta-suave">{{ $etiqueta }}</dt>
                                        <dd class="text-lg font-bold tabular-nums">{{ $valor }}</dd>
                                    </div>
                                @endforeach
                            </dl>
                            @foreach ($grupo['siembras'] as $indice => $siembra)
                                <details class="group border-b border-renglon last:border-b-0">
                                    <summary class="flex min-h-12 cursor-pointer list-none items-center justify-between gap-3 px-5 py-3 text-base font-semibold text-tinta hover:bg-papel-hondo [&::-webkit-details-marker]:hidden">
                                        <span>{{ __('lagos.lotes.resumen', ['numero' => $indice + 1, 'origen' => $siembra->textoOrigen(), 'peces' => $numero($siembra->peces_vivos)]) }}</span>
                                        <x-icono nombre="abajo" class="h-5 w-5 shrink-0 transition-transform group-open:rotate-180" />
                                    </summary>
                                    <div class="border-t border-renglon">
                                        @include('admin.lagos.partials.lote-detalle', ['siembra' => $siembra, 'numero_lote' => $indice + 1])
                                    </div>
                                </details>
                            @endforeach
                        </x-tarjeta>
                    @endif
                @empty
                    <x-vacio icono="lagos" :mensaje="__('lagos.este_lago_no_tiene_siembras')" />
                @endforelse

                {{-- Traslado: un modal por lote (los ids no cambian) --}}
                @foreach ($lago->siembrasActivas as $siembra)
                @if ($puedeOperar && $otrosLagos !== [])
                    <x-modal id="modal-traslado-{{ $siembra->id }}" :titulo="__('lagos.trasladar_especie', ['especie' => $etiquetasLote[$siembra->id]])">
                        <x-formulario :action="route('admin.traslados.store', $siembra)">
                            <x-aviso tono="info" icono="lagos">{{ __('lagos.ayuda_traslado', ['especie' => $siembra->nombre_especie]) }}</x-aviso>
                            <x-seleccion nombre="lago_destino_id" id="traslado-destino-{{ $siembra->id }}" :etiqueta="__('lagos.lago_destino')" :opciones="$otrosLagos" required />
                            <x-campo nombre="fecha" id="traslado-fecha-{{ $siembra->id }}" :etiqueta="__('comun.fecha')" tipo="date" :valor="\App\Support\Region::ahora()->toDateString()" required />
                            <div class="grid grid-cols-2 gap-4">
                                <x-campo nombre="cantidad" id="traslado-cantidad-{{ $siembra->id }}" :etiqueta="__('lagos.peces')" tipo="number" min="1" :max="$siembra->peces_vivos" inputmode="numeric" required
                                         :ayuda="__('lagos.maximo', ['cantidad' => $numero($siembra->peces_vivos)])" />
                                <x-campo nombre="peso_promedio_g" id="traslado-peso-{{ $siembra->id }}" :etiqueta="__('lagos.peso_promedio_g')" tipo="number" step="0.1" min="0.1" inputmode="decimal"
                                         :valor="$siembra->peso_actual_g" required />
                            </div>
                            <x-campo nombre="observaciones" id="traslado-obs-{{ $siembra->id }}" :etiqueta="__('lagos.observaciones_opcional')" />
                            {{-- Agua fría con cálida en el lago de destino: advertencia que se confirma --}}
                            <label class="flex min-h-12 items-start gap-3 rounded-ficha border border-atencion-borde bg-atencion-fondo p-4 text-base font-semibold text-atencion">
                                <input type="checkbox" name="confirmar_clima" value="1" class="mt-0.5 h-5 w-5 shrink-0 rounded">
                                <span>{{ __('lagos.confirmar_clima') }}</span>
                            </label>
                            @error('confirmar_clima')
                                <p class="flex items-start gap-1.5 text-sm font-semibold text-alerta"><x-icono nombre="alerta" class="mt-px h-4 w-4" /> {{ $message }}</p>
                            @enderror

                            <div class="grid grid-cols-2 gap-3 pt-2">
                                <x-boton tipo="button" variante="secundario" data-cerrar-modal>{{ __('comun.cancelar') }}</x-boton>
                                <x-boton>{{ __('lagos.trasladar') }}</x-boton>
                            </div>
                        </x-formulario>
                    </x-modal>
                @endif
                @endforeach
            </section>

            <section aria-labelledby="historial-titulo">
                <h2 id="historial-titulo" class="mb-4 text-seccion font-bold">{{ __('lagos.historial') }}</h2>

                @if ($historial->isEmpty())
                    <x-vacio icono="reloj" :mensaje="__('lagos.todavia_no_hay_registros_este')" />
                @else
                    {{-- Historial como renglones de la libreta: fecha a la derecha, quién y cuánto debajo --}}
                    <ol class="divide-y divide-renglon rounded-ficha bg-ficha shadow-ficha ring-1 ring-renglon">
                        @foreach ($historial as $evento)
                            <li class="flex gap-4 px-5 py-3.5">
                                <span class="mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-full {{ $tonos[$evento['tono']] }}">
                                    <x-icono :nombre="$evento['icono']" class="h-5 w-5" />
                                </span>
                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-baseline justify-between gap-x-3">
                                        <p class="font-bold">{{ $evento['titulo'] }}</p>
                                        <time class="text-sm tabular-nums text-tinta-suave" datetime="{{ $evento['fecha']->toDateString() }}">{{ $evento['fecha']->format('d/m/Y') }}</time>
                                    </div>
                                    @if ($evento['detalle'] !== '' || $evento['responsable'])
                                        <p class="text-sm text-tinta-suave">{{ collect([$evento['detalle'], $evento['responsable']])->filter()->join(' · ') }}</p>
                                    @endif
                                    @if ($evento['enlace'] && auth()->user()->tieneRol(\App\Models\Rol::PROPIETARIO, \App\Models\Rol::ADMINISTRADOR))
                                        <a href="{{ $evento['enlace'] }}" class="mt-1 inline-flex min-h-11 items-center text-sm font-bold text-marca-700 underline decoration-renglon-fuerte underline-offset-4 hover:decoration-marca-700">{{ __('cosechas.ver_detalle') }}</a>
                                    @endif
                                </div>
                            </li>
                        @endforeach
                    </ol>
                @endif
            </section>
        </div>

        {{-- En celular la columna lateral (agua y ración) va primero: es lo urgente del lago; en pantallas anchas queda a la derecha --}}
        <aside class="order-first space-y-8 lg:order-none">
            {{-- Calidad del agua: la lectura del lago, con su pestaña de estado (ícono y texto) --}}
            <x-tarjeta :titulo="__('lagos.calidad_agua')" :subtitulo="$medicion ? __('lagos.ultima_medicion', ['momento' => $medicion->medido_at->formatoHora('d/m/Y H:i')]) : null"
                       @class(['relative mt-3.5 !pt-7', '!ring-alerta-borde' => $estadoAgua === 'alerta', '!ring-atencion-borde' => $estadoAgua === 'atencion'])>
                <x-insignia :tono="['sin_medicion' => 'info'][$estadoAgua] ?? $estadoAgua" pestana como="p">{{ __('inicio.estado_agua.'.$estadoAgua) }}</x-insignia>

                @if ($medicion)
                    <dl class="grid grid-cols-2 gap-4">
                        @foreach ([['oxigeno', $medicion->oxigeno_mg_l, 'mg/L', 2], ['temperatura', $medicion->temperatura_c, '°C', 1]] as [$parametro, $valor, $unidad, $decimales])
                            <div>
                                <dt class="text-sm text-tinta-suave">{{ __('lagos.ficha.'.$parametro) }}</dt>
                                <dd @class([
                                    'mt-0.5 text-cifra font-bold tabular-nums',
                                    'text-alerta' => $parametro === 'oxigeno' && in_array('oxigeno', $parametros, true),
                                    'text-atencion' => $parametro === 'temperatura' && in_array('temperatura', $parametros, true),
                                ])>
                                    @if ($valor !== null)
                                        {{ $numero($valor, $decimales) }} <span class="text-base font-normal tracking-normal text-tinta-suave">{{ $unidad }}</span>
                                    @else
                                        <span class="text-lg font-normal tracking-normal text-tinta-suave">{{ __('inicio.agua.sin_dato') }}</span>
                                    @endif
                                </dd>
                            </div>
                        @endforeach
                    </dl>

                    {{-- El resto de parámetros, solo los que se midieron --}}
                    <dl class="mt-4 divide-y divide-renglon border-t border-renglon text-base">
                        @foreach ([
                            'ph' => [__('lagos.ph'), $medicion->ph !== null ? $numero($medicion->ph, 2) : null],
                            'amonio' => [__('lagos.amonio'), $medicion->amonio_mg_l !== null ? $numero($medicion->amonio_mg_l, 2).' mg/L' : null],
                            'nitritos' => [__('lagos.nitritos'), $medicion->nitritos_mg_l !== null ? $numero($medicion->nitritos_mg_l, 2).' mg/L' : null],
                            'transparencia' => [__('lagos.transparencia'), $medicion->transparencia_cm !== null ? $medicion->transparencia_cm.' cm' : null],
                        ] as $parametro => [$etiqueta, $valor])
                            @continue($valor === null)
                            <div class="flex items-baseline justify-between gap-3 py-2.5">
                                <dt class="text-tinta-suave">{{ $etiqueta }}</dt>
                                <dd @class(['font-bold tabular-nums', 'text-atencion' => in_array($parametro, $parametros, true)])>{{ $valor }}</dd>
                            </div>
                        @endforeach
                    </dl>
                @else
                    <p class="flex items-center gap-2 text-base text-tinta-suave"><x-icono nombre="reloj" class="h-5 w-5" /> {{ __('lagos.aun_no_hay_mediciones') }}</p>
                @endif
            </x-tarjeta>

            @if ($racion['filas'] !== [])
                <x-tarjeta :titulo="__('lagos.racion_sugerida')" :subtitulo="__('lagos.segun_biomasa_peso_promedio_valores')">
                    <p class="text-cifra font-bold tabular-nums">
                        {{ $numero($racion['total_kg_dia'], 1) }} <span class="text-base font-normal tracking-normal text-tinta-suave">{{ __('lagos.kg_dia') }}</span>
                    </p>
                    <p class="mt-1 text-base text-tinta-suave">
                        {{ __('lagos.tomas_de', ['tomas' => $racion['tomas'], 'kg' => $numero($racion['kg_por_toma'], 1)]) }}
                    </p>

                    @if ($racion['factor'] < 1)
                        <x-aviso tono="atencion" class="mt-4 text-sm">{{ __('lagos.racion_reducida', ['horas' => \App\Support\CalculadoraRacion::HORAS_MEDICION_VIGENTE]) }}</x-aviso>
                    @endif

                    <dl class="mt-4 divide-y divide-renglon border-t border-renglon text-sm">
                        @foreach ($racion['filas'] as $fila)
                            <div class="py-2.5">
                                <dt class="flex justify-between gap-3 text-base font-bold">
                                    <span>{{ $fila['especie'] }}</span>
                                    <span class="shrink-0 whitespace-nowrap tabular-nums">{{ __('lagos.kg_por_dia', ['kg' => $numero($fila['kg_dia'], 1)]) }}</span>
                                </dt>
                                <dd class="text-tinta-suave">
                                    {{ __('lagos.porcentaje_de_biomasa', ['porcentaje' => $numero($fila['porcentaje'], 1), 'kg' => $numero($fila['biomasa_kg'], 1)]) }}
                                    @if ($fila['proteina'])
                                        · {{ __('lagos.etapa_proteina', ['etapa' => __('lagos.etapas.'.$fila['etapa']), 'proteina' => $fila['proteina']]) }}
                                    @endif
                                </dd>
                            </div>
                        @endforeach
                    </dl>
                </x-tarjeta>
            @endif

            {{-- Especies sin tipo de alimentación: no hay ración sugerida, se registra a mano --}}
            @if ($racion['sin_sugerencia'] !== [])
                <x-aviso tono="atencion" icono="alimentar">{{ __('lagos.racion_manual', ['especies' => implode(', ', $racion['sin_sugerencia'])]) }}</x-aviso>
            @endif

            {{-- Avisos del rango común de agua (especies sin rangos o sin rango común) --}}
            @if ($avisosRango !== [])
                <x-tarjeta :titulo="__('lagos.rango.titulo')">
                    <ul class="space-y-2.5 text-base">
                        @foreach ($avisosRango as $aviso)
                            <li class="flex items-start gap-2 font-semibold text-atencion"><x-icono nombre="alerta" class="mt-0.5 h-5 w-5" /> {{ $aviso }}</li>
                        @endforeach
                    </ul>
                </x-tarjeta>
            @endif

            @if ($siembrasFinalizadas->isNotEmpty())
                <x-tarjeta :titulo="__('lagos.siembras_finalizadas')">
                    <ul class="-mt-1 divide-y divide-renglon">
                        @foreach ($siembrasFinalizadas as $siembra)
                            <li class="py-2.5">
                                <p class="font-bold">{{ $siembra->nombre_especie }}</p>
                                <p class="text-sm text-tinta-suave">
                                    {{ $siembra->fecha->format('d/m/Y') }} - {{ $siembra->finalizada_at->format('d/m/Y') }}
                                    · {{ __('lagos.sembrados', ['cantidad' => $numero($siembra->cantidad_alevinos)]) }}
                                </p>
                            </li>
                        @endforeach
                    </ul>
                </x-tarjeta>
            @endif
        </aside>
    </div>

    @if ($puedeOperar)
        <x-modal id="modal-medicion" :titulo="__('lagos.calidad_agua_de', ['lago' => $lago->nombre])">
            <x-formulario :action="route('admin.mediciones-agua.store', $lago)">
                <x-campo nombre="medido_at" :etiqueta="__('lagos.fecha_hora')" tipo="datetime-local" data-ver-hora :valor="\App\Support\Region::ahora()->format('Y-m-d\TH:i')" required />
                <div class="grid grid-cols-2 gap-4">
                    <x-campo nombre="temperatura_c" :etiqueta="__('lagos.temperatura_c')" tipo="number" step="0.1" inputmode="decimal" />
                    <x-campo nombre="oxigeno_mg_l" :etiqueta="__('lagos.oxigeno_mg_l')" tipo="number" step="0.01" inputmode="decimal" />
                    <x-campo nombre="ph" :etiqueta="__('lagos.ph')" tipo="number" step="0.01" inputmode="decimal" />
                    <x-campo nombre="transparencia_cm" :etiqueta="__('lagos.transparencia_cm')" tipo="number" inputmode="numeric" />
                    <x-campo nombre="amonio_mg_l" :etiqueta="__('lagos.amonio_mg_l')" tipo="number" step="0.001" inputmode="decimal" />
                    <x-campo nombre="nitritos_mg_l" :etiqueta="__('lagos.nitritos_mg_l')" tipo="number" step="0.001" inputmode="decimal" />
                </div>
                <p class="text-sm text-tinta-suave">{{ __('lagos.llena_solo_mediste') }}</p>
                <x-campo nombre="observaciones" id="medicion-obs" :etiqueta="__('lagos.observaciones_opcional')" />

                <div class="grid grid-cols-2 gap-3 pt-2">
                    <x-boton tipo="button" variante="secundario" data-cerrar-modal>{{ __('comun.cancelar') }}</x-boton>
                    <x-boton>{{ __('comun.guardar') }}</x-boton>
                </div>
            </x-formulario>
        </x-modal>

        <x-modal id="modal-tratamiento" :titulo="__('lagos.tratamiento_de', ['lago' => $lago->nombre])">
            <x-formulario :action="route('admin.tratamientos.store', $lago)">
                <x-campo nombre="motivo" :etiqueta="__('lagos.motivo_diagnostico')" required />
                <div class="grid grid-cols-2 gap-4">
                    <x-campo nombre="producto" :etiqueta="__('lagos.producto')" required />
                    <x-campo nombre="registro_sanitario" :etiqueta="__('region.registro_sanitario_opcional')" />
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <x-campo nombre="dosis" :etiqueta="__('lagos.dosis')" :placeholder="__('lagos.ej_2_g_kg_alimento')" required />
                    <x-seleccion nombre="via" :etiqueta="__('lagos.via')" :opciones="$vias" required />
                    <x-campo nombre="fecha_inicio" :etiqueta="__('lagos.inicio')" tipo="date" :valor="\App\Support\Region::ahora()->toDateString()" required />
                    <x-campo nombre="fecha_fin" :etiqueta="__('lagos.fin')" tipo="date" :valor="\App\Support\Region::ahora()->toDateString()" required />
                </div>
                <x-campo nombre="dias_retiro" :etiqueta="__('lagos.dias_retiro')" tipo="number" min="0" max="365" inputmode="numeric" valor="0" required
                         :ayuda="__('lagos.dias_despues_fin_pescado_no')" />
                <x-campo nombre="observaciones" id="tratamiento-obs" :etiqueta="__('lagos.observaciones_opcional')" />

                <div class="grid grid-cols-2 gap-3 pt-2">
                    <x-boton tipo="button" variante="secundario" data-cerrar-modal>{{ __('comun.cancelar') }}</x-boton>
                    <x-boton>{{ __('comun.guardar') }}</x-boton>
                </div>
            </x-formulario>
        </x-modal>
    @endif
@endsection
