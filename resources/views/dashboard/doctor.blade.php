@use('App\Support\LocalTime')
<x-app-layout title="Buen día, Dr(a). {{ auth()->user()->data->lastNames }}" subtitle="{{ ucfirst(LocalTime::longDate(LocalTime::today()->toDateString())) }}">
    <x-slot:actions>
        <x-button :href="route('doctor.day')" icon="bi-clipboard2-pulse">Atender hoy</x-button>
        <x-button :href="route('doctor.agenda')" variant="outline-primary" icon="bi-calendar3">Mi agenda</x-button>
    </x-slot:actions>

    <div class="row g-3 mb-4">
        <div class="col-6 col-lg-3"><x-stat-card label="Citas de hoy" :value="$data->kpi('today')" icon="bi-calendar-day" tone="blue" /></div>
        <div class="col-6 col-lg-3"><x-stat-card label="Pendientes" :value="$data->kpi('pending')" icon="bi-hourglass-split" tone="warning" /></div>
        <div class="col-6 col-lg-3"><x-stat-card label="Atendidas" :value="$data->kpi('attended')" icon="bi-check2-circle" tone="success" /></div>
        <div class="col-6 col-lg-3"><x-stat-card label="No asistieron" :value="$data->kpi('no_show')" icon="bi-person-x" tone="danger" /></div>
    </div>

    <x-card title="Pacientes de hoy" icon="bi-people" flush>
        <x-slot:actions>
            <a href="{{ route('doctor.day') }}" class="btn btn-sm btn-link">Ir a la atención del día</a>
        </x-slot:actions>
        @if ($data->reservations)
            <x-table caption="Pacientes de hoy" :headers="['Hora', 'Paciente', 'Especialidad', 'Consultorio', 'Estado']">
                @foreach ($data->reservations as $r)
                    <tr>
                        <td class="fw-semibold text-nowrap">{{ LocalTime::time($r->start) }}</td>
                        <td>{{ $r->patientName }}<div class="small text-secondary">{{ $r->patientDocument }}</div></td>
                        <td>{{ $r->specialty }}</td>
                        <td>{{ $r->branch }} · {{ $r->room }}</td>
                        <td><x-reservation-status :code="$r->statusCode" :name="$r->statusName" /></td>
                    </tr>
                @endforeach
            </x-table>
        @else
            <x-empty-state icon="bi-cup-hot" title="No tienes pacientes programados hoy" />
        @endif
    </x-card>
</x-app-layout>
