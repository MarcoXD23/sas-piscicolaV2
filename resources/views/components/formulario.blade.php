@props(['action', 'metodo' => 'POST', 'confirmar' => null])

@php
    $metodo = strtoupper($metodo);
@endphp

{{-- confirmar: pregunta antes de enviar (data-confirmar, resources/js/app.js; sin onsubmit, que la CSP bloquea) --}}
<form action="{{ $action }}" method="{{ $metodo === 'GET' ? 'GET' : 'POST' }}" @if ($confirmar) data-confirmar="{{ $confirmar }}" @endif {{ $attributes->class('space-y-5') }}>
    @if ($metodo !== 'GET')
        @csrf
    @endif

    @if (! in_array($metodo, ['GET', 'POST'], true))
        @method($metodo)
    @endif

    {{ $slot }}
</form>
