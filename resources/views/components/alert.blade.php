@props(['type' => 'info', 'dismissible' => false, 'icon' => null])
@php
    $icons = ['success' => 'bi-check-circle', 'danger' => 'bi-exclamation-octagon', 'warning' => 'bi-exclamation-triangle', 'info' => 'bi-info-circle'];
    $role = in_array($type, ['danger', 'warning'], true) ? 'alert' : 'status';
@endphp
<div {{ $attributes->class(['alert', 'alert-'.$type, 'd-flex gap-2 align-items-start', 'alert-dismissible fade show' => $dismissible]) }} role="{{ $role }}">
    <i class="bi {{ $icon ?? ($icons[$type] ?? 'bi-info-circle') }} flex-shrink-0 mt-1" aria-hidden="true"></i>
    <div class="flex-grow-1">{{ $slot }}</div>
    @if ($dismissible)
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar aviso"></button>
    @endif
</div>
