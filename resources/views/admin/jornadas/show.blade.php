@extends('layouts.app')

@php
    use App\Models\Jornada;
    use App\Support\Ajustes\AjustesFinca;
    use App\Support\Ajustes\CatalogoAjustes;
    use App\Support\CalculoPesaje;
    use App\Support\Formato;

    $tonos = [Jornada::ABIERTA => 'info', Jornada::CERRADA => 'bien', Jornada::CANCELADA => 'neutro'];
    $titulo = __('jornadas.titulo_detalle', ['fecha' => $jornada->fecha->format('d/m/Y')]);
    $diasAyuno = (int) AjustesFinca::valor(CatalogoAjustes::PROCESO_DIAS_AYUNO);
@endphp

@section('titulo', $titulo)

@section('contenido')
    <x-encabezado :titulo="$titulo" :subtitulo="__('jornadas.programada_por', ['nombre' => $jornada->usuario?->name ?? __('cosechas.sin_dato')])">
        <x-boton variante="secundario" :href="route('admin.jornadas.index')">{{ __('jornadas.volver') }}</x-boton>
    </x-encabezado>

    @error('jornada')
        <x-aviso tono="alerta" class="mb-5">{{ $message }}</x-aviso>
    @enderror

    <x-tarjeta>
        <div class="flex flex-wrap items-center gap-3">
            <x-insignia :tono="$tonos[$jornada->estado]">{{ __('jornadas.estados.'.$jornada->estado) }}</x-insignia>
            @if ($jornada->sin_alimentacion)
                <x-insignia tono="atencion">{{ __('jornadas.dia_sin_alimentacion') }}</x-insignia>
            @endif
        </div>
        @if ($jornada->estaAbierta())
            <p class="mt-3 text-base">
                {{ __('jornadas.ayuno_desde', ['fecha' => $jornada->fecha->subDays($diasAyuno)->format('d/m/Y')]) }}
            </p>
            @unless ($jornada->yaEsSuDia())
                <x-aviso tono="info" class="mt-3">{{ __('jornadas.aun_no_es_su_dia') }}</x-aviso>
            @endunless
        @elseif ($jornada->estado === Jornada::CERRADA)
            <p class="mt-3 text-base">{{ __('jornadas.cerrada_por', ['nombre' => $jornada->cerradaPor?->name ?? __('cosechas.sin_dato'), 'momento' => $jornada->cerrada_at?->formatoHora('d/m/Y H:i')]) }}</p>
        @else
            <p class="mt-3 text-base">{{ __('jornadas.cancelada_por', ['nombre' => $jornada->canceladaPor?->name ?? __('cosechas.sin_dato'), 'momento' => $jornada->cancelada_at?->formatoHora('d/m/Y H:i')]) }}</p>
        @endif
        @if ($jornada->observaciones)
            <p class="mt-3 text-base text-tinta-suave">{{ $jornada->observaciones }}</p>
        @endif

        @if ($puedeOperar)
            <div class="mt-5 grid grid-cols-1 gap-3 sm:grid-cols-2">
                @if ($puedePesar)
                    <x-boton :href="route('admin.jornadas.cerrar', $jornada)">{{ __('jornadas.ir_a_cerrar') }}</x-boton>
                @endif
                <x-formulario :action="route('admin.jornadas.cancelar', $jornada)" :confirmar="__('jornadas.confirmar_cancelar')">
                    <x-boton variante="secundario" class="w-full">{{ __('jornadas.cancelar_jornada') }}</x-boton>
                </x-formulario>
            </div>
        @endif
    </x-tarjeta>

    {{-- Un bloque por lago: el entero por especie y sus pesadas --}}
    @foreach ($jornada->lagos as $jornadaLago)
        @php
            $porEspecie = $jornadaLago->pesajes->groupBy('especie_id');
            $especies = $especiesPorLago[$jornadaLago->id];
            $sinCatalogo = $jornadaLago->lago->siembrasActivas->whereNull('especie_id')->isNotEmpty();
        @endphp
        <x-tarjeta class="mt-6" :titulo="__('lagos.lago_nombre', ['lago' => $jornadaLago->lago->nombre])"
                   :subtitulo="$jornadaLago->resultado ? __('jornadas.resultados.'.$jornadaLago->resultado) : null">
            @if ($porEspecie->isEmpty())
                <p class="text-base text-tinta-suave">{{ __('jornadas.sin_pesajes_lago') }}</p>
            @endif

            @foreach ($porEspecie as $pesajes)
                <section class="mb-4">
                    <h3 class="text-lg font-bold">
                        {{ $pesajes->first()->especie?->nombre }} ·
                        <span class="tabular-nums">{{ __('jornadas.kg_enteros', ['kg' => CalculoPesaje::kg($pesajes->sum('entero_kg'))]) }}</span>
                    </h3>
                    <ul class="mt-2 divide-y divide-renglon">
                        @foreach ($pesajes as $pesaje)
                            <li class="flex items-center justify-between gap-3 py-2">
                                <span class="text-base tabular-nums">
                                    @if ($pesaje->tipo === 'total')
                                        {{ __('jornadas.pesaje_total', ['kg' => CalculoPesaje::kg($pesaje->entero_kg)]) }}
                                    @else
                                        {{ __('jornadas.pesaje_pesada', ['cantidad' => $pesaje->canastas, 'tipo' => trans_choice('jornadas.tipos.'.$pesaje->tipo, $pesaje->canastas),
                                            'pesado' => CalculoPesaje::kg($pesaje->bruto_kg), 'descuento' => CalculoPesaje::kg($pesaje->tara_kg), 'entero' => CalculoPesaje::kg($pesaje->entero_kg)]) }}
                                    @endif
                                </span>
                                @if ($puedeOperar)
                                    <x-formulario :action="route('admin.jornadas.pesajes.destroy', [$jornada, $pesaje])" metodo="DELETE" :confirmar="__('jornadas.confirmar_quitar_pesaje')" class="space-y-0">
                                        <button type="submit" class="flex h-11 w-11 items-center justify-center rounded-ficha text-tinta-suave hover:bg-alerta-fondo hover:text-alerta"
                                                aria-label="{{ __('jornadas.quitar_pesaje', ['kg' => CalculoPesaje::kg($pesaje->entero_kg)]) }}">
                                            <x-icono nombre="basura" class="h-5 w-5" />
                                        </button>
                                    </x-formulario>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endforeach

            {{-- Después del cierre: lo que pasó con cada siembra --}}
            @if (! empty($jornadaLago->cierre['siembras']))
                <x-tabla :columnas="[__('jornadas.especie'), __('jornadas.kg'), __('jornadas.peces_cosechados'), __('jornadas.peces_quedan'), __('jornadas.diferencia')]">
                    @foreach ($jornadaLago->cierre['siembras'] as $fila)
                        <tr>
                            <td data-label="{{ __('jornadas.especie') }}" class="font-bold">{{ $fila['especie'] }}</td>
                            <td data-label="{{ __('jornadas.kg') }}" class="tabular-nums">{{ CalculoPesaje::kg((float) $fila['kg']) }}</td>
                            <td data-label="{{ __('jornadas.peces_cosechados') }}" class="tabular-nums">{{ Formato::numero($fila['peces_cosechados']) }}</td>
                            <td data-label="{{ __('jornadas.peces_quedan') }}" class="tabular-nums">{{ Formato::numero($fila['peces_quedan']) }}</td>
                            <td data-label="{{ __('jornadas.diferencia') }}" class="tabular-nums">{{ Formato::numero($fila['diferencia']) }}</td>
                        </tr>
                    @endforeach
                </x-tabla>
                <p class="mt-2 text-sm text-tinta-suave">{{ __('jornadas.diferencia_ayuda') }}</p>
            @endif

            {{-- «Reproducción»: solo con el ajuste encendido; sus kg vuelven al lago --}}
            @if ($conReproduccion && ($jornadaLago->reproducciones->isNotEmpty() || ($puedePesar && $especies->isNotEmpty())))
                <section class="mt-5 rounded-ficha bg-papel-hondo p-4">
                    <h3 class="text-base font-bold">{{ __('jornadas.reproduccion') }}</h3>
                    <p class="text-sm text-tinta-suave">{{ __('jornadas.reproduccion_ayuda') }}</p>
                    @if ($jornadaLago->reproducciones->isNotEmpty())
                        <ul class="mt-2 divide-y divide-renglon">
                            @foreach ($jornadaLago->reproducciones as $reproduccion)
                                <li class="flex items-center justify-between gap-3 py-2">
                                    <span class="text-base tabular-nums">{{ __('jornadas.reproduccion_linea', ['especie' => $reproduccion->especie?->nombre, 'kg' => CalculoPesaje::kg($reproduccion->kg)]) }}</span>
                                    @if ($puedeOperar)
                                        <x-formulario :action="route('admin.jornadas.reproduccion.destroy', [$jornada, $reproduccion])" metodo="DELETE" :confirmar="__('jornadas.confirmar_quitar_pesaje')" class="space-y-0">
                                            <button type="submit" class="flex h-11 w-11 items-center justify-center rounded-ficha text-tinta-suave hover:bg-alerta-fondo hover:text-alerta"
                                                    aria-label="{{ __('jornadas.quitar_pesaje', ['kg' => CalculoPesaje::kg($reproduccion->kg)]) }}">
                                                <x-icono nombre="basura" class="h-5 w-5" />
                                            </button>
                                        </x-formulario>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    @endif
                    @if ($puedePesar && $especies->isNotEmpty())
                        <x-formulario :action="route('admin.jornadas.reproduccion.store', $jornada)" class="mt-3 grid grid-cols-1 gap-3 space-y-0 sm:grid-cols-[1fr_10rem_auto] sm:items-end">
                            <input type="hidden" name="jornada_lago_id" value="{{ $jornadaLago->id }}">
                            <x-seleccion nombre="especie_id" :id="'reproduccion-especie-'.$jornadaLago->id" :etiqueta="__('jornadas.especie')" :opciones="$especies->all()" required />
                            <x-campo nombre="kg" :id="'reproduccion-kg-'.$jornadaLago->id" :etiqueta="__('jornadas.kg')" tipo="number" step="0.01" min="0.1" inputmode="decimal" required />
                            <x-boton variante="secundario">{{ __('jornadas.guardar_reproduccion') }}</x-boton>
                        </x-formulario>
                    @endif
                </section>
            @endif

            @if ($puedePesar)
                @if ($sinCatalogo)
                    <x-aviso tono="atencion" class="mt-4">{{ __('jornadas.siembra_sin_catalogo') }}</x-aviso>
                @endif
                @if ($especies->isNotEmpty())
                    @php
                        $formulario = [
                            'sufijo' => 'lago-'.$jornadaLago->id,
                            'accion' => route('admin.jornadas.pesajes.store', $jornada),
                            'ocultos' => ['jornada_lago_id' => $jornadaLago->id],
                            'selector' => ['nombre' => 'especie_id', 'etiqueta' => __('jornadas.especie'), 'opciones' => $especies],
                            'titulo' => __('jornadas.registrar_pesaje'),
                        ];
                    @endphp
                    @include('admin.jornadas._pesaje')
                @endif
            @endif
        </x-tarjeta>
    @endforeach

    {{-- Limpio de la jornada por presentación: el total es la suma de las pesadas --}}
    @if ($jornada->pesajesLimpio->isNotEmpty() || $puedePesar)
        @php $porPresentacion = $jornada->pesajesLimpio->groupBy('presentacion_id'); @endphp
        <x-tarjeta class="mt-6" :titulo="__('jornadas.limpio')"
                   :subtitulo="__('jornadas.limpio_total', ['kg' => CalculoPesaje::kg($jornada->pesajesLimpio->sum('limpio_kg'))])">
            @if ($porPresentacion->isEmpty())
                <p class="text-base text-tinta-suave">{{ __('jornadas.sin_limpio') }}</p>
            @endif
            @foreach ($porPresentacion as $pesajes)
                <section class="mb-4">
                    <h3 class="text-lg font-bold">
                        {{ $pesajes->first()->presentacion->nombre() }} ·
                        <span class="tabular-nums">{{ __('jornadas.kg_limpios', ['kg' => CalculoPesaje::kg($pesajes->sum('limpio_kg'))]) }}</span>
                    </h3>
                    <ul class="mt-2 divide-y divide-renglon">
                        @foreach ($pesajes as $pesaje)
                            <li class="flex items-center justify-between gap-3 py-2">
                                <span class="text-base tabular-nums">
                                    @if ($pesaje->tipo === 'total')
                                        {{ __('jornadas.pesaje_total', ['kg' => CalculoPesaje::kg($pesaje->limpio_kg)]) }}
                                    @else
                                        {{ __('jornadas.pesaje_pesada', ['cantidad' => $pesaje->canastas, 'tipo' => trans_choice('jornadas.tipos.'.$pesaje->tipo, $pesaje->canastas),
                                            'pesado' => CalculoPesaje::kg($pesaje->bruto_kg), 'descuento' => CalculoPesaje::kg($pesaje->tara_kg), 'entero' => CalculoPesaje::kg($pesaje->limpio_kg)]) }}
                                    @endif
                                </span>
                                @if ($puedeOperar)
                                    <x-formulario :action="route('admin.jornadas.limpio.destroy', [$jornada, $pesaje])" metodo="DELETE" :confirmar="__('jornadas.confirmar_quitar_pesaje')" class="space-y-0">
                                        <button type="submit" class="flex h-11 w-11 items-center justify-center rounded-ficha text-tinta-suave hover:bg-alerta-fondo hover:text-alerta"
                                                aria-label="{{ __('jornadas.quitar_pesaje', ['kg' => CalculoPesaje::kg($pesaje->limpio_kg)]) }}">
                                            <x-icono nombre="basura" class="h-5 w-5" />
                                        </button>
                                    </x-formulario>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endforeach

            @if ($puedePesar)
                @if ($presentaciones->isEmpty())
                    <x-aviso tono="info" class="mt-4">
                        {{ __('jornadas.sin_presentaciones') }}
                        <a href="{{ route('admin.presentaciones.index') }}" class="font-bold underline">{{ __('jornadas.ir_presentaciones') }}</a>
                    </x-aviso>
                @else
                    @php
                        $formulario = [
                            'sufijo' => 'limpio',
                            'accion' => route('admin.jornadas.limpio.store', $jornada),
                            'ocultos' => [],
                            'selector' => ['nombre' => 'presentacion_id', 'etiqueta' => __('jornadas.presentacion'), 'opciones' => $presentaciones],
                            'titulo' => __('jornadas.registrar_limpio'),
                            'limpio' => true,
                        ];
                    @endphp
                    @include('admin.jornadas._pesaje')
                @endif
            @endif
        </x-tarjeta>
    @endif

    {{-- Ventas rápidas y obsequios de la jornada (F6c) --}}
    @if ($ventas->isNotEmpty() || $obsequios->isNotEmpty() || $venta)
        <x-tarjeta class="mt-6" :titulo="__('ventas.rapida.titulo')" :subtitulo="__('ventas.rapida.subtitulo')">
            @if ($ventas->isEmpty())
                <p class="text-base text-tinta-suave">{{ __('ventas.rapida.sin_ventas') }}</p>
            @else
                <ul class="-mt-1 divide-y divide-renglon">
                    @foreach ($ventas as $fila)
                        <li class="py-2.5">
                            <p class="text-base">{{ __('ventas.rapida.linea', ['cliente' => $fila->nombreCliente(), 'especie' => $fila->especie?->nombre, 'kg' => Formato::sinCeros($fila->kg),
                                'total' => Formato::dinero($fila->total), 'forma' => __('ventas.formas_pago.'.$fila->forma_pago)]) }}</p>
                            @if ($fila->kg_cupo !== null)
                                <p class="text-sm text-tinta-suave">{{ __('ventas.cupo_y_exceso', ['cupo' => Formato::sinCeros($fila->kg_cupo), 'precio_cupo' => Formato::dinero((int) $fila->precio_cupo),
                                    'exceso' => Formato::sinCeros($fila->kgExceso()), 'precio' => Formato::dinero($fila->precio_kg)]) }}</p>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif
            @if ($venta)
                @include('admin.jornadas._venta_rapida')
            @endif

            <h3 class="mt-6 text-lg font-bold">{{ __('obsequios.titulo') }}</h3>
            <p class="text-sm text-tinta-suave">{{ __('obsequios.subtitulo') }}</p>
            @if ($obsequios->isEmpty())
                <p class="mt-2 text-base text-tinta-suave">{{ __('obsequios.sin_obsequios') }}</p>
            @else
                <ul class="mt-2 divide-y divide-renglon">
                    @foreach ($obsequios as $obsequio)
                        <li class="py-2.5 text-base">{{ __('obsequios.linea', ['kg' => Formato::sinCeros($obsequio->kg), 'especie' => $obsequio->especie?->nombre,
                            'destinatario' => $obsequio->destinatario, 'autorizo' => $obsequio->autorizadoPor?->name, 'registro' => $obsequio->registradoPor?->name]) }}</li>
                    @endforeach
                </ul>
            @endif
            @if ($venta)
                @include('admin.jornadas._obsequio')
            @endif
        </x-tarjeta>
    @endif

    {{-- Rendimiento por especie: con lo pesado (abierta) o lo guardado al cerrar --}}
    @php
        $filasRendimiento = $jornada->estaAbierta()
            ? collect($rendimientoAhora['especies'] ?? [])->map(fn ($f) => $f + ['nombre' => $nombresEspecie[$f['especie_id']] ?? '', 'motivo' => null])
            : $jornada->rendimientos->map(fn ($r) => ['nombre' => $r->especie?->nombre, 'entero' => $r->entero_kg, 'reproduccion' => $r->reproduccion_kg,
                'limpio' => $r->limpio_kg, 'rendimiento' => $r->rendimiento, 'referencia' => $r->referencia, 'fuente' => $r->fuente_referencia, 'bajo' => $r->bajo, 'motivo' => $r->motivo]);
    @endphp
    @if ($filasRendimiento->isNotEmpty())
        <x-tarjeta class="mt-6" :titulo="__('jornadas.rendimiento')" :subtitulo="__('jornadas.rendimiento_ayuda')">
            <ul class="-mt-1 divide-y divide-renglon">
                @foreach ($filasRendimiento as $fila)
                    <li class="py-3">
                        <p class="font-bold">{{ $fila['nombre'] }}</p>
                        <p class="text-base tabular-nums">
                            @if ($fila['rendimiento'] === null)
                                {{ __('jornadas.rendimiento_sin_dato', ['entero' => CalculoPesaje::kg((float) $fila['entero'] - (float) $fila['reproduccion'])]) }}
                            @else
                                {{ __('jornadas.rendimiento_linea', ['limpio' => CalculoPesaje::kg((float) $fila['limpio']), 'entero' => CalculoPesaje::kg((float) $fila['entero'] - (float) $fila['reproduccion']), 'rendimiento' => Formato::numero($fila['rendimiento'], 1)]) }}
                            @endif
                            @if ($fila['referencia'] !== null)
                                · {{ __($fila['fuente'] === 'promedio' ? 'jornadas.referencia_promedio' : 'jornadas.referencia_catalogo', ['referencia' => Formato::numero($fila['referencia'], 1)]) }}
                            @endif
                        </p>
                        @if ((float) $fila['reproduccion'] > 0)
                            <p class="text-sm text-tinta-suave">{{ __('jornadas.sin_reproduccion', ['kg' => CalculoPesaje::kg((float) $fila['reproduccion'])]) }}</p>
                        @endif
                        @if ($fila['bajo'])
                            <x-aviso tono="atencion" class="mt-2">
                                {{ __('jornadas.rendimiento_bajo') }}
                                @if ($fila['motivo']) {{ __('jornadas.motivo_anotado', ['motivo' => $fila['motivo']]) }}@endif
                            </x-aviso>
                        @endif
                    </li>
                @endforeach
            </ul>
        </x-tarjeta>
    @endif

    @if ($puedeOperar && $lagosParaAgregar->isNotEmpty())
        <x-tarjeta class="mt-6" :titulo="__('jornadas.agregar_lago')" :subtitulo="__('jornadas.agregar_lago_ayuda')">
            <x-formulario :action="route('admin.jornadas.lagos.store', $jornada)">
                <x-seleccion nombre="lago_id" :etiqueta="__('jornadas.lago')" :opciones="$lagosParaAgregar->pluck('nombre', 'id')->all()" required />
                <x-boton variante="secundario">{{ __('jornadas.agregar_lago') }}</x-boton>
            </x-formulario>
        </x-tarjeta>
    @endif

    @if ($jornada->cosechas->isNotEmpty())
        <x-tarjeta class="mt-6" :titulo="__('jornadas.cosechas_creadas')">
            <ul class="-mt-1 divide-y divide-renglon">
                @foreach ($jornada->cosechas as $cosecha)
                    <li class="py-2.5">
                        <a href="{{ route('admin.cosechas.show', $cosecha) }}" class="font-semibold underline-offset-2 hover:underline">
                            {{ __('jornadas.cosecha_linea', ['lago' => $cosecha->lago->nombre, 'especie' => $cosecha->siembra->nombre_especie, 'kg' => CalculoPesaje::kg($cosecha->kg), 'peces' => Formato::numero($cosecha->peces)]) }}
                        </a>
                    </li>
                @endforeach
            </ul>
        </x-tarjeta>
    @endif
@endsection
