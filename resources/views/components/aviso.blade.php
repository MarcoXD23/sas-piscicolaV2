@props(['tono' => 'info', 'icono' => null])

{{--
    Aviso en bloque: ícono y texto con el tono del estado (bien, atencion, alerta, info).
    El slot «accion» (opcional) pone un botón o enlace a la derecha; en celular pasa debajo del texto.
    Un aviso de alerta se anuncia de inmediato (role="alert"); los demás, con calma (role="status").
--}}
@php
    $tonos = [
        'bien' => 'border-bien-borde bg-bien-fondo text-bien',
        'atencion' => 'border-atencion-borde bg-atencion-fondo text-atencion',
        'alerta' => 'border-alerta-borde bg-alerta-fondo text-alerta',
        'info' => 'border-info-borde bg-info-fondo text-info',
    ];
    $iconos = ['bien' => 'ok', 'atencion' => 'alerta', 'alerta' => 'alerta', 'info' => 'reloj'];
    $icono ??= $iconos[$tono] ?? 'alerta';
@endphp

<div {{ $attributes->merge(['role' => $tono === 'alerta' ? 'alert' : 'status'])->class([
    'flex flex-wrap items-center justify-between gap-x-4 gap-y-3 rounded-ficha border px-4 py-3 text-base',
    $tonos[$tono] ?? $tonos['info'],
]) }}>
    <div class="flex min-w-0 flex-1 basis-60 items-start gap-2.5 font-semibold">
        <x-icono :nombre="$icono" class="mt-0.5 h-5 w-5" />
        <div class="min-w-0">{{ $slot }}</div>
    </div>
    @isset($accion)
        <div class="shrink-0">{{ $accion }}</div>
    @endisset
</div>
