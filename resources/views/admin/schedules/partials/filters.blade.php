{{-- Filtros compartidos de agendas y bloqueos. $route, $filters, $doctors, $specialties, $branches --}}
@use('App\Services\CatalogService')
<x-card class="mb-3">
    <form method="GET" action="{{ route($route) }}" class="row g-2 align-items-end" aria-label="Filtros">
        <div class="col-12 col-md-3">
            <label for="doctor_id" class="form-label">Médico</label>
            <select id="doctor_id" name="doctor_id" class="form-select" data-autosubmit>
                <option value="">Todos</option>
                @foreach ($doctors as $d)
                    <option value="{{ $d['id'] }}" @selected((string) ($filters['doctor_id'] ?? '') === (string) $d['id'])>{{ $d['name'] }}</option>
                @endforeach
            </select>
        </div>
        @isset($specialties)
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
                <label for="branch_id" class="form-label">Sede</label>
                <select id="branch_id" name="branch_id" class="form-select" data-autosubmit>
                    <option value="">Todas</option>
                    @foreach (CatalogService::options($branches, 'SedeId') as $id => $name)
                        <option value="{{ $id }}" @selected((string) ($filters['branch_id'] ?? '') === (string) $id)>{{ $name }}</option>
                    @endforeach
                </select>
            </div>
        @endisset
        <div class="col-6 col-md-2">
            <label for="date_from" class="form-label">Desde</label>
            <input type="date" id="date_from" name="date_from" value="{{ $filters['date_from'] ?? '' }}" class="form-control">
        </div>
        <div class="col-6 col-md-2">
            <label for="date_to" class="form-label">Hasta</label>
            <input type="date" id="date_to" name="date_to" value="{{ $filters['date_to'] ?? '' }}" class="form-control">
        </div>
        {{ $slot ?? '' }}
        <div class="col-12 col-md-1">
            <button class="btn btn-primary w-100" aria-label="Filtrar"><i class="bi bi-funnel" aria-hidden="true"></i></button>
        </div>
    </form>
</x-card>
