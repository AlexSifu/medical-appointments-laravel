<x-app-layout title="Médicos" subtitle="Encuentra al especialista y revisa su próxima disponibilidad.">
    <x-card class="mb-3">
        <form method="GET" action="{{ route('doctors.directory') }}" class="row g-2 align-items-end" role="search" aria-label="Filtrar médicos">
            <div class="col-12 col-md-4">
                <label for="q" class="form-label">Nombre o CMP</label>
                <input type="search" id="q" name="q" value="{{ $filters['q'] ?? '' }}" class="form-control" maxlength="100">
            </div>
            <div class="col-6 col-md-3">
                <label for="specialty_id" class="form-label">Especialidad</label>
                <select id="specialty_id" name="specialty_id" class="form-select" data-autosubmit>
                    <option value="">Todas</option>
                    @foreach ($specialties as $s)
                        <option value="{{ $s['EspecialidadId'] }}" @selected((string) ($filters['specialty_id'] ?? '') === (string) $s['EspecialidadId'])>{{ $s['Nombre'] }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-3">
                <label for="branch_id" class="form-label">Sede</label>
                <select id="branch_id" name="branch_id" class="form-select" data-autosubmit>
                    <option value="">Todas</option>
                    @foreach ($branches as $b)
                        <option value="{{ $b['SedeId'] }}" @selected((string) ($filters['branch_id'] ?? '') === (string) $b['SedeId'])>{{ $b['Nombre'] }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-12 col-md-2 d-flex gap-2">
                <button class="btn btn-primary flex-grow-1"><i class="bi bi-search me-1" aria-hidden="true"></i>Buscar</button>
                @if ($filters)
                    <a href="{{ route('doctors.directory') }}" class="btn btn-outline-secondary" aria-label="Limpiar filtros"><i class="bi bi-x-lg" aria-hidden="true"></i></a>
                @endif
            </div>
        </form>
    </x-card>

    @if ($page->isEmpty())
        <x-card><x-empty-state icon="bi-person-x" title="No encontramos médicos con esos filtros" message="Prueba con otra especialidad o sede." /></x-card>
    @else
        <div class="row g-3">
            @foreach ($page->items as $doctor)
                <div class="col-12 col-md-6 col-xl-4">
                    <x-doctor-card :doctor="$doctor" :bookable="auth()->user()->can('reservas.crear')" />
                </div>
            @endforeach
        </div>
        <x-pagination :result="$page" />
    @endif
</x-app-layout>
