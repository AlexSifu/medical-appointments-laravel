{{-- Fila de cita en listas (paciente / dashboards). $r: App\DTO\ReservationData; $actions: bool --}}
@php $actions = $actions ?? true; @endphp
<li class="list-group-item px-3 py-3">
    <div class="d-flex flex-column flex-md-row gap-3 align-items-md-center">
        <div class="text-center flex-shrink-0 rounded-3 border px-3 py-2 bg-body" style="min-width:5.5rem">
            <div class="small text-secondary text-uppercase">{{ \App\Support\LocalTime::date($r->date, 'D') }}</div>
            <div class="fs-4 fw-bold lh-1">{{ \App\Support\LocalTime::date($r->date, 'j') }}</div>
            <div class="small text-secondary">{{ \App\Support\LocalTime::date($r->date, 'M Y') }}</div>
        </div>
        <div class="flex-grow-1 min-w-0">
            <div class="d-flex flex-wrap gap-2 align-items-center mb-1">
                <span class="fw-semibold">{{ $r->specialty }}</span>
                <x-reservation-status :code="$r->statusCode" :name="$r->statusName" />
                <span class="code-pill">{{ $r->code }}</span>
            </div>
            <div class="small text-secondary">
                <i class="bi bi-clock me-1" aria-hidden="true"></i>{{ \App\Support\LocalTime::time($r->start) }} – {{ \App\Support\LocalTime::time($r->end) }}
                <span class="mx-1" aria-hidden="true">·</span>
                <i class="bi bi-person-badge me-1" aria-hidden="true"></i>{{ $r->doctorName }}
                <span class="mx-1" aria-hidden="true">·</span>
                <i class="bi bi-geo-alt me-1" aria-hidden="true"></i>{{ $r->branch }}@if ($r->room), consultorio {{ $r->room }}@endif
            </div>
            @if ($r->patientName && auth()->user()->can('reservas.gestionar'))
                <div class="small"><i class="bi bi-person me-1" aria-hidden="true"></i>{{ $r->patientName }}</div>
            @endif
        </div>
        @if ($actions)
            <div class="d-flex gap-2 flex-wrap">
                <a href="{{ route('reservations.show', $r->id) }}" class="btn btn-sm btn-outline-primary" aria-label="Ver cita {{ $r->code }}">
                    <i class="bi bi-eye" aria-hidden="true"></i> Ver
                </a>
                @can('reschedule', $r)
                    <a href="{{ route('reservations.reschedule.edit', $r->id) }}" class="btn btn-sm btn-outline-secondary" aria-label="Reprogramar cita {{ $r->code }}">
                        <i class="bi bi-arrow-repeat" aria-hidden="true"></i> Reprogramar
                    </a>
                @endcan
                @can('cancel', $r)
                    @if (! ($cancelInline ?? false))
                        <a href="{{ route('reservations.show', $r->id) }}#cancelar" class="btn btn-sm btn-outline-danger" aria-label="Cancelar cita {{ $r->code }}">
                            <i class="bi bi-x-circle" aria-hidden="true"></i> Cancelar
                        </a>
                    @else
                    <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#cancelModal"
                        data-action="{{ route('reservations.cancel', $r->id) }}" data-version="{{ $r->version }}" data-code="{{ $r->code }}"
                        aria-label="Cancelar cita {{ $r->code }}">
                        <i class="bi bi-x-circle" aria-hidden="true"></i> Cancelar
                    </button>
                    @endif
                @endcan
            </div>
        @endif
    </div>
</li>
