<x-app-layout title="Pacientes" subtitle="Busca por documento, nombre, teléfono o correo.">
    <x-slot:actions>
        @can('pacientes.crear')
            <x-button :href="route('reception.patients.create')" icon="bi-person-plus">Registrar paciente</x-button>
        @endcan
    </x-slot:actions>

    <x-card class="mb-3">
        <form method="GET" action="{{ route('reception.patients.index') }}" class="row g-2 align-items-end" role="search">
            <div class="col-12 col-md-6">
                <label for="q" class="form-label">Buscar</label>
                <input type="search" id="q" name="q" value="{{ $filters['q'] ?? '' }}" class="form-control" maxlength="100" autofocus>
            </div>
            <div class="col-6 col-md-3">
                <label for="active" class="form-label">Estado</label>
                <select id="active" name="active" class="form-select" data-autosubmit>
                    <option value="">Todos</option>
                    <option value="1" @selected(($filters['active'] ?? '') === '1')>Activos</option>
                    <option value="0" @selected(($filters['active'] ?? '') === '0')>Inactivos</option>
                </select>
            </div>
            <div class="col-6 col-md-3">
                <button class="btn btn-primary w-100"><i class="bi bi-search me-1" aria-hidden="true"></i>Buscar</button>
            </div>
        </form>
    </x-card>

    <x-card flush>
        @if ($page->isEmpty())
            <x-empty-state icon="bi-person-x" title="No se encontraron pacientes" message="Verifica el documento o registra al paciente.">
                @can('pacientes.crear')
                    <x-button :href="route('reception.patients.create')" icon="bi-person-plus" variant="outline-primary">Registrar paciente</x-button>
                @endcan
            </x-empty-state>
        @else
            <x-table caption="Pacientes" :headers="['Paciente', 'Documento', 'Contacto', 'Citas activas', 'Estado', '>Acciones']">
                @foreach ($page->items as $p)
                    <tr>
                        <td class="fw-semibold">{{ $p->fullName }}</td>
                        <td class="text-nowrap">{{ $p->document() }}</td>
                        <td class="small">{{ $p->phone ?? '—' }}<div class="text-secondary">{{ $p->email }}</div></td>
                        <td>{{ $p->confirmedReservations ?? 0 }}</td>
                        <td><x-badge :variant="$p->active ? 'success' : 'secondary'">{{ $p->active ? 'Activo' : 'Inactivo' }}</x-badge></td>
                        <td class="text-end text-nowrap">
                            <a href="{{ route('reception.patients.show', $p->id) }}" class="btn btn-sm btn-outline-primary" aria-label="Ver ficha de {{ $p->fullName }}">Ver</a>
                            @if ($p->active)
                                @can('reservas.crear')
                                    <a href="{{ route('booking.create', ['patient_id' => $p->id]) }}" class="btn btn-sm btn-primary" aria-label="Reservar para {{ $p->fullName }}"><i class="bi bi-calendar-plus" aria-hidden="true"></i> Reservar</a>
                                @endcan
                            @endif
                        </td>
                    </tr>
                @endforeach
            </x-table>
        @endif
    </x-card>
    <x-pagination :result="$page" />
</x-app-layout>
