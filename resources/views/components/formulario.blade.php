@props(['action', 'metodo' => 'POST'])

@php
    $metodo = strtoupper($metodo);
@endphp

<form action="{{ $action }}" method="{{ $metodo === 'GET' ? 'GET' : 'POST' }}" {{ $attributes->class('space-y-5') }}>
    @if ($metodo !== 'GET')
        @csrf
    @endif

    @if (! in_array($metodo, ['GET', 'POST'], true))
        @method($metodo)
    @endif

    {{ $slot }}
</form>

