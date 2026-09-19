@use('App\Models\Role')
@use('App\Support\LocalTime')
<x-app-layout title="Usuarios" subtitle="Cuentas de acceso, roles y estado.">
    <x-slot:actions>
        @can('usuarios.crear')
            <x-button :href="route('admin.users.create')" icon="bi-person-plus">Nuevo usuario</x-button>
        @endcan
    </x-slot:actions>

    <x-card class="mb-3">
        <form method="GET" action="{{ route('admin.users.index') }}" class="row g-2 align-items-end" role="search">
            <div class="col-12 col-md-5">
                <label for="q" class="form-label">Usuario, nombre o correo</label>
                <input type="search" id="q" name="q" value="{{ $filters['q'] ?? '' }}" class="form-control" maxlength="100">
            </div>
            <div class="col-6 col-md-3">
                <label for="role" class="form-label">Rol</label>
                <select id="role" name="role" class="form-select" data-autosubmit>
                    <option value="">Todos</option>
                    @foreach ($roles as $r)
                        <option value="{{ $r['Codigo'] }}" @selected(($filters['role'] ?? '') === $r['Codigo'])>{{ Role::label($r['Codigo']) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label for="active" class="form-label">Estado</label>
                <select id="active" name="active" class="form-select" data-autosubmit>
                    <option value="">Todos</option>
                    <option value="1" @selected(($filters['active'] ?? '') === '1')>Activos</option>
                    <option value="0" @selected(($filters['active'] ?? '') === '0')>Inactivos</option>
                </select>
            </div>
            <div class="col-12 col-md-2">
                <button class="btn btn-primary w-100"><i class="bi bi-search me-1" aria-hidden="true"></i>Buscar</button>
            </div>
        </form>
    </x-card>

    <x-card flush>
        @if ($page->isEmpty())
            <x-empty-state icon="bi-people" title="No se encontraron usuarios" />
        @else
            <x-table caption="Usuarios" :headers="['Usuario', 'Nombre', 'Roles', 'Último acceso', 'Estado', '>Acciones']">
                @foreach ($page->items as $u)
                    <tr>
                        <td class="fw-semibold">{{ $u->username }}</td>
                        <td>{{ $u->fullName() }}<div class="small text-secondary">{{ $u->email }}</div></td>
                        <td>
                            @foreach ($u->roles as $code)
                                <x-badge variant="light" class="border">{{ Role::label($code) }}</x-badge>
                            @endforeach
                        </td>
                        <td class="small text-nowrap">{{ $u->lastAccessUtc ? LocalTime::fromUtc($u->lastAccessUtc) : 'Nunca' }}</td>
                        <td>
                            @if (! $u->active)
                                <x-badge variant="secondary">Inactivo</x-badge>
                            @elseif ($u->lockedUntilUtc && \Carbon\CarbonImmutable::parse($u->lockedUntilUtc, 'UTC')->isFuture())
                                <x-badge variant="warning" icon="bi-lock">Bloqueado</x-badge>
                            @else
                                <x-badge variant="success">Activo</x-badge>
                            @endif
                        </td>
                        <td class="text-end">
                            <a href="{{ route('admin.users.show', $u->id) }}" class="btn btn-sm btn-outline-primary" aria-label="Ver usuario {{ $u->username }}">Ver</a>
                        </td>
                    </tr>
                @endforeach
            </x-table>
        @endif
    </x-card>
    <x-pagination :result="$page" />
</x-app-layout>
