{{-- Historial de aprobación y cambios de roles de una persona (de la bitácora, solo inserción). $registros --}}
@if ($registros->isNotEmpty())
    <details class="mt-2 text-sm" data-historial-roles>
        <summary class="min-h-12 cursor-pointer content-center font-medium text-marca-700">{{ __('personal.ver_historial', ['cantidad' => $registros->count()]) }}</summary>
        <ol class="mt-1 space-y-1.5 border-l-2 border-renglon pl-3">
            @foreach ($registros as $registro)
                <li>
                    <time class="tabular-nums text-tinta-suave" datetime="{{ $registro->momentoLocal()->toIso8601String() }}">{{ $registro->momentoLocal()->formatoHora('d/m/Y H:i') }}</time>
                    <span class="text-tinta">· {{ $registro->textoDetalle() }}</span>
                    <span class="block text-sm text-tinta-suave">{{ __('personal.hecho_por', ['nombre' => $registro->usuario->name]) }}</span>
                </li>
            @endforeach
        </ol>
    </details>
@endif
