@extends('layouts.app')

@section('titulo', __('reportes.libro_campo_de', ['lago' => $lago->nombre]))

@section('contenido')
    @php $numero = fn ($valor, $decimales = 0) => \App\Support\Formato::numero((float) $valor, $decimales); @endphp

    <x-encabezado-reporte :titulo="__('reportes.libro_campo_lago', ['lago' => $lago->nombre])"
                          :periodo="__('reportes.periodo_libro', ['desde' => $desde->format('d/m/Y'), 'hasta' => $hasta->format('d/m/Y'), 'tipo' => __('lagos.tipos.'.$lago->tipo)])" />

    <section class="mb-6 break-inside-avoid">
        <h2 class="mb-2 text-lg font-semibold text-slate-900">{{ __('reportes.siembras_periodo') }}</h2>
        <x-tabla :columnas="[__('reportes.especie'), __('comun.fecha'), __('reportes.peces_sembrados'), __('reportes.proveedor'), __('reportes.lote'), __('reportes.estado')]" :vacio="__('reportes.no_hubo_siembras_periodo')">
            @foreach ($siembras as $siembra)
                <tr>
                    <td data-label="{{ __('reportes.especie') }}" class="font-semibold">{{ $siembra->nombre_especie }}</td>
                    <td data-label="{{ __('comun.fecha') }}">{{ $siembra->fecha->format('d/m/Y') }}</td>
                    <td data-label="{{ __('reportes.peces_sembrados') }}" class="tabular-nums">{{ __('reportes.peces_con_peso', ['peces' => $numero($siembra->cantidad_alevinos), 'gramos' => $numero($siembra->peso_promedio_inicial_g, 2)]) }}</td>
                    <td data-label="{{ __('reportes.proveedor') }}">{{ $siembra->proveedor_alevinos ?: __('reportes.sin_registrar') }}</td>
                    <td data-label="{{ __('reportes.lote') }}">{{ $siembra->lote_alevinos ?: __('reportes.sin_registrar') }}</td>
                    <td data-label="{{ __('reportes.estado') }}">{{ $siembra->estaActiva() ? __('reportes.activa') : __('reportes.finalizada_el', ['fecha' => $siembra->finalizada_at->format('d/m/Y')]) }}</td>
                </tr>
            @endforeach
        </x-tabla>
    </section>

    <section>
        <h2 class="mb-2 text-lg font-semibold text-slate-900">{{ __('reportes.registro_cronologico') }}</h2>
        <x-tabla :columnas="[__('comun.fecha'), __('reportes.actividad'), __('reportes.detalle'), __('reportes.responsable')]" :vacio="__('reportes.no_hay_registros_periodo')">
            @foreach ($registros as $registro)
                <tr>
                    <td data-label="{{ __('comun.fecha') }}" class="whitespace-nowrap tabular-nums">{{ $registro['fecha']->format('d/m/Y') }}</td>
                    <td data-label="{{ __('reportes.actividad') }}" class="font-medium">{{ $registro['titulo'] }}</td>
                    <td data-label="{{ __('reportes.detalle') }}">{{ $registro['detalle'] }}</td>
                    <td data-label="{{ __('reportes.responsable') }}">{{ $registro['responsable'] ?? '—' }}</td>
                </tr>
            @endforeach
        </x-tabla>
    </section>

    <p class="mt-6 text-sm text-slate-500">
        {{ __('region.aviso_libro_campo') }}
    </p>
    <div class="mt-10 hidden grid-cols-2 gap-10 print:grid">
        <p class="border-t border-slate-400 pt-2 text-sm">{{ __('reportes.responsable_tecnico') }}</p>
        <p class="border-t border-slate-400 pt-2 text-sm">{{ __('reportes.propietario') }}</p>
    </div>
@endsection
