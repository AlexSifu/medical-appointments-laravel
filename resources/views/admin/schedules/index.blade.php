@use('App\Support\LocalTime')
<x-app-layout title="Agendas" subtitle="Bloques de atención publicados por médico, sede y consultorio.">
    <x-slot:actions>
        <x-button :href="route('admin.schedules.day', array_filter(['doctor_id' => $filters['doctor_id'] ?? null]))" variant="outline-primary" icon="bi-grid-3x3-gap">Vista del día</x-button>
        @can('agenda.bloquear')
            <x-button :href="route('admin.schedules.blocks')" variant="outline-primary" icon="bi-slash-circle">Bloqueos</x-button>
        @endcan
        <x-button :href="route('admin.schedules.create')" icon="bi-calendar-plus">Nueva agenda</x-button>
    </x-slot:actions>

    @include('admin.schedules.partials.filters', ['route' => 'admin.schedules.index'])

    <x-card flush>
        @if ($page->isEmpty())
            <x-empty-state icon="bi-calendar-x" title="No hay agendas en ese rango" message="Crea una agenda para publicar horarios disponibles.">
                <x-button :href="route('admin.schedules.create')" icon="bi-calendar-plus" variant="outline-primary">Nueva agenda</x-button>
            </x-empty-state>
        @else
            <x-table caption="Agendas" :headers="['Fecha', 'Horario', 'Médico', 'Especialidad', 'Sede / Consultorio', 'Ocupación', 'Estado', '>Acciones']">
                @foreach ($page->items as $a)
                    <tr @class(['text-secondary' => ! $a->active])>
                        <td class="text-nowrap">{{ LocalTime::date($a->date) }}</td>
                        <td class="text-nowrap">{{ LocalTime::time($a->start) }}–{{ LocalTime::time($a->end) }}<div class="small text-secondary">{{ $a->durationMinutes }} min · {{ $a->careType }}</div></td>
                        <td>{{ $a->doctorName }}</td>
                        <td>{{ $a->specialty }}</td>
                        <td>{{ $a->branch }}<div class="small text-secondary">{{ $a->room }}</div></td>
                        <td style="min-width: 9rem">
                            <div class="progress" role="progressbar" aria-label="Ocupación" aria-valuenow="{{ $a->occupancyPercent() }}" aria-valuemin="0" aria-valuemax="100" style="height: .5rem">
                                <div class="progress-bar bg-success" style="width: {{ $a->occupancyPercent() }}%"></div>
                            </div>
                            <div class="small text-secondary mt-1">{{ $a->bookedSlots }} reservados · {{ $a->freeSlots }} libres @if ($a->blockedSlots)· {{ $a->blockedSlots }} bloq.@endif</div>
                        </td>
                        <td><x-badge :variant="$a->active ? 'success' : 'secondary'">{{ $a->active ? 'Activa' : 'Inactiva' }}</x-badge></td>
                        <td class="text-end text-nowrap">
                            @if ($a->doctorId)
                                <a href="{{ route('admin.schedules.day', ['doctor_id' => $a->doctorId, 'date' => $a->date]) }}" class="btn btn-sm btn-outline-primary" aria-label="Ver día {{ $a->date }}">Ver día</a>
                            @endif
                            @if ($a->active)
                                <form method="POST" action="{{ route('admin.schedules.deactivate', $a->id) }}" class="d-inline"
                                    data-confirm="¿Desactivar esta agenda? Solo es posible si no tiene reservas confirmadas." data-confirm-variant="danger">
                                    @csrf
                                    <input type="hidden" name="version" value="{{ $a->version }}">
                                    <button class="btn btn-sm btn-outline-danger" aria-label="Desactivar agenda"><i class="bi bi-power" aria-hidden="true"></i></button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </x-table>
        @endif
    </x-card>
    <x-pagination :result="$page" />
</x-app-layout>
