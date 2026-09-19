@props(['doctor', 'bookable' => false])
{{-- $doctor: App\DTO\DoctorData --}}
<article {{ $attributes->class(['card h-100']) }}>
    <div class="card-body d-flex flex-column">
        <div class="d-flex gap-3 align-items-start mb-3">
            <span class="avatar avatar-lg" aria-hidden="true">{{ $doctor->initials() }}</span>
            <div class="min-w-0">
                <h3 class="h6 mb-1">{{ $doctor->fullName }}</h3>
                <p class="small text-secondary mb-0">CMP {{ $doctor->cmp }}</p>
            </div>
        </div>
        <dl class="small mb-3">
            <dt class="text-secondary fw-normal">Especialidad</dt>
            <dd class="mb-2">{{ $doctor->mainSpecialty ?? (implode(', ', $doctor->specialties) ?: '—') }}</dd>
            <dt class="text-secondary fw-normal">Sede</dt>
            <dd class="mb-2">{{ implode(', ', $doctor->branches) ?: '—' }}</dd>
            <dt class="text-secondary fw-normal">Próxima disponibilidad</dt>
            <dd class="mb-0">
                @if ($doctor->nextAvailableDate)
                    <span class="text-success"><i class="bi bi-calendar-check me-1" aria-hidden="true"></i>{{ \App\Support\LocalTime::date($doctor->nextAvailableDate) }}</span>
                @else
                    <span class="text-secondary">Sin horarios publicados</span>
                @endif
            </dd>
        </dl>
        @if ($bookable && $doctor->nextAvailableDate)
            <a class="btn btn-primary btn-sm mt-auto" href="{{ route('booking.create', ['specialty_id' => $doctor->mainSpecialtyId, 'doctor_id' => $doctor->id]) }}">
                <i class="bi bi-calendar-plus me-1" aria-hidden="true"></i>Reservar con este médico
            </a>
        @endif
    </div>
</article>
