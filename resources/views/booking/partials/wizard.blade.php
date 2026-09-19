{{--
    Wizard compartido por "Reservar" y "Reprogramar".
    Variables: $mode ('book'|'reschedule'), $specialties, $branches, $action, $slotField, $searchDays,
               $fixedSpecialty (?array), $preselectedSpecialty, $preselectedDoctor, $patientName, $hidden (array name => value)
--}}
@php
    $steps = ['Especialidad', 'Médico y sede', 'Fecha', 'Horario', 'Confirmación'];
    $fixedSpecialty = $fixedSpecialty ?? null;
@endphp
<div id="booking-wizard"
    data-doctors-url="{{ route('booking.doctors') }}"
    data-dates-url="{{ route('booking.dates') }}"
    data-slots-url="{{ route('booking.slots') }}"
    data-mode="{{ $mode }}"
    @if ($fixedSpecialty) data-fixed-specialty="{{ $fixedSpecialty['id'] }}" data-fixed-specialty-name="{{ $fixedSpecialty['name'] }}" @endif
    @if ($preselectedSpecialty ?? null) data-preselected-specialty="{{ $preselectedSpecialty }}" @endif
    @if ($preselectedDoctor ?? null) data-preselected-doctor="{{ $preselectedDoctor }}" @endif>

    <ol class="wizard-steps" aria-label="Pasos de la reserva">
        @foreach ($steps as $i => $label)
            <li @if ($i === 0) aria-current="step" @endif @class(['done' => $fixedSpecialty && $i === 0])>
                <span class="step-num" aria-hidden="true">{{ $i + 1 }}</span>
                <span><span class="visually-hidden">Paso {{ $i + 1 }}: </span>{{ $label }}</span>
            </li>
        @endforeach
    </ol>
    <p class="visually-hidden" aria-live="polite" data-live></p>

    <noscript>
        <x-alert type="warning">Para reservar es necesario habilitar JavaScript en el navegador.</x-alert>
    </noscript>

    {{-- Paso 1 --}}
    <section class="card" data-step="1" aria-labelledby="step1-title">
        <div class="card-body">
            <h2 class="h5 mb-3" id="step1-title">1. Elige la especialidad</h2>
            @if ($fixedSpecialty)
                <p class="mb-0">La reprogramación se realiza en la misma especialidad: <strong>{{ $fixedSpecialty['name'] }}</strong>.</p>
            @else
                <div class="row g-3" data-choices role="group" aria-labelledby="step1-title">
                    @forelse ($specialties as $s)
                        <div class="col-12 col-sm-6 col-lg-4">
                            <button type="button" class="choice-card w-100 text-start" aria-pressed="false"
                                data-specialty-id="{{ $s['EspecialidadId'] }}" data-name="{{ $s['Nombre'] }}">
                                <span class="d-block fw-semibold"><i class="bi bi-heart-pulse text-primary me-2" aria-hidden="true"></i>{{ $s['Nombre'] }}</span>
                                @if (! empty($s['Descripcion']))
                                    <span class="d-block small text-secondary mt-1">{{ $s['Descripcion'] }}</span>
                                @endif
                                @isset($s['MedicosActivos'])
                                    <span class="d-block small text-secondary mt-1">{{ $s['MedicosActivos'] }} {{ (int) $s['MedicosActivos'] === 1 ? 'médico' : 'médicos' }}</span>
                                @endisset
                            </button>
                        </div>
                    @empty
                        <div class="col-12"><x-empty-state icon="bi-heart" title="No hay especialidades activas" /></div>
                    @endforelse
                </div>
            @endif
        </div>
    </section>

    {{-- Paso 2 --}}
    <section class="card" data-step="2" aria-labelledby="step2-title" hidden>
        <div class="card-body">
            <div class="d-flex flex-column flex-md-row gap-3 align-items-md-end mb-3">
                <h2 class="h5 mb-0 flex-grow-1" id="step2-title">2. Elige médico y sede</h2>
                <div style="min-width:14rem">
                    <label for="branch-filter" class="form-label small mb-1">Sede</label>
                    <select id="branch-filter" class="form-select form-select-sm" data-branch-select>
                        <option value="">Todas las sedes</option>
                        @foreach ($branches as $b)
                            <option value="{{ $b['SedeId'] }}">{{ $b['Nombre'] }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div data-doctor-list></div>
            @unless ($fixedSpecialty)
                <button type="button" class="btn btn-link px-0 mt-3" data-back><i class="bi bi-arrow-left me-1" aria-hidden="true"></i>Cambiar especialidad</button>
            @endunless
        </div>
    </section>

    {{-- Paso 3 --}}
    <section class="card" data-step="3" aria-labelledby="step3-title" hidden>
        <div class="card-body">
            <h2 class="h5 mb-1" id="step3-title">3. Elige la fecha</h2>
            <p class="small text-secondary mb-3">Se muestran los próximos {{ $searchDays }} días con horarios libres.</p>
            <div data-date-list></div>
            <button type="button" class="btn btn-link px-0 mt-3" data-back><i class="bi bi-arrow-left me-1" aria-hidden="true"></i>Volver a médicos</button>
        </div>
    </section>

    {{-- Paso 4 --}}
    <section class="card" data-step="4" aria-labelledby="step4-title" hidden>
        <div class="card-body">
            <h2 class="h5 mb-2" id="step4-title">4. Elige el horario</h2>
            <ul class="list-inline small text-secondary mb-3" aria-label="Leyenda de estados">
                <li class="list-inline-item"><span class="legend-dot bg-success" aria-hidden="true"></span> Disponible</li>
                <li class="list-inline-item"><span class="legend-dot bg-warning" aria-hidden="true"></span> Bloqueado</li>
                <li class="list-inline-item"><span class="legend-dot bg-secondary" aria-hidden="true"></span> No disponible</li>
                <li class="list-inline-item"><span class="legend-dot bg-primary" aria-hidden="true"></span> Seleccionado</li>
            </ul>
            <div data-slot-list></div>
            <button type="button" class="btn btn-link px-0 mt-1" data-back><i class="bi bi-arrow-left me-1" aria-hidden="true"></i>Volver a fechas</button>
        </div>
    </section>

    {{-- Paso 5 --}}
    <section class="card" data-step="5" aria-labelledby="step5-title" hidden>
        <div class="card-body">
            <h2 class="h5 mb-3" id="step5-title">5. Confirma {{ $mode === 'reschedule' ? 'la reprogramación' : 'tu reserva' }}</h2>
            <dl class="row detail-list mb-3">
                <dt class="col-sm-4">Paciente</dt><dd class="col-sm-8">{{ $patientName }}</dd>
                <dt class="col-sm-4">Especialidad</dt><dd class="col-sm-8" data-summary="specialty">—</dd>
                <dt class="col-sm-4">Médico</dt><dd class="col-sm-8" data-summary="doctor">—</dd>
                <dt class="col-sm-4">Sede</dt><dd class="col-sm-8" data-summary="branch">—</dd>
                <dt class="col-sm-4">Consultorio</dt><dd class="col-sm-8" data-summary="room">—</dd>
                <dt class="col-sm-4">Fecha</dt><dd class="col-sm-8" data-summary="date">—</dd>
                <dt class="col-sm-4">Hora</dt><dd class="col-sm-8" data-summary="time">—</dd>
                <dt class="col-sm-4">Tipo de atención</dt><dd class="col-sm-8" data-summary="careType">—</dd>
            </dl>

            <form method="POST" action="{{ $action }}" data-booking-form data-submit-once>
                @csrf
                <input type="hidden" name="{{ $slotField }}" value="" data-slot-input>
                @foreach ($hidden as $name => $value)
                    <input type="hidden" name="{{ $name }}" value="{{ $value }}">
                @endforeach
                @if ($mode === 'reschedule')
                    <div class="mb-3">
                        <label for="reason" class="form-label">Motivo de la reprogramación (opcional)</label>
                        <textarea id="reason" name="reason" class="form-control" rows="2" maxlength="250">{{ old('reason') }}</textarea>
                    </div>
                @else
                    <div class="mb-3">
                        <label for="notes" class="form-label">Observación para el médico (opcional)</label>
                        <textarea id="notes" name="notes" class="form-control" rows="2" maxlength="500" aria-describedby="notes-help">{{ old('notes') }}</textarea>
                        <div id="notes-help" class="form-text">No incluyas diagnósticos ni información clínica sensible.</div>
                    </div>
                @endif
                <x-alert type="info" class="small">El horario se valida de nuevo al confirmar. Si otra persona lo tomó un instante antes, te lo indicaremos para que elijas otro.</x-alert>
                <div class="d-flex flex-wrap gap-2">
                    <button type="button" class="btn btn-outline-secondary" data-back><i class="bi bi-arrow-left me-1" aria-hidden="true"></i>Cambiar horario</button>
                    <button type="submit" class="btn btn-primary" disabled data-loading-text="Confirmando…">
                        <i class="bi bi-check2-circle me-1" aria-hidden="true"></i>{{ $mode === 'reschedule' ? 'Confirmar reprogramación' : 'Confirmar reserva' }}
                    </button>
                </div>
            </form>
        </div>
    </section>
</div>
