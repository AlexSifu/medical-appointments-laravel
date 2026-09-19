<x-app-layout title="Panel de administración" subtitle="Resumen operativo de citas, agendas y ocupación.">
    <x-slot:actions>
        @can('agenda.gestionar')
            <x-button :href="route('admin.schedules.create')" icon="bi-calendar-range">Crear agenda</x-button>
        @endcan
        @can('reservas.gestionar')
            <x-button :href="route('admin.reservations.index')" variant="outline-primary" icon="bi-journal-medical">Reservas</x-button>
        @endcan
    </x-slot:actions>

    @include('dashboard.partials.kpis', ['extended' => true])

    <div class="row g-3">
        <div class="col-12 col-xl-7">
            @include('dashboard.partials.series')
        </div>
        <div class="col-12 col-xl-5">
            <x-card title="Reservas por especialidad (±30 días)" icon="bi-heart-pulse" flush class="h-100">
                @if ($data->specialties)
                    <x-table caption="Reservas por especialidad" :headers="['Especialidad', '>Total', '>Atendidas', '>Canceladas']" class="table-sm">
                        @foreach ($data->specialties as $s)
                            <tr>
                                <td>{{ $s['Especialidad'] }}</td>
                                <td class="text-end fw-semibold">{{ $s['Total'] }}</td>
                                <td class="text-end">{{ $s['Atendidas'] }}</td>
                                <td class="text-end">{{ $s['Canceladas'] }}</td>
                            </tr>
                        @endforeach
                    </x-table>
                @else
                    <x-empty-state icon="bi-bar-chart" title="Sin datos por especialidad" />
                @endif
            </x-card>
        </div>
    </div>

    <div class="row g-3 mt-1">
        @can('medicos.ver')
            <div class="col-6 col-md-3"><a class="card card-body text-decoration-none h-100" href="{{ route('admin.doctors.index') }}"><i class="bi bi-person-badge fs-4 text-primary" aria-hidden="true"></i><span class="fw-semibold mt-2">Médicos</span></a></div>
        @endcan
        @can('agenda.bloquear')
            <div class="col-6 col-md-3"><a class="card card-body text-decoration-none h-100" href="{{ route('admin.schedules.blocks') }}"><i class="bi bi-slash-circle fs-4 text-warning" aria-hidden="true"></i><span class="fw-semibold mt-2">Bloqueos</span></a></div>
        @endcan
        @can('usuarios.ver')
            <div class="col-6 col-md-3"><a class="card card-body text-decoration-none h-100" href="{{ route('admin.users.index') }}"><i class="bi bi-person-gear fs-4 text-teal" aria-hidden="true"></i><span class="fw-semibold mt-2">Usuarios</span></a></div>
        @endcan
        @can('reportes.ver')
            <div class="col-6 col-md-3"><a class="card card-body text-decoration-none h-100" href="{{ route('reports.index') }}"><i class="bi bi-bar-chart-line fs-4 text-info" aria-hidden="true"></i><span class="fw-semibold mt-2">Reportes</span></a></div>
        @endcan
    </div>
</x-app-layout>
