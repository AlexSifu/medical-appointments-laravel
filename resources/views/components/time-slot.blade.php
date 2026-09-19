@props(['start', 'end' => null, 'state' => 'DISPONIBLE', 'selected' => false])
@php
    $map = [
        'DISPONIBLE' => ['is-available', 'Disponible'],
        'BLOQUEADO' => ['is-blocked', 'Bloqueado'],
        'NO_DISPONIBLE' => ['is-unavailable', 'No disponible'],
    ];
    [$class, $label] = $map[$state] ?? $map['NO_DISPONIBLE'];
    $label = $selected ? 'Seleccionado' : $label;
@endphp
<span {{ $attributes->class(['time-slot', $class, 'is-selected' => $selected]) }}
    aria-label="{{ $start }}{{ $end ? ' a '.$end : '' }}, {{ $label }}">
    {{ $start }}<small>{{ $label }}</small>
</span>
