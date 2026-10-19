@props(['value'])
@php
    $amount = (string) $value;
    $negative = str_starts_with($amount, '-');
    [$whole, $fraction] = array_pad(explode('.', ltrim($amount, '-'), 2), 2, '');
    $formatted = ($negative ? '-' : '').preg_replace('/\B(?=(\d{3})+(?!\d))/', '.', $whole).','.str_pad(substr($fraction, 0, 2), 2, '0');
@endphp
Rp {{ $formatted }}
