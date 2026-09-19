@props(['caption' => null, 'headers' => []])
<div class="table-responsive">
    <table {{ $attributes->class(['table table-hover align-middle']) }}>
        @if ($caption)
            <caption class="visually-hidden">{{ $caption }}</caption>
        @endif
        @if ($headers)
            <thead>
                <tr>
                    @foreach ($headers as $header)
                        <th scope="col" @class(['text-end' => str_starts_with($header, '>')])>{{ ltrim($header, '>') }}</th>
                    @endforeach
                </tr>
            </thead>
        @endif
        <tbody>
            {{ $slot }}
        </tbody>
    </table>
</div>
