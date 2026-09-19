@use('App\Support\LocalTime')
<x-app-layout title="Recepción" subtitle="{{ ucfirst(LocalTime::longDate(LocalTime::today()->toDateString())) }}">
    <x-slot:actions>
        @can('reservas.crear')
            <x-button :href="route('booking.create')" icon="bi-calendar-plus">Nueva reserva</x-button>
        @endcan
        @can('pacientes.crear')
            <x-button :href="route('reception.patients.create')" variant="outline-primary" icon="bi-person-plus">Registrar paciente</x-button>
        @endcan
    </x-slot:actions>

    @include('dashboard.partials.kpis')

    <x-card title="Buscar paciente" icon="bi-search" class="mb-3">
        <form method="GET" action="{{ route('reception.patients.index') }}" class="row g-2 align-items-end" role="search">
            <div class="col-12 col-md">
                <label for="q" class="form-label">Documento, nombre o correo</label>
                <input type="search" id="q" name="q" class="form-control" maxlength="100" placeholder="Ej. 40123456 o Pérez">
            </div>
            <div class="col-12 col-md-auto">
                <button class="btn btn-primary w-100"><i class="bi bi-search me-1" aria-hidden="true"></i>Buscar</button>
            </div>
        </form>
    </x-card>

    <div class="row g-3">
        <div class="col-12 col-xl-8">
            <x-card title="Citas de hoy" icon="bi-calendar-day" flush>
                <x-slot:actions>
                    <a href="{{ route('reception.index') }}" class="btn btn-sm btn-link">Ver agenda del día</a>
                </x-slot:actions>
                @if ($data->reservations)
                    <x-table caption="Citas de hoy" :headers="['Hora', 'Paciente', 'Médico', 'Sede', 'Estado', '>Acción']">
                        @foreach ($data->reservations as $r)
                            <tr>
                                <td class="fw-semibold text-nowrap">{{ LocalTime::time($r->start) }}</td>
                                <td>{{ $r->patientName }}<div class="small text-secondary">{{ $r->patientDocument }}</div></td>
                                <td>{{ $r->doctorName }}<div class="small text-secondary">{{ $r->specialty }}</div></td>
                                <td>{{ $r->branch }}</td>
                                <td><x-reservation-status :code="$r->statusCode" :name="$r->statusName" /></td>
                                <td class="text-end"><a href="{{ route('reservations.show', $r->id) }}" class="btn btn-sm btn-outline-primary" aria-label="Ver cita {{ $r->code }}">Ver</a></td>
                            </tr>
                        @endforeach
                    </x-table>
                @else
                    <x-empty-state icon="bi-calendar-x" title="No hay citas registradas para hoy" />
                @endif
            </x-card>
        </div>
        <div class="col-12 col-xl-4">
            <x-card title="Cancelaciones recientes" icon="bi-x-circle" flush>
                @if ($data->history)
                    <ul class="list-group list-group-flush">
                        @foreach ($data->history as $r)
                            <li class="list-group-item">
                                <a href="{{ route('reservations.show', $r->id) }}" class="fw-semibold text-decoration-none">{{ $r->code }}</a>
                                <div class="small">{{ $r->patientName }}</div>
                                <div class="small text-secondary">{{ LocalTime::date($r->date) }} {{ LocalTime::time($r->start) }} · {{ $r->cancellationReason ?? 'Sin motivo' }}</div>
                            </li>
                        @endforeach
                    </ul>
                @else
                    <x-empty-state icon="bi-check2-all" title="Sin cancelaciones" />
                @endif
            </x-card>
        </div>
    </div>
</x-app-layout>
