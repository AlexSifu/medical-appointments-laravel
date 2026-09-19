@use('App\Models\Role')
@use('App\Support\LocalTime')
@php
    $isSuper = auth()->user()->hasRole(Role::SUPERADMIN);
    $isSelf = auth()->id() === $account->id;
    $available = collect($roles)
        ->filter(fn ($r) => (bool) ($r['Activo'] ?? true) && ! in_array($r['Codigo'], $account->roles, true))
        ->filter(fn ($r) => $r['Codigo'] !== Role::SUPERADMIN || $isSuper)
        ->mapWithKeys(fn ($r) => [$r['Codigo'] => Role::label($r['Codigo'])])
        ->all();
    $locked = $account->lockedUntilUtc && \Carbon\CarbonImmutable::parse($account->lockedUntilUtc, 'UTC')->isFuture();
    $grouped = collect($permissions)->groupBy(fn ($p) => explode('.', $p)[0]);
@endphp
<x-app-layout :title="$account->fullName()" :subtitle="'@'.$account->username">
    <x-slot:breadcrumb>
        <a href="{{ route('admin.users.index') }}" class="small text-decoration-none"><i class="bi bi-arrow-left me-1" aria-hidden="true"></i>Usuarios</a>
    </x-slot:breadcrumb>
    <x-slot:actions>
        @if (! $account->active)
            <x-badge variant="secondary">Inactivo</x-badge>
        @elseif ($locked)
            <x-badge variant="warning" icon="bi-lock">Bloqueado temporalmente</x-badge>
        @else
            <x-badge variant="success">Activo</x-badge>
        @endif
    </x-slot:actions>

    <div class="row g-3">
        <div class="col-12 col-xl-7">
            <form method="POST" action="{{ route('admin.users.update', $account->id) }}" data-submit-once novalidate>
                @csrf
                @method('PUT')
                <input type="hidden" name="version" value="{{ $account->version }}">
                <x-card title="Datos de la cuenta" icon="bi-person-gear" class="mb-3">
                    <div class="row gx-3">
                        <div class="col-12 col-md-6"><x-input name="first_names" label="Nombres" :value="$account->firstNames" maxlength="100" required /></div>
                        <div class="col-12 col-md-6"><x-input name="last_names" label="Apellidos" :value="$account->lastNames" maxlength="100" required /></div>
                        <div class="col-12"><x-input name="email" label="Correo" type="email" :value="$account->email" maxlength="150" /></div>
                    </div>
                    <div class="form-check form-switch mb-2">
                        <input type="hidden" name="active" value="0">
                        <input class="form-check-input" type="checkbox" role="switch" id="active" name="active" value="1" @checked(old('active', $account->active)) @disabled($isSelf)>
                        <label class="form-check-label" for="active">Cuenta activa</label>
                        @if ($isSelf)
                            <input type="hidden" name="active" value="1">
                            <div class="form-text">No puedes desactivar tu propia cuenta.</div>
                        @endif
                    </div>
                    @can('usuarios.editar')
                        <x-slot:footer>
                            <x-button icon="bi-check2" loading="Guardando…">Guardar cambios</x-button>
                        </x-slot:footer>
                    @endcan
                </x-card>
            </form>

            <x-card title="Detalles" icon="bi-info-circle">
                <dl class="detail-list row mb-0">
                    <dt class="col-sm-5">Último acceso</dt><dd class="col-sm-7">{{ $account->lastAccessUtc ? LocalTime::fromUtc($account->lastAccessUtc) : 'Nunca' }}</dd>
                    <dt class="col-sm-5">Creada</dt><dd class="col-sm-7">{{ $account->createdAtUtc ? LocalTime::fromUtc($account->createdAtUtc) : '—' }}</dd>
                    <dt class="col-sm-5">Algoritmo de contraseña</dt><dd class="col-sm-7"><span class="code-pill">{{ $account->passwordAlgorithm }}</span></dd>
                    @if ($locked)
                        <dt class="col-sm-5">Bloqueada hasta</dt><dd class="col-sm-7">{{ LocalTime::fromUtc($account->lockedUntilUtc) }}</dd>
                    @endif
                    <dt class="col-sm-5">Médico vinculado</dt><dd class="col-sm-7">{{ $account->doctorId ? '#'.$account->doctorId : '—' }}</dd>
                    <dt class="col-sm-5">Paciente vinculado</dt>
                    <dd class="col-sm-7 mb-0">
                        @if ($account->patientId)
                            @can('pacientes.ver')<a href="{{ route('reception.patients.show', $account->patientId) }}">#{{ $account->patientId }}</a>@else #{{ $account->patientId }} @endcan
                        @else
                            —
                        @endif
                    </dd>
                </dl>
            </x-card>
        </div>

        <div class="col-12 col-xl-5">
            <x-card title="Roles" icon="bi-shield-lock" class="mb-3">
                <ul class="list-group list-group-flush mb-3">
                    @forelse ($account->roles as $code)
                        <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                            <span>{{ Role::label($code) }}</span>
                            @can('usuarios.roles')
                                @if (($code !== Role::SUPERADMIN || $isSuper) && ! ($isSelf && in_array($code, [Role::SUPERADMIN, Role::ADMIN], true)))
                                    <form method="POST" action="{{ route('admin.users.role', $account->id) }}" data-confirm="¿Quitar el rol {{ Role::label($code) }}?" data-confirm-variant="danger">
                                        @csrf
                                        <input type="hidden" name="role" value="{{ $code }}"><input type="hidden" name="assign" value="0">
                                        <button class="btn btn-sm btn-outline-danger" aria-label="Quitar rol {{ Role::label($code) }}"><i class="bi bi-x-lg" aria-hidden="true"></i></button>
                                    </form>
                                @endif
                            @endcan
                        </li>
                    @empty
                        <li class="list-group-item px-0 text-secondary">Sin roles. La cuenta no puede usar ningún módulo.</li>
                    @endforelse
                </ul>
                @can('usuarios.roles')
                    @if ($available)
                        <form method="POST" action="{{ route('admin.users.role', $account->id) }}" class="d-flex gap-2 align-items-end">
                            @csrf
                            <input type="hidden" name="assign" value="1">
                            <div class="flex-grow-1">
                                <label for="add-role" class="form-label small">Asignar rol</label>
                                <select id="add-role" name="role" class="form-select form-select-sm" required>
                                    @foreach ($available as $code => $label)<option value="{{ $code }}">{{ $label }}</option>@endforeach
                                </select>
                            </div>
                            <button class="btn btn-sm btn-primary"><i class="bi bi-plus-lg" aria-hidden="true"></i> Asignar</button>
                        </form>
                    @endif
                @endcan
            </x-card>

            @can('usuarios.editar')
                <x-card title="Restablecer contraseña" icon="bi-key" class="mb-3">
                    <form method="POST" action="{{ route('admin.users.password', $account->id) }}" data-submit-once
                        data-confirm="¿Restablecer la contraseña de {{ $account->username }}? Se desbloqueará la cuenta." autocomplete="off">
                        @csrf
                        <x-input name="password" label="Nueva contraseña" type="password" required autocomplete="new-password" id="reset-password"
                            help="Mínimo 10 caracteres, con mayúsculas, minúsculas y números." />
                        <x-input name="password_confirmation" label="Confirmar" type="password" required autocomplete="new-password" id="reset-password-confirmation" />
                        <x-button variant="outline-primary" icon="bi-arrow-repeat" loading="Guardando…">Restablecer</x-button>
                    </form>
                </x-card>
            @endcan

            <x-card title="Permisos efectivos" icon="bi-list-check">
                @forelse ($grouped as $module => $codes)
                    <h3 class="h6 text-secondary text-uppercase small mt-2">{{ $module }}</h3>
                    <div class="d-flex flex-wrap gap-1">
                        @foreach ($codes as $code)<span class="code-pill">{{ $code }}</span>@endforeach
                    </div>
                @empty
                    <p class="text-secondary mb-0">Sin permisos.</p>
                @endforelse
            </x-card>
        </div>
    </div>
</x-app-layout>
