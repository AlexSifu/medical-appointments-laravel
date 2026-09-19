@use('App\Services\CatalogService')
<x-app-layout title="Médicos" subtitle="Alta, edición, especialidades y sedes.">
    <x-slot:actions>
        @can('medicos.crear')
            <x-button :href="route('admin.doctors.create')" icon="bi-person-plus">Nuevo médico</x-button>
        @endcan
    </x-slot:actions>

    <x-card class="mb-3">
        <form method="GET" action="{{ route('admin.doctors.index') }}" class="row g-2 align-items-end" role="search">
            <div class="col-12 col-md-4">
                <label for="q" class="form-label">Nombre o CMP</label>
                <input type="search" id="q" name="q" value="{{ $filters['q'] ?? '' }}" class="form-control" maxlength="100">
            </div>
            <div class="col-6 col-md-3">
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
            <div class="col-6 col-md-1">
                <label for="active" class="form-label">Estado</label>
                <select id="active" name="active" class="form-select" data-autosubmit>
                    <option value="">Todos</option>
                    <option value="1" @selected(($filters['active'] ?? '') === '1')>Activos</option>
                    <option value="0" @selected(($filters['active'] ?? '') === '0')>Inactivos</option>
                </select>
            </div>
            <div class="col-6 col-md-2">
                <button class="btn btn-primary w-100"><i class="bi bi-search me-1" aria-hidden="true"></i>Buscar</button>
            </div>
        </form>
    </x-card>

    <x-card flush>
        @if ($page->isEmpty())
            <x-empty-state icon="bi-person-x" title="No se encontraron médicos" />
        @else
            <x-table caption="Médicos" :headers="['Médico', 'CMP', 'Especialidades', 'Sedes', 'Estado', '>Acciones']">
                @foreach ($page->items as $d)
                    <tr>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <span class="avatar" aria-hidden="true">{{ $d->initials() }}</span>
                                <div class="min-w-0"><div class="fw-semibold">{{ $d->fullName }}</div><div class="small text-secondary text-truncate">{{ $d->email }}</div></div>
                            </div>
                        </td>
                        <td class="text-nowrap">{{ $d->cmp }}</td>
                        <td class="small">{{ implode(', ', $d->specialties) ?: '—' }}</td>
                        <td class="small">{{ implode(', ', $d->branches) ?: '—' }}</td>
                        <td><x-badge :variant="$d->active ? 'success' : 'secondary'">{{ $d->active ? 'Activo' : 'Inactivo' }}</x-badge></td>
                        <td class="text-end text-nowrap">
                            @can('medicos.editar')
                                <a href="{{ route('admin.doctors.edit', $d->id) }}" class="btn btn-sm btn-outline-primary" aria-label="Editar a {{ $d->fullName }}"><i class="bi bi-pencil" aria-hidden="true"></i> Editar</a>
                            @endcan
                            @can('agenda.gestionar')
                                <a href="{{ route('admin.schedules.index', ['doctor_id' => $d->id]) }}" class="btn btn-sm btn-outline-secondary" aria-label="Agendas de {{ $d->fullName }}"><i class="bi bi-calendar3" aria-hidden="true"></i> Agendas</a>
                            @endcan
                        </td>
                    </tr>
                @endforeach
            </x-table>
        @endif
    </x-card>
    <x-pagination :result="$page" />
</x-app-layout>
