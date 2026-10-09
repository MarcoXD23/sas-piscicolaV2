@props(['tono' => 'neutro', 'icono' => null, 'pestana' => false, 'como' => 'span', 'latido' => false])

{{--
    Insignia de estado: siempre ícono y texto, nunca solo color.
    tono: bien, atencion, alerta, info, neutro o trucha (solo para lo que se celebra: listo para pescar, cosecha).
    pestana: se dibuja como la pestaña de una ficha de archivo, sobre el borde superior (el padre lleva «relative»).
    como: etiqueta HTML (span, p, h2…), para que la pestaña pueda ser el título del bloque.
    latido: el ícono late (solo para un estado que pide atención ahora: alertas abiertas). icono="" deja solo el texto del slot.
--}}
@php
    $tonos = [
        'bien' => 'bg-bien-fondo text-bien ring-bien-borde',
        'atencion' => 'bg-atencion-fondo text-atencion ring-atencion-borde',
        'alerta' => 'bg-alerta-fondo text-alerta ring-alerta-borde',
        'info' => 'bg-info-fondo text-info ring-info-borde',
        'neutro' => 'bg-papel-hondo text-tinta-suave ring-renglon',
        'trucha' => 'bg-ficha text-trucha ring-trucha/40',
    ];
    $iconos = ['bien' => 'ok', 'atencion' => 'alerta', 'alerta' => 'alerta', 'info' => 'reloj', 'trucha' => 'pez'];
    $icono ??= $iconos[$tono] ?? null;
@endphp

<{{ $como }} {{ $attributes->class([
    'items-center rounded-md font-bold ring-1',
    'inline-flex gap-1 px-2 py-0.5 text-sm' => ! $pestana,
    'absolute -top-3.5 left-4 inline-flex max-w-[calc(100%-2rem)] gap-1.5 px-2.5 py-1 text-sm' => $pestana,
    $tonos[$tono] ?? $tonos['neutro'],
]) }}>
    @if ($icono)
        <x-icono :nombre="$icono" @class(['shrink-0', 'h-3.5 w-3.5' => ! $pestana, 'h-4 w-4' => $pestana, 'animate-latido' => $latido]) />
    @endif
    <span class="min-w-0">{{ $slot }}</span>
</{{ $como }}>
