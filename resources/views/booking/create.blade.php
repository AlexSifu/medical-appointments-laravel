<x-app-layout title="Reservar cita" subtitle="Elige especialidad, médico, fecha y horario en pocos pasos.">
    @if ($staffBooking && ! $patient)
        <x-card title="Selecciona al paciente" icon="bi-person-check">
            <p class="text-secondary">Para registrar una reserva en recepción primero busca al paciente o regístralo.</p>
            <form method="GET" action="{{ route('reception.patients.index') }}" class="row g-2 align-items-end" role="search">
                <div class="col-12 col-md">
                    <label for="q" class="form-label">Documento o nombre</label>
                    <input type="search" id="q" name="q" class="form-control" maxlength="100" required>
                </div>
                <div class="col-12 col-md-auto d-flex gap-2">
                    <button class="btn btn-primary"><i class="bi bi-search me-1" aria-hidden="true"></i>Buscar</button>
                    @can('pacientes.crear')
                        <a href="{{ route('reception.patients.create') }}" class="btn btn-outline-primary"><i class="bi bi-person-plus me-1" aria-hidden="true"></i>Nuevo paciente</a>
                    @endcan
                </div>
            </form>
        </x-card>
    @else
        @if ($patient && $staffBooking)
            <x-alert type="info">
                Reservando para <strong>{{ $patient->fullName }}</strong> ({{ $patient->document() }}).
                <a href="{{ route('booking.create') }}" class="ms-1">Cambiar paciente</a>
            </x-alert>
        @endif
        @include('booking.partials.wizard', [
            'mode' => 'book',
            'action' => route('reservations.store'),
            'slotField' => 'slot_id',
            'patientName' => $patient?->fullName ?? auth()->user()->name(),
            'hidden' => array_filter([
                'idempotency_key' => $idempotencyKey,
                'patient_id' => $staffBooking ? $patient?->id : null,
            ], fn ($v) => $v !== null),
        ])
    @endif
</x-app-layout>
