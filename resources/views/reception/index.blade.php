@use('App\Support\LocalTime')
@use('App\Services\CatalogService')
@php
    $current = \Carbon\CarbonImmutable::parse($date);
@endphp
<x-app-layout title="Citas del día" subtitle="{{ ucfirst(LocalTime::longDate($date)) }}">
    <x-slot:actions>
        @can('reservas.crear')
            <x-button :href="route('booking.create')" icon="bi-calendar-plus">Nueva reserva</x-button>
        @endcan
        @can('agenda.gestionar')
            <x-button :href="route('admin.schedules.day', ['date' => $date])" variant="outline-primary" icon="bi-grid-3x3-gap">Disponibilidad</x-button>
        @endcan
    </x-slot:actions>

    <x-card class="mb-3">
        <form method="GET" action="{{ route('reception.index') }}" class="row g-2 align-items-end" aria-label="Filtros">
            <div class="col-12 col-md-3">
                <label for="date" class="form-label">Fecha</label>
                <div class="input-group">
                    <a class="btn btn-outline-secondary" href="{{ route('reception.index', ['date' => $current->subDay()->toDateString()]) }}" aria-label="Día anterior"><i class="bi bi-chevron-left" aria-hidden="true"></i></a>
                    <input type="date" id="date" name="date" value="{{ $date }}" class="form-control" data-autosubmit>
                    <a class="btn btn-outline-secondary" href="{{ route('reception.index', ['date' => $current->addDay()->toDateString()]) }}" aria-label="Día siguiente"><i class="bi bi-chevron-right" aria-hidden="true"></i></a>
                </div>
            </div>
            <div class="col-12 col-md-3">
                <label for="q" class="form-label">Paciente o código</label>
                <input type="search" id="q" name="q" value="{{ $filters['q'] ?? '' }}" class="form-control" maxlength="100">
            </div>
            <div class="col-6 col-md-2">
                <label for="specialty_id" class="form-label">Especialidad</label>
                <select id="specialty_id" name="specialty_id" class="form-select" data-autosubmit>
                    <option value="">Todas</option>
                    @foreach (CatalogService::options($specialties, 'EspecialidadId') as $id => $name)
                        <option value="{{ $id }}" @selected((string) ($filters['specialty_id'] ?? '') === (string) $id)>{{ $name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label for="status_id" class="form-label">Estado</label>
                <select id="status_id" name="status_id" class="form-select" data-autosubmit>
                    <option value="">Todos</option>
                    @foreach (CatalogService::options($statuses, 'EstadoReservaId') as $id => $name)
                        <option value="{{ $id }}" @selected((string) ($filters['status_id'] ?? '') === (string) $id)>{{ $name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-12 col-md-2 d-flex gap-2">
                <button class="btn btn-primary flex-grow-1"><i class="bi bi-search me-1" aria-hidden="true"></i>Filtrar</button>
                @if (count($filters) > 0)
                    <a href="{{ route('reception.index') }}" class="btn btn-outline-secondary" aria-label="Limpiar filtros"><i class="bi bi-x-lg" aria-hidden="true"></i></a>
                @endif
            </div>
            @if (! empty($filters['branch_id']))
                <input type="hidden" name="branch_id" value="{{ $filters['branch_id'] }}">
            @endif
        </form>
    </x-card>

    <x-card flush>
        @if ($page->isEmpty())
            <x-empty-state icon="bi-calendar-x" title="No hay citas con esos criterios" />
        @else
            @include('partials.reservation-table', ['items' => $page->items, 'showDate' => false])
        @endif
    </x-card>
    <x-pagination :result="$page" />

    @include('partials.cancel-modal', ['reasons' => $reasons])
</x-app-layout>
