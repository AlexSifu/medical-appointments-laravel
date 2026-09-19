@use('App\Support\LocalTime')
@php
    $current = \Carbon\CarbonImmutable::parse($date);
    $weekStart = $current->startOfWeek();
    $isToday = LocalTime::isToday($date);
@endphp
<x-app-layout title="{{ $isToday ? 'Atención de hoy' : 'Agenda del día' }}" subtitle="{{ ucfirst(LocalTime::longDate($date)) }}">
    <x-slot:actions>
        <x-button :href="route('doctor.agenda')" variant="outline-primary" icon="bi-calendar3">Mi agenda</x-button>
    </x-slot:actions>

    {{-- Semana --}}
    <nav aria-label="Semana" class="mb-3">
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <a class="btn btn-sm btn-outline-secondary" href="{{ route('doctor.day', ['date' => $current->subWeek()->toDateString()]) }}" aria-label="Semana anterior"><i class="bi bi-chevron-double-left" aria-hidden="true"></i></a>
            @for ($i = 0; $i < 7; $i++)
                @php $d = $weekStart->addDays($i); $sel = $d->toDateString() === $date; @endphp
                <a href="{{ route('doctor.day', ['date' => $d->toDateString()]) }}" @class(['date-chip text-decoration-none', 'active' => $sel]) @if ($sel) aria-current="date" @endif>
                    <span class="fw-semibold">{{ LocalTime::date($d->toDateString(), 'D j') }}</span>
                    @if (LocalTime::isToday($d->toDateString()))<small>Hoy</small>@endif
                </a>
            @endfor
            <a class="btn btn-sm btn-outline-secondary" href="{{ route('doctor.day', ['date' => $current->addWeek()->toDateString()]) }}" aria-label="Semana siguiente"><i class="bi bi-chevron-double-right" aria-hidden="true"></i></a>
            @unless ($isToday)
                <a class="btn btn-sm btn-link" href="{{ route('doctor.day') }}">Ir a hoy</a>
            @endunless
        </div>
    </nav>

    <div class="row g-3 mb-3">
        <div class="col-4"><x-stat-card label="Pendientes" :value="$kpis['pending']" icon="bi-hourglass-split" tone="warning" /></div>
        <div class="col-4"><x-stat-card label="Atendidas" :value="$kpis['attended']" icon="bi-check2-circle" tone="success" /></div>
        <div class="col-4"><x-stat-card label="No asistió" :value="$kpis['no_show']" icon="bi-person-x" tone="danger" /></div>
    </div>

    <x-card title="Mis citas" icon="bi-people" flush>
        @if ($reservations)
            <x-table caption="Citas del día" :headers="['Hora', 'Paciente', 'Especialidad', 'Consultorio', 'Estado', '>Acciones']">
                @foreach ($reservations as $r)
                    <tr>
                        <td class="fw-semibold text-nowrap">{{ LocalTime::time($r->start) }}<div class="small text-secondary fw-normal">{{ LocalTime::time($r->end) }}</div></td>
                        <td>
                            {{ $r->patientName }}
                            <div class="small text-secondary">{{ $r->patientDocument }}</div>
                            @if ($r->notes)<div class="small text-pre-wrap"><i class="bi bi-chat-left-text me-1" aria-hidden="true"></i>{{ $r->notes }}</div>@endif
                        </td>
                        <td>{{ $r->specialty }}<div class="small text-secondary">{{ $r->careTypeName() }}</div></td>
                        <td>{{ $r->branch }} · {{ $r->room }}</td>
                        <td><x-reservation-status :code="$r->statusCode" :name="$r->statusName" /></td>
                        <td class="text-end">
                            <div class="d-inline-flex gap-1 flex-wrap justify-content-end">
                                @can('close', $r)
                                    <form method="POST" action="{{ route('reservations.attended', $r->id) }}" data-confirm="¿Marcar como atendida la cita de {{ $r->patientName }}?">
                                        @csrf
                                        <input type="hidden" name="version" value="{{ $r->version }}">
                                        <button class="btn btn-sm btn-success" aria-label="Marcar atendida: {{ $r->patientName }}"><i class="bi bi-check2" aria-hidden="true"></i> Atendida</button>
                                    </form>
                                    <form method="POST" action="{{ route('reservations.no-show', $r->id) }}" data-confirm="¿Registrar que {{ $r->patientName }} no asistió?" data-confirm-variant="danger">
                                        @csrf
                                        <input type="hidden" name="version" value="{{ $r->version }}">
                                        <button class="btn btn-sm btn-outline-danger" aria-label="Marcar no asistió: {{ $r->patientName }}"><i class="bi bi-person-x" aria-hidden="true"></i> No asistió</button>
                                    </form>
                                @endcan
                                <a class="btn btn-sm btn-outline-primary" href="{{ route('reservations.show', $r->id) }}" aria-label="Ver cita {{ $r->code }}"><i class="bi bi-eye" aria-hidden="true"></i></a>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </x-table>
        @else
            <x-empty-state icon="bi-calendar-x" title="No hay citas para este día" />
        @endif
    </x-card>
</x-app-layout>
