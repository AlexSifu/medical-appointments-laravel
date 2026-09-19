@props(['label', 'value', 'icon' => 'bi-graph-up', 'tone' => 'blue', 'hint' => null, 'href' => null])
<div {{ $attributes->class(['card stat-card h-100']) }}>
    <div class="card-body d-flex gap-3 align-items-center">
        <span class="stat-icon tone-{{ $tone }}" aria-hidden="true"><i class="bi {{ $icon }}"></i></span>
        <div class="min-w-0">
            <div class="stat-value">{{ $value }}</div>
            <div class="stat-label">
                @if ($href)
                    <a href="{{ $href }}" class="stretched-link text-reset text-decoration-none">{{ $label }}</a>
                @else
                    {{ $label }}
                @endif
            </div>
            @if ($hint)
                <div class="small text-secondary">{{ $hint }}</div>
            @endif
        </div>
    </div>
</div>
