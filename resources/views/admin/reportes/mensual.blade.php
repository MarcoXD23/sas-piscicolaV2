@extends('layouts.app')

@section('titulo', 'Informe mensual')

@section('contenido')
    @php
        $numero = fn ($valor, $decimales = 0) => number_format((float) $valor, $decimales, ',', '.');
        $totalMortalidad = $lagos->sum('mortalidad');
        $totalAlimento = $lagos->sum('alimento_kg');
    @endphp

    <x-encabezado-reporte titulo="Informe mensual" :periodo="ucfirst($mes->translatedFormat('F \d\e Y'))" />

    <section class="mb-6 grid grid-cols-2 gap-3 sm:grid-cols-4 print:grid-cols-4">
        <x-indicador etiqueta="Peces sembrados" :valor="$numero($siembrasNuevas->sum('cantidad_alevinos'))" />
        <x-indicador etiqueta="Mortalidad" :valor="$numero($totalMortalidad)" unidad="peces" />
        <x-indicador etiqueta="Alimento suministrado" :valor="$numero($totalAlimento, 1)" unidad="kg" />
        <x-indicador etiqueta="Tratamientos" :valor="$tratamientos->count()" />
    </section>

    <section class="mb-6 break-inside-avoid">
        <h2 class="mb-2 text-lg font-semibold text-slate-900">Por lago</h2>
        <x-tabla :columnas="['Lago', 'Especies hoy', 'Mortalidad del mes', 'Alimento del mes', 'Muestreos', 'Biomasa hoy']">
            @foreach ($lagos as $fila)
                <tr>
                    <td data-label="Lago" class="font-semibold">{{ $fila['lago']->nombre }}</td>
                    <td data-label="Especies hoy">{{ $fila['lago']->siembrasActivas->pluck('nombre_especie')->join(', ') ?: '—' }}</td>
                    <td data-label="Mortalidad del mes" class="tabular-nums">{{ $numero($fila['mortalidad']) }}</td>
                    <td data-label="Alimento del mes" class="tabular-nums">{{ $numero($fila['alimento_kg'], 1) }} kg</td>
                    <td data-label="Muestreos" class="tabular-nums">{{ $fila['muestreos'] }}</td>
                    <td data-label="Biomasa hoy" class="tabular-nums">{{ $numero($fila['lago']->siembrasActivas->sum('biomasa_kg'), 2) }} kg</td>
                </tr>
            @endforeach
        </x-tabla>
        <p class="mt-2 text-xs text-slate-500">"Especies hoy" y "Biomasa hoy" muestran el estado actual de cada lago, no el del cierre del mes.</p>
    </section>

    <div class="grid gap-6 md:grid-cols-2 print:grid-cols-2">
        <x-tarjeta titulo="Alimento por concentrado" class="break-inside-avoid">
            @forelse ($alimentoPorConcentrado as $concentrado => $kg)
                <div class="flex justify-between gap-3 border-t border-slate-100 py-2 first:border-t-0">
                    <span>{{ $concentrado }}</span><span class="font-semibold tabular-nums">{{ $numero($kg, 1) }} kg</span>
                </div>
            @empty
                <p class="text-slate-500">Sin alimentación registrada.</p>
            @endforelse
        </x-tarjeta>

        <x-tarjeta titulo="Siembras del mes" class="break-inside-avoid">
            @forelse ($siembrasNuevas as $siembra)
                <div class="border-t border-slate-100 py-2 first:border-t-0">
                    <p class="font-medium">{{ $siembra->fecha->format('d/m') }} · Lago {{ $siembra->lago->nombre }} · {{ $siembra->nombre_especie }}</p>
                    <p class="text-sm text-slate-500">{{ $numero($siembra->cantidad_alevinos) }} peces de {{ $numero($siembra->peso_promedio_inicial_g, 2) }} g</p>
                </div>
            @empty
                <p class="text-slate-500">Sin siembras nuevas.</p>
            @endforelse
        </x-tarjeta>

        <x-tarjeta titulo="Cosechas y ventas" class="break-inside-avoid">
            @forelse ($cosechas as $cosecha)
                <p class="border-t border-slate-100 py-2 first:border-t-0">
                    {{ $cosecha->fecha->format('d/m') }} · Lago {{ $cosecha->lago->nombre }} · {{ $cosecha->siembra->nombre_especie }}:
                    {{ $numero($cosecha->kg, 1) }} kg ({{ $numero($cosecha->peces) }} peces)
                </p>
            @empty
                <p class="text-slate-500">Sin cosechas.</p>
            @endforelse
            @if ($ventas !== null)
                <div class="mt-2 space-y-1 border-t border-slate-200 pt-2">
                    <p>Vendido: <strong>{{ \App\Support\Formato::pesos($ventas['total']) }}</strong> ({{ $numero($ventas['kg'], 1) }} kg)</p>
                    <p class="text-sm text-slate-600">
                        A crédito: {{ \App\Support\Formato::pesos($ventas['a_credito']) }} · abonos recibidos: {{ \App\Support\Formato::pesos($ventas['abonos']) }}
                    </p>
                </div>
            @endif
        </x-tarjeta>

        <x-tarjeta titulo="Traslados" class="break-inside-avoid">
            @forelse ($traslados as $traslado)
                <p class="border-t border-slate-100 py-2 first:border-t-0">
                    {{ $traslado->fecha->format('d/m') }} · {{ $numero($traslado->cantidad) }} {{ $traslado->siembraOrigen->nombre_especie }}
                    de {{ $traslado->lagoOrigen->nombre }} a {{ $traslado->lagoDestino->nombre }}
                </p>
            @empty
                <p class="text-slate-500">Sin traslados.</p>
            @endforelse
        </x-tarjeta>

        <x-tarjeta titulo="Sanidad y agua" class="break-inside-avoid">
            @foreach ($tratamientos as $tratamiento)
                <p class="border-t border-slate-100 py-2 first:border-t-0">
                    {{ $tratamiento->fecha_inicio->format('d/m') }} · Lago {{ $tratamiento->lago->nombre }}: {{ $tratamiento->motivo }} ({{ $tratamiento->producto }})
                </p>
            @endforeach
            <p @class(['py-2', 'border-t border-slate-100' => $tratamientos->isNotEmpty()])>
                {{ $mediciones }} mediciones de agua, {{ $medicionesConAlerta }} con algún parámetro fuera de rango.
            </p>
        </x-tarjeta>

        <x-tarjeta titulo="Personal y seguridad" class="break-inside-avoid">
            <p class="py-1">{{ $tareasCompletadas }} tareas completadas.</p>
            <p class="py-1">{{ $numero($horasExtraAprobadas, 1) }} horas extra aprobadas.</p>
            <p class="py-1">{{ $novedades->count() }} novedades de seguridad.</p>
            @foreach ($novedades as $novedad)
                <p class="text-sm text-slate-600">{{ $novedad->registrada_at->format('d/m H:i') }} · {{ $novedad->textoTipo() }}: {{ $novedad->descripcion }}</p>
            @endforeach
        </x-tarjeta>

        @if ($entradasBodega !== null)
            <x-tarjeta titulo="Entradas a bodega" class="break-inside-avoid">
                @forelse ($entradasBodega as $entrada)
                    <p class="border-t border-slate-100 py-2 first:border-t-0">
                        {{ $entrada->fecha->format('d/m') }} · {{ $numero($entrada->cantidad_kg, 1) }} kg de {{ $entrada->tipo_concentrado }}
                        <span class="text-sm text-slate-500">({{ $entrada->proveedor }}, lote {{ $entrada->lote }})</span>
                    </p>
                @empty
                    <p class="text-slate-500">Sin entradas.</p>
                @endforelse
            </x-tarjeta>
        @endif
    </div>
@endsection
