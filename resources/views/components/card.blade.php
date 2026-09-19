@props(['title' => null, 'icon' => null, 'flush' => false])
<section {{ $attributes->class(['card']) }} @if ($title) aria-label="{{ $title }}" @endif>
    @if ($title || isset($actions))
        <div class="card-header d-flex align-items-center gap-2 flex-wrap">
            @if ($title)
                <h2 class="h6 mb-0 fw-semibold">
                    @if ($icon)<i class="bi {{ $icon }} me-1 text-primary" aria-hidden="true"></i>@endif{{ $title }}
                </h2>
            @endif
            @isset($actions)
                <div class="ms-auto d-flex gap-2 flex-wrap">{{ $actions }}</div>
            @endisset
        </div>
    @endif
    <div @class(['card-body' => ! $flush])>
        {{ $slot }}
    </div>
    @isset($footer)
        <div class="card-footer bg-transparent">{{ $footer }}</div>
    @endisset
</section>
