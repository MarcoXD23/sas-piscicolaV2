@extends('layouts.app')

@section('titulo', __('seguridad.personal.ingresos_titulo'))

@section('contenido')
    <a href="{{ route('admin.ajustes.edit') }}#seguridad" class="-ml-1 mb-2 inline-flex min-h-12 items-center gap-1 text-base font-semibold text-marca-700 underline decoration-renglon-fuerte underline-offset-4 hover:decoration-marca-700">
        <x-icono nombre="abajo" class="h-5 w-5 rotate-90" /> {{ __('seguridad.finca.titulo') }}
    </a>

    <x-encabezado :titulo="__('seguridad.personal.ingresos_titulo')" :subtitulo="__('seguridad.personal.ingresos_ayuda')" />

    {{-- Filtro por persona --}}
    <x-formulario :action="route('admin.ajustes.seguridad.ingresos')" metodo="GET" class="mb-6 flex max-w-xl flex-wrap items-end gap-3 !space-y-0">
        <div class="min-w-56 flex-1">
            <x-seleccion nombre="persona" :etiqueta="__('seguridad.personal.persona')" :valor="$persona?->id ?? ''"
                         :opciones="['' => __('seguridad.personal.todas')] + $personal->pluck('name', 'id')->all()" />
        </div>
        <x-boton variante="secundario">{{ __('seguridad.personal.filtrar') }}</x-boton>
    </x-formulario>

    <x-tabla :columnas="[__('seguridad.personal.columna_cuando'), __('seguridad.personal.columna_persona'), __('seguridad.personal.columna_resultado'), __('seguridad.personal.columna_equipo')]"
             :vacio="__('seguridad.personal.vacio')">
        @foreach ($ingresos as $ingreso)
            <tr>
                <td data-label="{{ __('seguridad.personal.columna_cuando') }}" class="whitespace-nowrap tabular-nums">{{ \App\Support\Region::local($ingreso->created_at)->formatoHora('d/m/Y H:i') }}</td>
                <td data-label="{{ __('seguridad.personal.columna_persona') }}" class="font-semibold">{{ $ingreso->usuario?->name }}</td>
                <td data-label="{{ __('seguridad.personal.columna_resultado') }}">
                    @if ($ingreso->exitoso)
                        <x-insignia tono="bien">{{ __('seguridad.ingresos.exitoso') }}</x-insignia>
                    @else
                        <x-insignia tono="alerta">{{ __('seguridad.ingresos.fallido') }}: {{ __('seguridad.ingresos.motivos.'.$ingreso->motivo) }}</x-insignia>
                    @endif
                    @if ($ingreso->equipo_nuevo) <x-insignia tono="atencion" class="mt-1">{{ __('seguridad.ingresos.equipo_nuevo') }}</x-insignia> @endif
                </td>
                <td data-label="{{ __('seguridad.personal.columna_equipo') }}">
                    {{ $ingreso->equipo }} · {{ __('seguridad.ingresos.canales.'.$ingreso->canal) }}
                    @if ($ingreso->red) <span class="block text-sm text-tinta-suave">{{ __('seguridad.ingresos.red', ['red' => $ingreso->red]) }}</span> @endif
                </td>
            </tr>
        @endforeach
    </x-tabla>

    <div class="mt-6">{{ $ingresos->links() }}</div>
@endsection
