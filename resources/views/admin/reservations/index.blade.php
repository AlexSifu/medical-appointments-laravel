@use('App\Services\CatalogService')
<x-app-layout title="Reservas" subtitle="Búsqueda y seguimiento de todas las reservas.">
    <x-slot:actions>
        <x-button :href="request()->fullUrlWithQuery(['export' => 'csv', 'page' => null])" variant="outline-primary" icon="bi-filetype-csv">Exportar CSV</x-button>
        @can('reservas.crear')
            <x-button :href="route('booking.create')" icon="bi-calendar-plus">Nueva reserva</x-button>
        @endcan
    </x-slot:actions>

    <x-card class="mb-3">
        <form method="GET" action="{{ route('admin.reservations.index') }}" class="row g-2 align-items-end" aria-label="Filtros de reservas">
            <div class="col-12 col-lg-3">
                <label for="q" class="form-label">Paciente, documento o código</label>
                <input type="search" id="q" name="q" value="{{ $filters['q'] ?? '' }}" class="form-control" maxlength="100">
            </div>
            <div class="col-6 col-lg-2">
                <label for="date_from" class="form-label">Desde</label>
                <input type="date" id="date_from" name="date_from" value="{{ $filters['date_from'] ?? '' }}" class="form-control">
            </div>
            <div class="col-6 col-lg-2">
                <label for="date_to" class="form-label">Hasta</label>
                <input type="date" id="date_to" name="date_to" value="{{ $filters['date_to'] ?? '' }}" class="form-control">
            </div>
            <div class="col-6 col-lg-2">
                <label for="status_id" class="form-label">Estado</label>
                <select id="status_id" name="status_id" class="form-select">
                    <option value="">Todos</option>
                    @foreach (CatalogService::options($statuses, 'EstadoReservaId') as $id => $name)
                        <option value="{{ $id }}" @selected((string) ($filters['status_id'] ?? '') === (string) $id)>{{ $name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-lg-3">
                <label for="specialty_id" class="form-label">Especialidad</label>
                <select id="specialty_id" name="specialty_id" class="form-select">
                    <option value="">Todas</option>
                    @foreach (CatalogService::options($specialties, 'EspecialidadId') as $id => $name)
                        <option value="{{ $id }}" @selected((string) ($filters['specialty_id'] ?? '') === (string) $id)>{{ $name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-lg-3">
                <label for="branch_id" class="form-label">Sede</label>
                <select id="branch_id" name="branch_id" class="form-select">
                    <option value="">Todas</option>
                    @foreach (CatalogService::options($branches, 'SedeId') as $id => $name)
                        <option value="{{ $id }}" @selected((string) ($filters['branch_id'] ?? '') === (string) $id)>{{ $name }}</option>
                    @endforeach
                </select>
            </div>
            @foreach (['doctor_id', 'patient_id'] as $hidden)
                @if (! empty($filters[$hidden]))
                    <input type="hidden" name="{{ $hidden }}" value="{{ $filters[$hidden] }}">
                @endif
            @endforeach
            <div class="col-6 col-lg-3 d-flex gap-2">
                <button class="btn btn-primary flex-grow-1"><i class="bi bi-search me-1" aria-hidden="true"></i>Buscar</button>
                <a href="{{ route('admin.reservations.index') }}" class="btn btn-outline-secondary" aria-label="Limpiar filtros"><i class="bi bi-x-lg" aria-hidden="true"></i></a>
            </div>
        </form>
    </x-card>

    <x-card flush>
        @if ($page->isEmpty())
            <x-empty-state icon="bi-search" title="No hay reservas con esos criterios" message="Ajusta los filtros o el rango de fechas." />
        @else
            @include('partials.reservation-table', ['items' => $page->items])
        @endif
    </x-card>
    <x-pagination :result="$page" />

    @include('partials.cancel-modal', ['reasons' => app(\App\Services\ReservationService::class)->cancellationReasons(auth()->user())])
</x-app-layout>
