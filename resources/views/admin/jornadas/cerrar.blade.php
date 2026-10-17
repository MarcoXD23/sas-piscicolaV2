@extends('layouts.app')

@php
    use App\Models\JornadaLago;
    use App\Support\CalculoPesaje;
    use App\Support\Formato;

    $titulo = __('jornadas.titulo_cerrar', ['fecha' => $jornada->fecha->format('d/m/Y')]);
    $clasesEntrada = 'block min-h-12 w-full rounded-ficha border border-renglon-fuerte bg-ficha px-3 text-base tabular-nums text-tinta focus:border-marca-700 focus:outline-none focus:ring-4 focus:ring-marca-200/70';
@endphp

@section('titulo', $titulo)

@section('contenido')
    <x-encabezado :titulo="$titulo" :subtitulo="__('jornadas.subtitulo_cerrar')">
        <x-boton variante="secundario" :href="route('admin.jornadas.show', $jornada)">{{ __('jornadas.volver_jornada') }}</x-boton>
    </x-encabezado>

    @if ($errors->any())
        <x-aviso tono="alerta" class="mb-5">
            <ul class="space-y-1">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </x-aviso>
    @endif

    <x-formulario :action="route('admin.jornadas.cerrar.guardar', $jornada)" :confirmar="__('jornadas.confirmar_cerrar')" class="max-w-4xl">
        @foreach ($jornada->lagos as $jornadaLago)
            @php
                $propuesta = $propuestas[$jornadaLago->id];
                $base = 'lagos['.$jornadaLago->id.']';
                $nombresEspecie = $jornadaLago->pesajes->pluck('especie.nombre', 'especie_id');
            @endphp
            <x-tarjeta :titulo="__('lagos.lago_nombre', ['lago' => $jornadaLago->lago->nombre])">
                @if ($propuesta['totales'] !== [])
                    <p class="text-base">
                        {{ collect($propuesta['totales'])->map(fn ($kg, $especieId) => __('jornadas.total_especie', ['especie' => $nombresEspecie[$especieId] ?? '', 'kg' => CalculoPesaje::kg($kg)]))->implode(' · ') }}
                    </p>
                @else
                    <p class="text-base text-tinta-suave">{{ __('jornadas.sin_pesajes_lago') }}</p>
                @endif

                @if ($propuesta['sin_siembra'] !== [])
                    <x-aviso tono="alerta" class="mt-3">{{ __('jornadas.errores.especie_sin_siembra', ['lago' => $jornadaLago->lago->nombre]) }}</x-aviso>
                @endif

                <fieldset class="mt-4">
                    <legend class="mb-1.5 text-base font-semibold text-tinta">{{ __('jornadas.como_quedo') }}</legend>
                    <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                        @foreach (JornadaLago::RESULTADOS as $resultado)
                            <label class="flex min-h-12 cursor-pointer items-start gap-3 rounded-ficha bg-papel px-4 py-3 ring-1 ring-renglon has-checked:ring-2 has-checked:ring-marca-700">
                                <input type="radio" name="{{ $base }}[resultado]" value="{{ $resultado }}" class="mt-1 h-5 w-5 shrink-0 accent-marca-700" required
                                       @checked(old('lagos.'.$jornadaLago->id.'.resultado') === $resultado)>
                                <span>
                                    <span class="block font-bold">{{ __('jornadas.resultados.'.$resultado) }}</span>
                                    <span class="block text-sm text-tinta-suave">{{ __('jornadas.resultados_ayuda.'.$resultado) }}</span>
                                </span>
                            </label>
                        @endforeach
                    </div>
                </fieldset>

                @if ($propuesta['filas'] !== [])
                    <div class="mt-5 space-y-3">
                        <p class="text-sm text-tinta-suave">{{ __('jornadas.reparto_ayuda') }}</p>
                        @foreach ($propuesta['filas'] as $fila)
                            @php $siembra = $fila['siembra']; @endphp
                            <div class="grid grid-cols-1 gap-3 rounded-ficha bg-papel p-3 ring-1 ring-renglon sm:grid-cols-[1fr_10rem_10rem] sm:items-end">
                                <div>
                                    <p class="font-bold">{{ $siembra->nombre_especie ?: __('cosechas.sin_dato') }}</p>
                                    <p class="text-sm text-tinta-suave">
                                        {{ __('jornadas.siembra_linea', ['fecha' => $siembra->fecha->format('d/m/Y'), 'peces' => Formato::numero($siembra->peces_vivos), 'gramos' => Formato::numero($siembra->peso_actual_g)]) }}
                                    </p>
                                    @if ($fila['kg'] > 0)
                                        <p class="text-sm">{{ __('jornadas.peces_segun_reparto', ['peces' => Formato::numero((int) $fila['peces'])]) }}</p>
                                    @endif
                                </div>
                                <label class="block">
                                    <span class="mb-1 block text-sm font-semibold text-tinta-suave">{{ __('jornadas.kg_cosechados_siembra') }}</span>
                                    <input type="number" step="0.01" min="0" inputmode="decimal" name="{{ $base }}[kg][{{ $siembra->id }}]" class="{{ $clasesEntrada }}"
                                           value="{{ old('lagos.'.$jornadaLago->id.'.kg.'.$siembra->id, $fila['kg']) }}">
                                </label>
                                <label class="block">
                                    <span class="mb-1 block text-sm font-semibold text-tinta-suave">{{ __('jornadas.kg_quedan') }}</span>
                                    <input type="number" step="0.01" min="0" inputmode="decimal" name="{{ $base }}[restante_kg][{{ $siembra->id }}]" class="{{ $clasesEntrada }}"
                                           value="{{ old('lagos.'.$jornadaLago->id.'.restante_kg.'.$siembra->id) }}" placeholder="{{ __('jornadas.opcional') }}">
                                </label>
                            </div>
                        @endforeach
                        <p class="text-sm text-tinta-suave">{{ __('jornadas.kg_quedan_ayuda') }}</p>
                    </div>
                @endif
            </x-tarjeta>
        @endforeach

        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
            <x-boton variante="secundario" :href="route('admin.jornadas.show', $jornada)">{{ __('comun.cancelar') }}</x-boton>
            <x-boton>{{ __('jornadas.cerrar_jornada') }}</x-boton>
        </div>
    </x-formulario>
@endsection
