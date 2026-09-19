@use('App\Support\LocalTime')
<x-app-layout title="{{ $patient->fullName }}" subtitle="{{ $patient->document() }}">
    <x-slot:breadcrumb>
        <a href="{{ route('reception.patients.index') }}" class="small text-decoration-none"><i class="bi bi-arrow-left me-1" aria-hidden="true"></i>Pacientes</a>
    </x-slot:breadcrumb>
    <x-slot:actions>
        @if ($patient->active)
            @can('reservas.crear')
                <x-button :href="route('booking.create', ['patient_id' => $patient->id])" icon="bi-calendar-plus">Reservar cita</x-button>
            @endcan
        @endif
        @can('pacientes.editar')
            <x-button :href="route('reception.patients.edit', $patient->id)" variant="outline-primary" icon="bi-pencil">Editar</x-button>
        @endcan
    </x-slot:actions>

    <div class="row g-3">
        <div class="col-12 col-lg-4">
            <x-card title="Datos del paciente" icon="bi-person-vcard">
                <dl class="detail-list mb-0">
                    <dt>Estado</dt><dd><x-badge :variant="$patient->active ? 'success' : 'secondary'">{{ $patient->active ? 'Activo' : 'Inactivo' }}</x-badge></dd>
                    <dt>Documento</dt><dd>{{ $patient->document() }}</dd>
                    <dt>Fecha de nacimiento</dt><dd>{{ LocalTime::date($patient->birthDate, 'd/m/Y') }}</dd>
                    <dt>Sexo</dt><dd>{{ ['F' => 'Femenino', 'M' => 'Masculino', 'X' => 'No especifica'][$patient->sex] ?? '—' }}</dd>
                    <dt>Teléfono</dt><dd>{{ $patient->phone ?? '—' }}</dd>
                    <dt>Correo</dt><dd class="text-break">{{ $patient->email ?? '—' }}</dd>
                    <dt>Dirección</dt><dd>{{ $patient->address ?? '—' }}</dd>
                    <dt>Contacto de emergencia</dt><dd>{{ $patient->emergencyContact ?? '—' }}</dd>
                    <dt>Usuario del portal</dt><dd class="mb-0">{{ $patient->username ?? 'Sin acceso al portal' }}</dd>
                </dl>
            </x-card>
        </div>
        <div class="col-12 col-lg-8">
            <x-card title="Citas del paciente" icon="bi-calendar2-week" flush>
                @if ($reservations->isEmpty())
                    <x-empty-state icon="bi-calendar-x" title="El paciente no tiene citas registradas" />
                @else
                    @include('partials.reservation-table', ['items' => $reservations->items])
                    @if ($reservations->total > count($reservations->items))
                        <p class="small text-secondary px-3 py-2 mb-0">
                            Se muestran las {{ count($reservations->items) }} más recientes de {{ $reservations->total }}.
                            @can('reservas.gestionar')
                                <a href="{{ route('admin.reservations.index', ['patient_id' => $patient->id]) }}">Ver todas</a>
                            @endcan
                        </p>
                    @endif
                @endif
            </x-card>
        </div>
    </div>

    @include('partials.cancel-modal', ['reasons' => app(\App\Services\ReservationService::class)->cancellationReasons(auth()->user())])
</x-app-layout>
