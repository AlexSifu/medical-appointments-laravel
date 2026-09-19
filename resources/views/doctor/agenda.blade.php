@use('App\Support\LocalTime')
@php
    $byWeek = collect($agendas)->groupBy(fn ($a) => \Carbon\CarbonImmutable::parse($a->date)->startOfWeek()->toDateString());
@endphp
<x-app-layout title="Mi agenda" subtitle="Turnos publicados del {{ LocalTime::date($from) }} al {{ LocalTime::date($to) }}.">
    <x-slot:actions>
        <x-button :href="route('doctor.day')" icon="bi-clipboard2-pulse">Hoy</x-button>
    </x-slot:actions>

    <x-card class="mb-3">
        <form method="GET" action="{{ route('doctor.agenda') }}" class="row g-2 align-items-end">
            <div class="col-6 col-md-4"><x-input name="date_from" label="Desde" type="date" :value="$from" /></div>
            <div class="col-6 col-md-4"><x-input name="date_to" label="Hasta" type="date" :value="$to" /></div>
            <div class="col-12 col-md-4 mb-3"><button class="btn btn-primary w-100"><i class="bi bi-funnel me-1" aria-hidden="true"></i>Aplicar</button></div>
        </form>
    </x-card>

    @forelse ($byWeek as $week => $items)
        <x-card title="Semana del {{ LocalTime::date($week, 'j \d\e F') }}" icon="bi-calendar-week" flush class="mb-3">
            <x-table caption="Turnos de la semana" :headers="['Fecha', 'Horario', 'Especialidad', 'Sede · Consultorio', '>Reservados', '>Libres', '>Bloqueados', '>Acciones']">
                @foreach ($items as $a)
                    <tr @class(['table-light text-secondary' => ! $a->active])>
                        <td class="text-nowrap">{{ LocalTime::date($a->date) }}</td>
                        <td class="text-nowrap">{{ LocalTime::time($a->start) }} – {{ LocalTime::time($a->end) }}<div class="small text-secondary">{{ $a->durationMinutes }} min</div></td>
                        <td>{{ $a->specialty }}</td>
                        <td>{{ $a->branch }} · {{ $a->room }}</td>
                        <td class="text-end fw-semibold">{{ $a->bookedSlots }}</td>
                        <td class="text-end">{{ $a->freeSlots }}</td>
                        <td class="text-end">{{ $a->blockedSlots }}</td>
                        <td class="text-end"><a href="{{ route('doctor.day', ['date' => $a->date]) }}" class="btn btn-sm btn-outline-primary" aria-label="Ver citas del {{ LocalTime::date($a->date) }}">Ver día</a></td>
                    </tr>
                @endforeach
            </x-table>
        </x-card>
    @empty
        <x-card><x-empty-state icon="bi-calendar-x" title="No hay turnos publicados en este rango" message="La administración genera las agendas. Consulta con recepción si falta algún turno." /></x-card>
    @endforelse
</x-app-layout>
