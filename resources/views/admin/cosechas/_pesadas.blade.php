{{-- Tabla de pesadas y totales de una cosecha. Recibe $pesaje = CalculoPesaje::deCosecha($cosecha). --}}
@php
    use App\Support\CalculoPesaje;
    $totales = $pesaje['totales'];
@endphp

<x-tabla :columnas="[__('cosechas.pesada'), __('cosechas.tipo'), __('cosechas.cantidad'), __('cosechas.peso_pesado'), __('cosechas.descuento'), __('cosechas.peso_limpio')]">
    @foreach ($pesaje['filas'] as $fila)
        <tr>
            <td data-label="{{ __('cosechas.pesada') }}" class="font-medium">{{ $fila['numero'] }}</td>
            <td data-label="{{ __('cosechas.tipo') }}">{{ __('cosechas.tipos.'.$fila['tipo']) }}</td>
            <td data-label="{{ __('cosechas.cantidad') }}" class="tabular-nums">{{ $fila['cantidad'] }} × {{ CalculoPesaje::kg($fila['peso_vacia']) }} kg</td>
            <td data-label="{{ __('cosechas.peso_pesado') }}" class="tabular-nums">{{ CalculoPesaje::kg($fila['peso_pesado']) }}</td>
            <td data-label="{{ __('cosechas.descuento') }}" class="tabular-nums">{{ CalculoPesaje::kg($fila['descuento']) }}</td>
            <td data-label="{{ __('cosechas.peso_limpio') }}" class="font-semibold tabular-nums">{{ CalculoPesaje::kg($fila['limpio']) }}</td>
        </tr>
    @endforeach
</x-tabla>

<dl class="mt-4 grid grid-cols-2 gap-3 rounded-xl bg-marca-50 p-4 text-base sm:grid-cols-5">
    <div><dt class="text-sm text-marca-800">{{ __('cosechas.total_pesadas') }}</dt><dd class="font-semibold tabular-nums">{{ $totales['pesadas'] }}</dd></div>
    <div><dt class="text-sm text-marca-800">{{ __('cosechas.total_cantidad') }}</dt>
        <dd class="font-semibold tabular-nums">{{ __('cosechas.cantidad_por_tipo', ['canastas' => $totales['por_tipo']['canasta'], 'canastillas' => $totales['por_tipo']['canastilla']]) }}</dd></div>
    <div><dt class="text-sm text-marca-800">{{ __('cosechas.total_pesado') }}</dt><dd class="font-semibold tabular-nums">{{ CalculoPesaje::kg($totales['pesado']) }}</dd></div>
    <div><dt class="text-sm text-marca-800">{{ __('cosechas.total_descuento') }}</dt><dd class="font-semibold tabular-nums">{{ CalculoPesaje::kg($totales['descuento']) }}</dd></div>
    <div><dt class="text-sm font-bold text-marca-900">{{ __('cosechas.total_limpio') }}</dt><dd class="text-xl font-bold tabular-nums text-marca-900">{{ CalculoPesaje::kg($totales['limpio']) }}</dd></div>
</dl>
<p class="mt-3 text-base text-slate-700">{{ CalculoPesaje::lineaCuenta($totales) }}</p>
