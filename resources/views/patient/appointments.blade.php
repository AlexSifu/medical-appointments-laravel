<x-app-layout title="Mis citas" subtitle="Consulta, reprograma o cancela tus citas.">
    <x-slot:actions>
        <x-button :href="route('booking.create')" icon="bi-calendar-plus">Reservar cita</x-button>
    </x-slot:actions>

    @php
        $tabs = ['proximas' => ['Próximas', 'bi-calendar-check'], 'historial' => ['Historial', 'bi-clock-history'], 'canceladas' => ['Canceladas', 'bi-x-circle']];
    @endphp
    <nav aria-label="Filtro de citas" class="mb-3">
        <ul class="nav nav-pills gap-1">
            @foreach ($tabs as $key => [$label, $icon])
                <li class="nav-item">
                    <a href="{{ route('patient.appointments', ['tab' => $key]) }}" @class(['nav-link', 'active' => $tab === $key]) @if ($tab === $key) aria-current="page" @endif>
                        <i class="bi {{ $icon }} me-1" aria-hidden="true"></i>{{ $label }}
                    </a>
                </li>
            @endforeach
        </ul>
    </nav>

    <x-card flush>
        @if ($page->isEmpty())
            @if ($tab === 'proximas')
                <x-empty-state icon="bi-calendar-plus" title="No tienes citas próximas" message="Cuando reserves una cita aparecerá aquí.">
                    <x-button :href="route('booking.create')" icon="bi-calendar-plus">Reservar cita</x-button>
                </x-empty-state>
            @elseif ($tab === 'canceladas')
                <x-empty-state icon="bi-check2-all" title="No tienes citas canceladas" />
            @else
                <x-empty-state icon="bi-archive" title="Aún no tienes historial de citas" />
            @endif
        @else
            <ul class="list-group list-group-flush">
                @foreach ($page->items as $r)
                    @include('partials.reservation-item', ['r' => $r, 'cancelInline' => true])
                @endforeach
            </ul>
        @endif
    </x-card>
    <x-pagination :result="$page" />

    @include('partials.cancel-modal', ['reasons' => $reasons])
</x-app-layout>
