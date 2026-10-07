{{-- Historial de aprobación y cambios de roles de una persona (de la bitácora, solo inserción). $registros --}}
@if ($registros->isNotEmpty())
    <details class="mt-2 text-sm" data-historial-roles>
        <summary class="min-h-11 cursor-pointer content-center font-medium text-marca-700">{{ __('personal.ver_historial', ['cantidad' => $registros->count()]) }}</summary>
        <ol class="mt-1 space-y-1.5 border-l-2 border-slate-200 pl-3">
            @foreach ($registros as $registro)
                <li>
                    <time class="tabular-nums text-slate-500" datetime="{{ $registro->momentoLocal()->toIso8601String() }}">{{ $registro->momentoLocal()->format('d/m/Y H:i') }}</time>
                    <span class="text-slate-700">· {{ $registro->textoDetalle() }}</span>
                    <span class="block text-xs text-slate-500">{{ __('personal.hecho_por', ['nombre' => $registro->usuario->name]) }}</span>
                </li>
            @endforeach
        </ol>
    </details>
@endif
