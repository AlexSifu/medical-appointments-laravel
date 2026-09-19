@props(['variant' => 'secondary', 'icon' => null])
<span {{ $attributes->class(['badge-status', 'text-bg-'.$variant]) }}>
    @if ($icon)<i class="bi {{ $icon }}" aria-hidden="true"></i>@endif{{ $slot }}
</span>
