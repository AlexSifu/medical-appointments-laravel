@props(['variant' => 'primary', 'icon' => null, 'href' => null, 'type' => 'submit', 'size' => null, 'loading' => null])
@php
    $classes = ['btn', 'btn-'.$variant, 'btn-'.$size => $size, 'd-inline-flex align-items-center justify-content-center gap-2'];
@endphp
@if ($href)
    <a href="{{ $href }}" {{ $attributes->class($classes) }}>
        @if ($icon)<i class="bi {{ $icon }}" aria-hidden="true"></i>@endif
        <span>{{ $slot }}</span>
    </a>
@else
    <button type="{{ $type }}" {{ $attributes->class($classes) }} @if ($loading) data-loading-text="{{ $loading }}" @endif>
        @if ($icon)<i class="bi {{ $icon }}" aria-hidden="true"></i>@endif
        <span>{{ $slot }}</span>
    </button>
@endif
