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

            @if ($puedePesar)
                @if ($sinCatalogo)
                    <x-aviso tono="atencion" class="mt-4">{{ __('jornadas.siembra_sin_catalogo') }}</x-aviso>
                @endif
                @if ($especies->isNotEmpty())
                    {{-- $jornadaLago y $especies ya están en el ciclo --}}
                    @include('admin.jornadas._pesaje')
                @endif
            @endif
        </x-tarjeta>
    @endforeach

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
