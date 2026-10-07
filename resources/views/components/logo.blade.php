@props(['alt' => null])

{{-- Ícono de la marca (trucha arcoíris con corona). Archivo liviano de public/icons; el original queda en docs/marca. --}}
<img src="/icons/icon-192.png" width="192" height="192" decoding="async" alt="{{ $alt ?? __('layout.logo_alt') }}" {{ $attributes->class('object-cover') }}>
