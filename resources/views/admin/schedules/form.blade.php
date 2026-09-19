@use('App\Services\CatalogService')
@php
    $mode = old('mode', 'single');
    $weekdays = [1 => 'Lun', 2 => 'Mar', 3 => 'Mié', 4 => 'Jue', 5 => 'Vie', 6 => 'Sáb', 7 => 'Dom'];
    $oldWeekdays = array_map('intval', (array) old('weekdays', [1, 2, 3, 4, 5]));
    $today = now()->toDateString();
@endphp
<x-app-layout title="Nueva agenda" subtitle="Genera horarios para un día o para un rango de fechas.">
    <x-slot:breadcrumb>
        <a href="{{ route('admin.schedules.index') }}" class="small text-decoration-none"><i class="bi bi-arrow-left me-1" aria-hidden="true"></i>Agendas</a>
    </x-slot:breadcrumb>

    <form method="POST" action="{{ route('admin.schedules.store') }}" data-submit-once data-schedule-form
        data-doctors='@json($doctors)' novalidate>
        @csrf
        <div class="row g-3">
            <div class="col-12 col-xl-8">
                <x-card title="Médico y lugar" icon="bi-person-badge" class="mb-3">
                    <div class="row gx-3">
                        <div class="col-12">
                            <x-select name="doctor_id" label="Médico" :options="collect($doctors)->pluck('name', 'id')->all()" placeholder="Selecciona un médico" required data-doctor-select />
                        </div>
                        <div class="col-12 col-md-6">
                            <x-select name="specialty_id" label="Especialidad" :options="CatalogService::options($specialties, 'EspecialidadId')" placeholder="Selecciona" required data-filter-by="specialtyIds"
                                help="Solo las especialidades asignadas al médico." />
                        </div>
                        <div class="col-12 col-md-6">
                            <x-select name="care_type_id" label="Tipo de atención" :options="CatalogService::options($careTypes, 'TipoAtencionId')" required />
                        </div>
                        <div class="col-12 col-md-6">
                            <x-select name="branch_id" label="Sede" :options="CatalogService::options($branches, 'SedeId')" placeholder="Selecciona" required data-filter-by="branchIds" data-branch-select />
                        </div>
                        <div class="col-12 col-md-6">
                            @php $currentRoom = (string) old('room_id'); @endphp
                            <div class="mb-3">
                                <label for="f-room_id" class="form-label">Consultorio<span class="text-danger" aria-hidden="true"> *</span></label>
                                <select id="f-room_id" name="room_id" required aria-required="true" data-room-select
                                    @class(['form-select', 'is-invalid' => $errors->has('room_id')]) @error('room_id') aria-invalid="true" aria-describedby="f-room_id-error" @enderror>
                                    <option value="">Selecciona</option>
                                    @foreach ($rooms as $room)
                                        <option value="{{ $room['ConsultorioId'] }}" data-branch="{{ $room['SedeId'] }}" @selected($currentRoom === (string) $room['ConsultorioId'])>
                                            {{ $room['Nombre'] }} ({{ $room['Codigo'] }}{{ isset($room['Piso']) ? ' · piso '.$room['Piso'] : '' }})
                                        </option>
                                    @endforeach
                                </select>
                                @error('room_id')<div id="f-room_id-error" class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            </div>
                        </div>
                    </div>
                </x-card>

                <x-card title="Fechas y horario" icon="bi-clock">
                    <fieldset class="mb-3">
                        <legend class="form-label fs-6">Modo</legend>
                        <div class="btn-group" role="group">
                            <input type="radio" class="btn-check" name="mode" id="mode-single" value="single" @checked($mode === 'single') data-mode>
                            <label class="btn btn-outline-primary" for="mode-single"><i class="bi bi-calendar-day me-1" aria-hidden="true"></i>Un día</label>
                            <input type="radio" class="btn-check" name="mode" id="mode-range" value="range" @checked($mode === 'range') data-mode>
                            <label class="btn btn-outline-primary" for="mode-range"><i class="bi bi-calendar-range me-1" aria-hidden="true"></i>Rango de fechas</label>
                        </div>
                    </fieldset>

                    <div class="row gx-3" data-mode-panel="single" @if ($mode !== 'single') hidden @endif>
                        <div class="col-12 col-md-4"><x-input name="date" label="Fecha" type="date" :min="$today" required /></div>
                    </div>
                    <div data-mode-panel="range" @if ($mode !== 'range') hidden @endif>
                        <div class="row gx-3">
                            <div class="col-6 col-md-4"><x-input name="date_from" label="Desde" type="date" :min="$today" required /></div>
                            <div class="col-6 col-md-4"><x-input name="date_to" label="Hasta" type="date" :min="$today" required help="Máximo según la configuración del sistema." /></div>
                        </div>
                        <fieldset class="mb-3">
                            <legend class="form-label fs-6">Días de la semana<span class="text-danger" aria-hidden="true"> *</span></legend>
                            <div class="d-flex flex-wrap gap-2">
                                @foreach ($weekdays as $n => $label)
                                    <input type="checkbox" class="btn-check" name="weekdays[]" id="wd-{{ $n }}" value="{{ $n }}" @checked(in_array($n, $oldWeekdays, true))>
                                    <label class="btn btn-sm btn-outline-secondary" for="wd-{{ $n }}">{{ $label }}</label>
                                @endforeach
                            </div>
                            @error('weekdays')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </fieldset>
                    </div>

                    <div class="row gx-3">
                        <div class="col-6 col-md-4"><x-input name="start" label="Hora inicio" type="time" value="08:00" step="300" required /></div>
                        <div class="col-6 col-md-4"><x-input name="end" label="Hora fin" type="time" value="13:00" step="300" required /></div>
                        <div class="col-12 col-md-4">
                            <x-select name="duration" label="Duración de cada cita" :options="[10 => '10 min', 15 => '15 min', 20 => '20 min', 30 => '30 min', 40 => '40 min', 45 => '45 min', 60 => '60 min']" value="30" required />
                        </div>
                    </div>
                </x-card>
            </div>

            <div class="col-12 col-xl-4">
                <x-card title="Antes de publicar" icon="bi-info-circle">
                    <ul class="small text-secondary ps-3 mb-3">
                        <li>SQL Server valida que el médico tenga la especialidad y la sede asignadas.</li>
                        <li>No se permiten cruces con otras agendas del médico ni del consultorio.</li>
                        <li>Los horarios se generan automáticamente según la duración elegida.</li>
                        <li>En modo rango se omiten los días sin atención y los feriados configurados.</li>
                    </ul>
                    <x-button icon="bi-calendar-check" class="w-100" loading="Generando…">Publicar agenda</x-button>
                </x-card>
            </div>
        </div>
    </form>
</x-app-layout>
