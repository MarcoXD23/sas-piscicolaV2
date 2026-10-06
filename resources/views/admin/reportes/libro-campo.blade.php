@extends('layouts.app')

@section('titulo', 'Libro de campo · '.$lago->nombre)

@section('contenido')
    @php $numero = fn ($valor, $decimales = 0) => number_format((float) $valor, $decimales, ',', '.'); @endphp

    <x-encabezado-reporte :titulo="'Libro de campo · Lago '.$lago->nombre"
                          :periodo="'Del '.$desde->format('d/m/Y').' al '.$hasta->format('d/m/Y').' · lago en '.$lago->tipo" />

    <section class="mb-6 break-inside-avoid">
        <h2 class="mb-2 text-lg font-semibold text-slate-900">Siembras del período</h2>
        <x-tabla :columnas="['Especie', 'Fecha', 'Peces sembrados', 'Proveedor', 'Lote', 'Estado']" vacio="No hubo siembras en el período.">
            @foreach ($siembras as $siembra)
                <tr>
                    <td data-label="Especie" class="font-semibold">{{ $siembra->nombre_especie }}</td>
                    <td data-label="Fecha">{{ $siembra->fecha->format('d/m/Y') }}</td>
                    <td data-label="Peces sembrados" class="tabular-nums">{{ $numero($siembra->cantidad_alevinos) }} ({{ $numero($siembra->peso_promedio_inicial_g, 2) }} g)</td>
                    <td data-label="Proveedor">{{ $siembra->proveedor_alevinos ?: 'Sin registrar' }}</td>
                    <td data-label="Lote">{{ $siembra->lote_alevinos ?: 'Sin registrar' }}</td>
                    <td data-label="Estado">{{ $siembra->estaActiva() ? 'Activa' : 'Finalizada el '.$siembra->finalizada_at->format('d/m/Y') }}</td>
                </tr>
            @endforeach
        </x-tabla>
    </section>

    <section>
        <h2 class="mb-2 text-lg font-semibold text-slate-900">Registro cronológico</h2>
        <x-tabla :columnas="['Fecha', 'Actividad', 'Detalle', 'Responsable']" vacio="No hay registros en el período.">
            @foreach ($registros as $registro)
                <tr>
                    <td data-label="Fecha" class="whitespace-nowrap tabular-nums">{{ $registro['fecha']->format('d/m/Y') }}</td>
                    <td data-label="Actividad" class="font-medium">{{ $registro['titulo'] }}</td>
                    <td data-label="Detalle">{{ $registro['detalle'] }}</td>
                    <td data-label="Responsable">{{ $registro['responsable'] ?? '—' }}</td>
                </tr>
            @endforeach
        </x-tabla>
    </section>

    <p class="mt-6 text-sm text-slate-500">
        Formato de apoyo para la trazabilidad del cultivo. Verifica con tu oficina del ICA los datos y la firma que exige la norma vigente.
    </p>
    <div class="mt-10 hidden grid-cols-2 gap-10 print:grid">
        <p class="border-t border-slate-400 pt-2 text-sm">Responsable técnico</p>
        <p class="border-t border-slate-400 pt-2 text-sm">Propietario</p>
    </div>
@endsection
