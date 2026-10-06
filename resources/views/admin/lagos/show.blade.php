@extends('layouts.app')

@section('titulo', 'Lago '.$lago->nombre)

@section('contenido')
    @php
        $esTecnico = auth()->user()->tieneRol('propietario') || auth()->user()->tieneRol('administrador');
        $numero = fn ($valor, $decimales = 0) => number_format((float) $valor, $decimales, ',', '.');
        $medicion = $lago->ultimaMedicion;
        $tonos = [
            'positivo' => 'bg-marca-100 text-marca-700',
            'alerta' => 'bg-amber-100 text-amber-700',
            'neutro' => 'bg-slate-100 text-slate-600',
        ];
    @endphp

    <a href="{{ route('admin.lagos.index') }}" class="mb-4 inline-flex min-h-11 items-center gap-1 text-base font-medium text-marca-700 hover:underline">
        ← Lagos
    </a>

    <x-encabezado :titulo="'Lago '.$lago->nombre"
                  :subtitulo="'Lago en '.$lago->tipo.' · '.($lago->siembrasActivas->isEmpty() ? 'sin siembras activas' : $lago->siembrasActivas->count().' especie(s) en cultivo')">
        @if ($esTecnico)
            <x-boton tipo="button" variante="secundario" data-abrir-modal="modal-medicion">
                <x-icono nombre="reloj" class="h-5 w-5" /> Medir agua
            </x-boton>
            <x-boton tipo="button" variante="secundario" data-abrir-modal="modal-tratamiento">
                <x-icono nombre="alerta" class="h-5 w-5" /> Tratamiento
            </x-boton>
        @endif
    </x-encabezado>

    @foreach ($lago->tratamientosEnRetiro as $tratamiento)
        <div role="status" class="mb-3 flex items-start gap-3 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-base text-amber-900">
            <x-icono nombre="alerta" class="mt-0.5 h-5 w-5" />
            <span>
                <strong class="font-semibold">Retiro sanitario hasta el {{ $tratamiento->finRetiro()->format('d/m/Y') }}:</strong>
                no cosechar. Tratamiento con {{ $tratamiento->producto }} por {{ $tratamiento->motivo }}.
            </span>
        </div>
    @endforeach

    @if ($alertasAgua !== [])
        <div role="alert" class="mb-3 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-base text-red-800">
            <p class="mb-1 flex items-center gap-2 font-semibold">
                <x-icono nombre="alerta" class="h-5 w-5" /> Alertas de la última medición ({{ $medicion->medido_at->format('d/m H:i') }})
            </p>
            <ul class="ml-7 list-disc space-y-0.5">
                @foreach ($alertasAgua as $alerta)
                    <li>{{ $alerta }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="mt-6 grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <section>
                <h2 class="mb-3 text-lg font-semibold text-slate-900">Siembras activas</h2>

                @forelse ($lago->siembrasActivas as $siembra)
                    <x-tarjeta class="mb-4 !p-0 overflow-hidden">
                        <div class="flex items-center gap-4 p-5">
                            @if ($siembra->foto_especie)
                                <img src="{{ $siembra->foto_especie }}" alt="" loading="lazy" decoding="async" class="h-12 w-12 rounded-xl object-cover ring-1 ring-slate-200">
                            @else
                                <span class="flex h-12 w-12 items-center justify-center rounded-xl bg-marca-50 text-marca-600"><x-icono nombre="pez" /></span>
                            @endif
                            <div class="min-w-0 flex-1">
                                <h3 class="font-semibold text-slate-900">{{ $siembra->nombre_especie }}</h3>
                                <p class="text-sm text-slate-500">Desde {{ $siembra->fecha->format('d/m/Y') }} · {{ $siembra->dias_cultivo }} días</p>
                            </div>
                        </div>

                        @if ($esTecnico)
                            <div class="grid grid-cols-2 gap-2 px-5 pb-4">
                                <x-boton tipo="button" class="!px-2" data-abrir-modal="modal-cosecha-{{ $siembra->id }}" :disabled="$lago->tratamientosEnRetiro->isNotEmpty()"
                                         :title="$lago->tratamientosEnRetiro->isNotEmpty() ? 'Lago en retiro sanitario' : null">
                                    <x-icono nombre="pez" class="h-5 w-5" /> Cosechar
                                </x-boton>
                                <x-boton tipo="button" variante="secundario" class="!px-2" data-abrir-modal="modal-traslado-{{ $siembra->id }}" :disabled="$otrosLagos === []">
                                    <x-icono nombre="lagos" class="h-5 w-5" /> Trasladar
                                </x-boton>
                            </div>
                        @endif

                        <dl class="grid grid-cols-3 gap-px border-t border-slate-100 bg-slate-100 text-center">
                            <div class="bg-white p-3">
                                <dt class="text-xs text-slate-500">Peces vivos</dt>
                                <dd class="font-bold tabular-nums text-slate-900">{{ $numero($siembra->peces_vivos) }}</dd>
                            </div>
                            <div class="bg-white p-3">
                                <dt class="text-xs text-slate-500">Peso promedio</dt>
                                <dd class="font-bold tabular-nums text-slate-900">{{ $numero($siembra->peso_actual_g, 2) }} g</dd>
                            </div>
                            <div class="bg-white p-3">
                                <dt class="text-xs text-slate-500">Biomasa</dt>
                                <dd class="font-bold tabular-nums text-slate-900">{{ $numero($siembra->biomasa_kg, 2) }} kg</dd>
                            </div>
                        </dl>
                    </x-tarjeta>

                    @if ($esTecnico && $otrosLagos !== [])
                        <x-modal id="modal-traslado-{{ $siembra->id }}" titulo="Trasladar {{ $siembra->nombre_especie }}">
                            <x-formulario :action="route('admin.traslados.store', $siembra)">
                                <p class="rounded-xl bg-marca-50 p-4 text-base text-marca-900">
                                    Si trasladas una parte es un desdoble. Si el lago de destino ya tiene {{ $siembra->nombre_especie }}, los peces se suman a esa siembra.
                                </p>
                                <x-seleccion nombre="lago_destino_id" id="traslado-destino-{{ $siembra->id }}" etiqueta="Lago de destino" :opciones="$otrosLagos" required />
                                <x-campo nombre="fecha" id="traslado-fecha-{{ $siembra->id }}" etiqueta="Fecha" tipo="date" :valor="now()->toDateString()" required />
                                <div class="grid grid-cols-2 gap-4">
                                    <x-campo nombre="cantidad" id="traslado-cantidad-{{ $siembra->id }}" etiqueta="Peces" tipo="number" min="1" :max="$siembra->peces_vivos" inputmode="numeric" required
                                             ayuda="Máximo {{ $numero($siembra->peces_vivos) }}." />
                                    <x-campo nombre="peso_promedio_g" id="traslado-peso-{{ $siembra->id }}" etiqueta="Peso promedio (g)" tipo="number" step="0.1" min="0.1" inputmode="decimal"
                                             :valor="$siembra->peso_actual_g" required />
                                </div>
                                <x-campo nombre="observaciones" id="traslado-obs-{{ $siembra->id }}" etiqueta="Observaciones (opcional)" />

                                <div class="grid grid-cols-2 gap-3 pt-2">
                                    <x-boton tipo="button" variante="secundario" data-cerrar-modal>Cancelar</x-boton>
                                    <x-boton>Trasladar</x-boton>
                                </div>
                            </x-formulario>
                        </x-modal>
                    @endif

                    @if ($esTecnico && $lago->tratamientosEnRetiro->isEmpty())
                        <x-modal id="modal-cosecha-{{ $siembra->id }}" titulo="Cosechar {{ $siembra->nombre_especie }}">
                            <x-formulario :action="route('admin.cosechas.store', $siembra)">
                                <p class="rounded-xl bg-marca-50 p-4 text-base text-marca-900">
                                    Si cosechas todos los peces, la siembra se cierra y el lago queda libre.
                                </p>
                                <x-campo nombre="fecha" id="cosecha-fecha-{{ $siembra->id }}" etiqueta="Fecha" tipo="date" :valor="now()->toDateString()" required />
                                <x-campo nombre="peces" id="cosecha-peces-{{ $siembra->id }}" etiqueta="Peces" tipo="number" min="1" :max="$siembra->peces_vivos" inputmode="numeric" required
                                         ayuda="Hay {{ $numero($siembra->peces_vivos) }}." />

                                @php
                                    // Si el formulario volvió con errores, se reabre en el modo y con las canastas que se escribieron
                                    $porCanastas = old('_modal') === 'modal-cosecha-'.$siembra->id && is_array(old('pesajes'));
                                    $brutos = $porCanastas ? array_values(old('pesajes')) : [''];
                                @endphp
                                <div data-pesaje class="space-y-4">
                                    <fieldset>
                                        <legend class="mb-1.5 text-base font-medium text-slate-700">Peso</legend>
                                        <div class="grid grid-cols-2 gap-1 rounded-xl bg-slate-100 p-1">
                                            @foreach (['total' => 'Total neto', 'canastas' => 'Por canastas'] as $modo => $texto)
                                                <label class="flex min-h-11 cursor-pointer items-center justify-center rounded-lg text-base font-semibold text-slate-600 has-checked:bg-white has-checked:text-marca-800 has-checked:shadow-sm">
                                                    <input type="radio" name="modo_pesaje_{{ $siembra->id }}" value="{{ $modo }}" class="sr-only" data-modo-pesaje
                                                           @checked(($modo === 'canastas') === $porCanastas)>
                                                    {{ $texto }}
                                                </label>
                                            @endforeach
                                        </div>
                                    </fieldset>

                                    <div data-seccion="total" @if ($porCanastas) hidden @endif>
                                        <x-campo nombre="kg" id="cosecha-kg-{{ $siembra->id }}" etiqueta="Kg netos" tipo="number" step="0.01" min="0.1" inputmode="decimal"
                                                 required :disabled="$porCanastas" ayuda="Peso total sin la tara de las canastas." />
                                    </div>

                                    <div data-seccion="canastas" class="space-y-3" @unless ($porCanastas) hidden @endunless>
                                        <x-campo nombre="tara_kg" id="cosecha-tara-{{ $siembra->id }}" etiqueta="Tara por canasta (kg)" tipo="number" step="0.01" min="0" inputmode="decimal"
                                                 required :disabled="! $porCanastas" data-tara ayuda="Peso de la canasta vacía." />

                                        <ol data-canastas class="space-y-2">
                                            @foreach ($brutos as $indice => $bruto)
                                                <li data-canasta class="flex items-end gap-2">
                                                    <div class="min-w-0 flex-1">
                                                        <label class="mb-1 block text-sm font-medium text-slate-600" data-etiqueta-canasta>Canasta {{ $indice + 1 }} · bruto (kg)</label>
                                                        <input type="number" name="pesajes[]" value="{{ $bruto }}" step="0.01" min="0.1" inputmode="decimal" required
                                                               @disabled(! $porCanastas) data-bruto
                                                               @class([
                                                                   'block min-h-12 w-full rounded-xl border bg-white px-4 py-3 text-base text-slate-900 shadow-sm focus:outline-none focus:ring-2',
                                                                   'border-slate-300 focus:border-marca-500 focus:ring-marca-200' => ! $errors->has("pesajes.{$indice}"),
                                                                   'border-red-400 focus:ring-red-200' => $errors->has("pesajes.{$indice}"),
                                                               ])>
                                                    </div>
                                                    <button type="button" data-quitar-canasta class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl text-slate-400 hover:bg-red-50 hover:text-red-600" aria-label="Quitar canasta">
                                                        <x-icono nombre="basura" class="h-5 w-5" />
                                                    </button>
                                                </li>
                                            @endforeach
                                        </ol>

                                        <div class="flex items-center justify-between gap-3">
                                            <x-boton tipo="button" variante="secundario" class="!px-3" data-agregar-canasta>
                                                <x-icono nombre="mas" class="h-5 w-5" /> Canasta
                                            </x-boton>
                                            <p class="text-right text-base text-slate-700" aria-live="polite">Neto: <strong data-total-neto class="tabular-nums">0,00 kg</strong></p>
                                        </div>
                                    </div>
                                </div>
                                <x-campo nombre="observaciones" id="cosecha-obs-{{ $siembra->id }}" etiqueta="Observaciones (opcional)" />

                                <div class="grid grid-cols-2 gap-3 pt-2">
                                    <x-boton tipo="button" variante="secundario" data-cerrar-modal>Cancelar</x-boton>
                                    <x-boton>Registrar cosecha</x-boton>
                                </div>
                            </x-formulario>
                        </x-modal>
                    @endif
                @empty
                    <x-vacio icono="lagos" mensaje="Este lago no tiene siembras activas." />
                @endforelse
            </section>

            <section>
                <h2 class="mb-3 text-lg font-semibold text-slate-900">Historial</h2>

                @if ($historial->isEmpty())
                    <x-vacio icono="reloj" mensaje="Todavía no hay registros en este lago." />
                @else
                    <ol class="rounded-2xl bg-white shadow-sm ring-1 ring-slate-200">
                        @foreach ($historial as $evento)
                            <li class="flex gap-4 border-t border-slate-100 p-4 first:border-t-0">
                                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl {{ $tonos[$evento['tono']] }}">
                                    <x-icono :nombre="$evento['icono']" class="h-5 w-5" />
                                </span>
                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-baseline justify-between gap-x-3">
                                        <p class="font-semibold text-slate-900">{{ $evento['titulo'] }}</p>
                                        <time class="text-sm text-slate-500" datetime="{{ $evento['fecha']->toDateString() }}">{{ $evento['fecha']->format('d/m/Y') }}</time>
                                    </div>
                                    @if ($evento['detalle'] !== '' || $evento['responsable'])
                                        <p class="text-sm text-slate-600">{{ collect([$evento['detalle'], $evento['responsable']])->filter()->join(' · ') }}</p>
                                    @endif
                                </div>
                            </li>
                        @endforeach
                    </ol>
                @endif
            </section>
        </div>

        <aside class="space-y-6">
            @if ($racion['filas'] !== [])
                <x-tarjeta titulo="Ración sugerida" subtitulo="Según biomasa y peso promedio. Valores de referencia.">
                    <p class="text-3xl font-bold tabular-nums text-marca-700">
                        {{ $numero($racion['total_kg_dia'], 1) }} <span class="text-base font-medium text-slate-500">kg/día</span>
                    </p>
                    <p class="mt-1 text-base text-slate-600">
                        {{ $racion['tomas'] }} tomas de {{ $numero($racion['kg_por_toma'], 1) }} kg
                    </p>

                    @if ($racion['factor'] < 1)
                        <p class="mt-3 rounded-xl bg-amber-50 p-3 text-sm text-amber-900">
                            Reducida a la mitad por alertas en la medición de agua de las últimas {{ \App\Support\CalculadoraRacion::HORAS_MEDICION_VIGENTE }} horas.
                        </p>
                    @endif

                    <dl class="mt-4 divide-y divide-slate-100 text-sm">
                        @foreach ($racion['filas'] as $fila)
                            <div class="py-2.5">
                                <dt class="flex justify-between gap-3 font-semibold text-slate-900">
                                    <span>{{ $fila['especie'] }}</span>
                                    <span class="tabular-nums">{{ $numero($fila['kg_dia'], 1) }} kg/día</span>
                                </dt>
                                <dd class="text-slate-500">
                                    {{ str_replace('.', ',', (string) $fila['porcentaje']) }} % de {{ $numero($fila['biomasa_kg'], 1) }} kg
                                    @if ($fila['proteina'])
                                        · {{ ['iniciacion' => 'iniciación', 'levante' => 'levante', 'engorde' => 'engorde'][$fila['etapa']] }}, proteína {{ $fila['proteina'] }}
                                    @endif
                                </dd>
                            </div>
                        @endforeach
                    </dl>
                </x-tarjeta>
            @endif

            <x-tarjeta titulo="Calidad del agua" :subtitulo="$medicion ? 'Última medición: '.$medicion->medido_at->format('d/m/Y H:i') : null">
                @if ($medicion)
                    <dl class="divide-y divide-slate-100 text-base">
                        @foreach ([
                            'Temperatura' => $medicion->temperatura_c !== null ? $numero($medicion->temperatura_c, 1).' °C' : null,
                            'Oxígeno disuelto' => $medicion->oxigeno_mg_l !== null ? $numero($medicion->oxigeno_mg_l, 2).' mg/L' : null,
                            'pH' => $medicion->ph !== null ? $numero($medicion->ph, 2) : null,
                            'Amonio' => $medicion->amonio_mg_l !== null ? $numero($medicion->amonio_mg_l, 2).' mg/L' : null,
                            'Nitritos' => $medicion->nitritos_mg_l !== null ? $numero($medicion->nitritos_mg_l, 2).' mg/L' : null,
                            'Transparencia' => $medicion->transparencia_cm !== null ? $medicion->transparencia_cm.' cm' : null,
                        ] as $etiqueta => $valor)
                            @continue($valor === null)
                            <div class="flex justify-between gap-3 py-2">
                                <dt class="text-slate-500">{{ $etiqueta }}</dt>
                                <dd class="font-semibold text-slate-900">{{ $valor }}</dd>
                            </div>
                        @endforeach
                    </dl>
                @else
                    <p class="text-base text-slate-500">Aún no hay mediciones.</p>
                @endif
            </x-tarjeta>

            @if ($siembrasFinalizadas->isNotEmpty())
                <x-tarjeta titulo="Siembras finalizadas">
                    <ul class="divide-y divide-slate-100">
                        @foreach ($siembrasFinalizadas as $siembra)
                            <li class="py-2.5">
                                <p class="font-medium text-slate-900">{{ $siembra->nombre_especie }}</p>
                                <p class="text-sm text-slate-500">
                                    {{ $siembra->fecha->format('d/m/Y') }} – {{ $siembra->finalizada_at->format('d/m/Y') }}
                                    · {{ $numero($siembra->cantidad_alevinos) }} sembrados
                                </p>
                            </li>
                        @endforeach
                    </ul>
                </x-tarjeta>
            @endif
        </aside>
    </div>

    @if ($esTecnico)
        <x-modal id="modal-medicion" titulo="Calidad del agua · {{ $lago->nombre }}">
            <x-formulario :action="route('admin.mediciones-agua.store', $lago)">
                <x-campo nombre="medido_at" etiqueta="Fecha y hora" tipo="datetime-local" :valor="now()->format('Y-m-d\TH:i')" required />
                <div class="grid grid-cols-2 gap-4">
                    <x-campo nombre="temperatura_c" etiqueta="Temperatura (°C)" tipo="number" step="0.1" inputmode="decimal" />
                    <x-campo nombre="oxigeno_mg_l" etiqueta="Oxígeno (mg/L)" tipo="number" step="0.01" inputmode="decimal" />
                    <x-campo nombre="ph" etiqueta="pH" tipo="number" step="0.01" inputmode="decimal" />
                    <x-campo nombre="transparencia_cm" etiqueta="Transparencia (cm)" tipo="number" inputmode="numeric" />
                    <x-campo nombre="amonio_mg_l" etiqueta="Amonio (mg/L)" tipo="number" step="0.001" inputmode="decimal" />
                    <x-campo nombre="nitritos_mg_l" etiqueta="Nitritos (mg/L)" tipo="number" step="0.001" inputmode="decimal" />
                </div>
                <p class="text-sm text-slate-500">Llena solo lo que mediste.</p>
                <x-campo nombre="observaciones" id="medicion-obs" etiqueta="Observaciones (opcional)" />

                <div class="grid grid-cols-2 gap-3 pt-2">
                    <x-boton tipo="button" variante="secundario" data-cerrar-modal>Cancelar</x-boton>
                    <x-boton>Guardar</x-boton>
                </div>
            </x-formulario>
        </x-modal>

        <x-modal id="modal-tratamiento" titulo="Tratamiento sanitario · {{ $lago->nombre }}">
            <x-formulario :action="route('admin.tratamientos.store', $lago)">
                <x-campo nombre="motivo" etiqueta="Motivo o diagnóstico" required />
                <div class="grid grid-cols-2 gap-4">
                    <x-campo nombre="producto" etiqueta="Producto" required />
                    <x-campo nombre="registro_ica" etiqueta="Registro ICA (opcional)" />
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <x-campo nombre="dosis" etiqueta="Dosis" placeholder="Ej. 2 g/kg de alimento" required />
                    <x-seleccion nombre="via" etiqueta="Vía" :opciones="$vias" required />
                    <x-campo nombre="fecha_inicio" etiqueta="Inicio" tipo="date" :valor="now()->toDateString()" required />
                    <x-campo nombre="fecha_fin" etiqueta="Fin" tipo="date" :valor="now()->toDateString()" required />
                </div>
                <x-campo nombre="dias_retiro" etiqueta="Días de retiro" tipo="number" min="0" max="365" inputmode="numeric" valor="0" required
                         ayuda="Días después del fin en que el pescado no se puede cosechar (según la etiqueta del producto)." />
                <x-campo nombre="observaciones" id="tratamiento-obs" etiqueta="Observaciones (opcional)" />

                <div class="grid grid-cols-2 gap-3 pt-2">
                    <x-boton tipo="button" variante="secundario" data-cerrar-modal>Cancelar</x-boton>
                    <x-boton>Guardar</x-boton>
                </div>
            </x-formulario>
        </x-modal>
    @endif
@endsection
