@use('App\Support\LocalTime')
@php
    $r = $reservation;
    $staff = auth()->user()->can('reservas.gestionar');
    $reasonOptions = collect($reasons)
        ->filter(fn ($x) => $staff || ! (bool) ($x['SoloPersonal'] ?? false))
        ->mapWithKeys(fn ($x) => [$x['MotivoCancelacionId'] => $x['Nombre']])->all();
@endphp
<x-app-layout title="Cita {{ $r->code }}" subtitle="{{ $r->specialty }} · {{ ucfirst(LocalTime::longDate($r->date)) }}">
    <x-slot:actions>
        @can('reschedule', $r)
            <x-button :href="route('reservations.reschedule.edit', $r->id)" variant="outline-primary" icon="bi-arrow-repeat">Reprogramar</x-button>
        @endcan
        <button type="button" class="btn btn-outline-secondary d-inline-flex align-items-center gap-2" data-print><i class="bi bi-printer" aria-hidden="true"></i>Imprimir</button>
    </x-slot:actions>

    @if ($justConfirmed && $r->isConfirmed())
        <div class="card border-success mb-4" role="status">
            <div class="card-body d-flex gap-3 align-items-center">
                <span class="stat-icon tone-success d-inline-grid rounded-3 p-3 fs-3" aria-hidden="true"><i class="bi bi-check2-circle"></i></span>
                <div>
                    <h2 class="h5 mb-1 text-success">¡Cita confirmada!</h2>
                    <p class="mb-0">Código de reserva <span class="code-pill fs-6">{{ $r->code }}</span> · Estado: <x-reservation-status :code="$r->statusCode" :name="$r->statusName" /></p>
                    <p class="small text-secondary mb-0 mt-1">Guarda este código. Preséntate 15 minutos antes con tu documento de identidad.</p>
                </div>
            </div>
        </div>
    @endif

    <div class="row g-3">
        <div class="col-12 col-xl-8">
            <x-card title="Detalle de la cita" icon="bi-calendar2-check">
                <x-slot:actions>
                    <x-reservation-status :code="$r->statusCode" :name="$r->statusName" />
                </x-slot:actions>
                <dl class="row detail-list mb-0">
                    <dt class="col-sm-4">Código de reserva</dt><dd class="col-sm-8"><span class="code-pill">{{ $r->code }}</span></dd>
                    <dt class="col-sm-4">Paciente</dt>
                    <dd class="col-sm-8">
                        {{ $r->patientName }}
                        @if ($r->patientDocument)<span class="text-secondary"> · {{ $r->patientDocument }}</span>@endif
                        @if ($staff && $r->patientId)
                            <a href="{{ route('reception.patients.show', $r->patientId) }}" class="small ms-1">Ver ficha</a>
                        @endif
                    </dd>
                    <dt class="col-sm-4">Especialidad</dt><dd class="col-sm-8">{{ $r->specialty }}</dd>
                    <dt class="col-sm-4">Médico</dt><dd class="col-sm-8">{{ $r->doctorName }} <span class="text-secondary">· CMP {{ $r->cmp }}</span></dd>
                    <dt class="col-sm-4">Sede</dt><dd class="col-sm-8">{{ $r->branch }}@if ($r->branchAddress)<div class="small text-secondary">{{ $r->branchAddress }}</div>@endif</dd>
                    <dt class="col-sm-4">Consultorio</dt><dd class="col-sm-8">{{ $r->room ?? '—' }}</dd>
                    <dt class="col-sm-4">Fecha</dt><dd class="col-sm-8">{{ ucfirst(LocalTime::longDate($r->date)) }}</dd>
                    <dt class="col-sm-4">Hora</dt><dd class="col-sm-8">{{ LocalTime::time($r->start) }} – {{ LocalTime::time($r->end) }}</dd>
                    <dt class="col-sm-4">Tipo de atención</dt><dd class="col-sm-8">{{ $r->careTypeName() }}</dd>
                    @if ($r->notes)
                        <dt class="col-sm-4">Observación</dt><dd class="col-sm-8 text-pre-wrap">{{ $r->notes }}</dd>
                    @endif
                    @if ($r->cancellationReason)
                        <dt class="col-sm-4">Motivo de cancelación</dt>
                        <dd class="col-sm-8">{{ $r->cancellationReason }}@if ($r->cancellationNotes)<div class="small text-secondary text-pre-wrap">{{ $r->cancellationNotes }}</div>@endif
                            @if ($r->cancelledAtUtc)<div class="small text-secondary">{{ LocalTime::fromUtc($r->cancelledAtUtc) }}</div>@endif
                        </dd>
                    @endif
                    @if ($r->originCode)
                        <dt class="col-sm-4">Reprogramada desde</dt><dd class="col-sm-8"><span class="code-pill">{{ $r->originCode }}</span></dd>
                    @endif
                    @if ($r->replacementCode)
                        <dt class="col-sm-4">Reemplazada por</dt>
                        <dd class="col-sm-8">
                            @if ($r->replacementId)
                                <a href="{{ route('reservations.show', $r->replacementId) }}" class="code-pill text-decoration-none">{{ $r->replacementCode }}</a>
                            @else
                                <span class="code-pill">{{ $r->replacementCode }}</span>
                            @endif
                        </dd>
                    @endif
                    <dt class="col-sm-4">Registrada</dt><dd class="col-sm-8 mb-0">{{ LocalTime::fromUtc($r->createdAtUtc) }}@if ($r->createdBy) <span class="text-secondary">por {{ $r->createdBy }}</span>@endif</dd>
                </dl>
            </x-card>

            @can('close', $r)
                <x-card title="Registrar atención" icon="bi-clipboard2-check" class="mt-3">
                    <p class="small text-secondary">Marca el resultado de la cita. Esta acción no se puede deshacer.</p>
                    <div class="d-flex flex-wrap gap-2">
                        <form method="POST" action="{{ route('reservations.attended', $r->id) }}" data-confirm="¿Marcar la cita {{ $r->code }} como atendida?">
                            @csrf
                            <input type="hidden" name="version" value="{{ $r->version }}">
                            <button type="submit" class="btn btn-success"><i class="bi bi-check2-circle me-1" aria-hidden="true"></i>Atendida</button>
                        </form>
                        <form method="POST" action="{{ route('reservations.no-show', $r->id) }}" data-confirm="¿Registrar que el paciente no asistió a la cita {{ $r->code }}?" data-confirm-variant="danger">
                            @csrf
                            <input type="hidden" name="version" value="{{ $r->version }}">
                            <button type="submit" class="btn btn-outline-danger"><i class="bi bi-person-x me-1" aria-hidden="true"></i>No asistió</button>
                        </form>
                    </div>
                </x-card>
            @endcan

            @can('cancel', $r)
                <x-card title="Cancelar cita" icon="bi-x-circle" class="mt-3" id="cancelar">
                    <p class="small text-secondary">
                        @if ($r->minimumCancellationHours)Puedes cancelar hasta {{ $r->minimumCancellationHours }} {{ $r->minimumCancellationHours === 1 ? 'hora' : 'horas' }} antes de la cita. @endif El horario quedará libre para otros pacientes.
                    </p>
                    <form method="POST" action="{{ route('reservations.cancel', $r->id) }}" data-submit-once data-confirm="¿Confirmas la cancelación de la cita {{ $r->code }}?" data-confirm-variant="danger">
                        @csrf
                        <input type="hidden" name="version" value="{{ $r->version }}">
                        <div class="row g-2">
                            <div class="col-12 col-md-5">
                                <x-select name="reason_id" label="Motivo" :options="$reasonOptions" placeholder="Selecciona un motivo" required />
                            </div>
                            <div class="col-12 col-md-7">
                                <x-input name="notes" label="Comentario (opcional)" maxlength="500" />
                            </div>
                        </div>
                        <button type="submit" class="btn btn-danger" data-loading-text="Cancelando…"><i class="bi bi-x-circle me-1" aria-hidden="true"></i>Cancelar cita</button>
                    </form>
                </x-card>
            @endcan
        </div>

        <div class="col-12 col-xl-4">
            <x-card title="Historial de estados" icon="bi-clock-history">
                @if ($history)
                    <ol class="timeline list-unstyled mb-0">
                        @foreach ($history as $h)
                            <li class="mb-3">
                                <div class="d-flex flex-wrap gap-2 align-items-center">
                                    @if ($h['EstadoAnteriorCodigo'] ?? null)
                                        <x-reservation-status :code="$h['EstadoAnteriorCodigo']" :name="$h['EstadoAnterior']" />
                                        <i class="bi bi-arrow-right text-secondary" aria-label="a"></i>
                                    @endif
                                    <x-reservation-status :code="$h['EstadoNuevoCodigo']" :name="$h['EstadoNuevo']" />
                                </div>
                                <div class="small text-secondary mt-1">{{ LocalTime::fromUtc((string) $h['FechaUtc']) }} · {{ $h['ActorNombre'] ?? $h['Actor'] }}</div>
                                @if (! empty($h['Motivo']))
                                    <div class="small text-pre-wrap">{{ $h['Motivo'] }}</div>
                                @endif
                            </li>
                        @endforeach
                    </ol>
                @else
                    <p class="text-secondary mb-0">Sin movimientos registrados.</p>
                @endif
            </x-card>
        </div>
    </div>
</x-app-layout>
