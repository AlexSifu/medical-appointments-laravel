@use('App\Support\LocalTime')
<x-app-layout title="Reprogramar cita" subtitle="Elige un nuevo horario. La cita actual se libera solo cuando la nueva queda confirmada.">
    <x-slot:breadcrumb>
        <a href="{{ route('reservations.show', $reservation->id) }}" class="small text-decoration-none"><i class="bi bi-arrow-left me-1" aria-hidden="true"></i>Volver a la cita {{ $reservation->code }}</a>
    </x-slot:breadcrumb>

    <x-card class="mb-3">
        <div class="d-flex flex-wrap gap-3 align-items-center">
            <span class="code-pill">{{ $reservation->code }}</span>
            <span><strong>Cita actual:</strong> {{ $reservation->specialty }} · {{ $reservation->doctorName }}</span>
            <span class="text-secondary">{{ LocalTime::date($reservation->date) }} · {{ LocalTime::time($reservation->start) }} · {{ $reservation->branch }}</span>
        </div>
    </x-card>

    @include('booking.partials.wizard', [
        'mode' => 'reschedule',
        'specialties' => [],
        'fixedSpecialty' => ['id' => $reservation->specialtyId, 'name' => $reservation->specialty],
        'action' => route('reservations.reschedule', $reservation->id),
        'slotField' => 'new_slot_id',
        'patientName' => $reservation->patientName ?? auth()->user()->name(),
        'hidden' => ['version' => $reservation->version, 'idempotency_key' => $idempotencyKey],
    ])
</x-app-layout>
