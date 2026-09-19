@use('App\Support\LocalTime')
@php
    $current = \Carbon\CarbonImmutable::parse($date);
    $doctorName = collect($doctors)->firstWhere('id', $doctorId)['name'] ?? null;
    $canBlock = auth()->user()->can('agenda.bloquear');
    $free = collect($slots)->filter->isFree()->count();
    $booked = collect($slots)->whereNotNull('reservationId')->count();
    $blocked = collect($slots)->where('blocked', true)->count();
@endphp
<x-app-layout title="Disponibilidad del día" :subtitle="ucfirst(LocalTime::longDate($date)).($doctorName ? ' · '.$doctorName : '')">
    <x-slot:actions>
        @can('agenda.gestionar')
            <x-button :href="route('admin.schedules.index')" variant="outline-primary" icon="bi-calendar3">Agendas</x-button>
        @endcan
    </x-slot:actions>

    <x-card class="mb-3">
        <form method="GET" action="{{ route('admin.schedules.day') }}" class="row g-2 align-items-end">
            <div class="col-12 col-md-5">
                <label for="doctor_id" class="form-label">Médico</label>
                <select id="doctor_id" name="doctor_id" class="form-select" data-autosubmit required>
                    <option value="">Selecciona un médico</option>
                    @foreach ($doctors as $d)
                        <option value="{{ $d['id'] }}" @selected($doctorId === $d['id'])>{{ $d['name'] }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-12 col-md-5">
                <label for="date" class="form-label">Fecha</label>
                <div class="input-group">
                    <a class="btn btn-outline-secondary" href="{{ route('admin.schedules.day', ['doctor_id' => $doctorId, 'date' => $current->subDay()->toDateString()]) }}" aria-label="Día anterior"><i class="bi bi-chevron-left" aria-hidden="true"></i></a>
                    <input type="date" id="date" name="date" value="{{ $date }}" class="form-control" data-autosubmit>
                    <a class="btn btn-outline-secondary" href="{{ route('admin.schedules.day', ['doctor_id' => $doctorId, 'date' => $current->addDay()->toDateString()]) }}" aria-label="Día siguiente"><i class="bi bi-chevron-right" aria-hidden="true"></i></a>
                </div>
            </div>
            <div class="col-12 col-md-2"><button class="btn btn-primary w-100">Ver</button></div>
        </form>
    </x-card>

    @if (! $doctorId)
        <x-empty-state icon="bi-person-badge" title="Selecciona un médico" message="Verás cada horario del día con su estado: libre, reservado o bloqueado." />
    @elseif (count($slots) === 0)
        <x-empty-state icon="bi-calendar-x" title="El médico no tiene agenda ese día">
            @can('agenda.gestionar')
                <x-button :href="route('admin.schedules.create')" icon="bi-calendar-plus" variant="outline-primary">Crear agenda</x-button>
            @endcan
        </x-empty-state>
    @else
        <div class="row g-3 mb-3">
            <div class="col-4"><x-stat-card label="Libres" :value="$free" icon="bi-circle" tone="success" /></div>
            <div class="col-4"><x-stat-card label="Reservados" :value="$booked" icon="bi-person-check" tone="blue" /></div>
            <div class="col-4"><x-stat-card label="Bloqueados" :value="$blocked" icon="bi-slash-circle" tone="warning" /></div>
        </div>

        <div class="row g-3">
            <div @class(['col-12', 'col-xl-8' => $canBlock])>
                <x-card title="Horarios" icon="bi-clock" flush>
                    <x-table caption="Horarios del día" :headers="['Hora', 'Estado', 'Detalle', 'Lugar', '>Acciones']">
                        @foreach ($slots as $s)
                            <tr>
                                <td class="fw-semibold text-nowrap">{{ LocalTime::time($s->start) }}–{{ LocalTime::time($s->end) }}</td>
                                <td>
                                    @if ($s->blocked)
                                        <x-badge variant="warning" icon="bi-slash-circle">Bloqueado</x-badge>
                                    @elseif ($s->reservationId)
                                        <x-reservation-status :code="$s->statusCode" :name="$s->statusName" />
                                    @else
                                        <x-badge variant="success" icon="bi-circle">Libre</x-badge>
                                    @endif
                                </td>
                                <td class="small">
                                    @if ($s->blocked)
                                        {{ \App\Http\Requests\BlockRequest::TYPES[$s->blockType] ?? $s->blockType }}@if ($s->blockReason): {{ $s->blockReason }}@endif
                                    @elseif ($s->reservationId)
                                        <span class="code-pill">{{ $s->reservationCode }}</span> {{ $s->patientName }}
                                    @else
                                        <span class="text-secondary">{{ $s->specialty }} · {{ $s->careType }}</span>
                                    @endif
                                </td>
                                <td class="small">{{ $s->branch }}<div class="text-secondary">{{ $s->room }}</div></td>
                                <td class="text-end text-nowrap">
                                    @if ($s->reservationId)
                                        <a href="{{ route('reservations.show', $s->reservationId) }}" class="btn btn-sm btn-outline-primary" aria-label="Ver reserva {{ $s->reservationCode }}">Ver</a>
                                    @elseif ($s->blocked && $s->blockId && $canBlock)
                                        <form method="POST" action="{{ route('admin.schedules.unblock', $s->blockId) }}" data-confirm="¿Levantar este bloqueo? Los horarios volverán a estar disponibles.">
                                            @csrf
                                            <button class="btn btn-sm btn-outline-success"><i class="bi bi-unlock" aria-hidden="true"></i> Levantar</button>
                                        </form>
                                    @elseif ($s->isFree() && $canBlock)
                                        <button type="button" class="btn btn-sm btn-outline-warning" data-bs-toggle="modal" data-bs-target="#blockSlotModal"
                                            data-slot-id="{{ $s->slotId }}" data-slot-label="{{ LocalTime::time($s->start) }}–{{ LocalTime::time($s->end) }}">
                                            <i class="bi bi-slash-circle" aria-hidden="true"></i> Bloquear
                                        </button>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </x-table>
                </x-card>
            </div>

            @if ($canBlock)
                <div class="col-12 col-xl-4">
                    <x-card title="Bloquear un rango" icon="bi-slash-circle">
                        <p class="small text-secondary">Bloquea todos los horarios libres del rango. Los horarios con reserva confirmada no se bloquean.</p>
                        <form method="POST" action="{{ route('admin.schedules.block') }}" data-submit-once>
                            @csrf
                            <input type="hidden" name="doctor_id" value="{{ $doctorId }}">
                            <input type="hidden" name="date" value="{{ $date }}">
                            <div class="row gx-2">
                                <div class="col-6"><x-input name="start" label="Desde" type="time" step="300" required /></div>
                                <div class="col-6"><x-input name="end" label="Hasta" type="time" step="300" required /></div>
                            </div>
                            <x-select name="type" label="Tipo" :options="array_diff_key($blockTypes, ['SLOT' => true])" required />
                            <x-input name="reason" label="Motivo" maxlength="250" required />
                            <x-button variant="warning" icon="bi-slash-circle" class="w-100" loading="Bloqueando…">Bloquear rango</x-button>
                        </form>
                    </x-card>
                </div>
            @endif
        </div>

        @if ($canBlock)
            @push('modals')
                <x-modal id="blockSlotModal" title="Bloquear horario">
                    <form method="POST" action="{{ route('admin.schedules.block') }}" id="blockSlotForm" data-submit-once>
                        @csrf
                        <input type="hidden" name="doctor_id" value="{{ $doctorId }}">
                        <input type="hidden" name="slot_id" value="" data-slot-input>
                        <input type="hidden" name="type" value="SLOT">
                        <p>Horario: <strong data-slot-label></strong></p>
                        <x-input name="reason" label="Motivo" maxlength="250" required id="block-slot-reason" />
                    </form>
                    <x-slot:footer>
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" form="blockSlotForm" class="btn btn-warning"><i class="bi bi-slash-circle me-1" aria-hidden="true"></i>Bloquear</button>
                    </x-slot:footer>
                </x-modal>
            @endpush
        @endif
    @endif
</x-app-layout>
