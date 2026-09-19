@props(['title' => null, 'subtitle' => null])
@php
    /** @var \App\Models\User $user */
    $user = auth()->user();
    $roleLabel = \App\Models\Role::LABELS[$user->data->primaryRole()] ?? 'Usuario';

    // Navegación según permisos efectivos (los SP vuelven a validar cada acción).
    $sections = [
        'General' => [
            ['route' => 'dashboard', 'match' => 'dashboard', 'icon' => 'bi-house-door', 'label' => 'Inicio', 'show' => true],
        ],
        'Mis citas' => [
            ['route' => 'booking.create', 'match' => 'booking.*', 'icon' => 'bi-calendar-plus', 'label' => 'Reservar cita', 'show' => $user->isPatient()],
            ['route' => 'patient.appointments', 'match' => 'patient.appointments', 'icon' => 'bi-calendar2-week', 'label' => 'Mis citas', 'show' => $user->can('reservas.propias') && $user->data->patientId !== null],
            ['route' => 'doctors.directory', 'match' => 'doctors.directory', 'icon' => 'bi-person-badge', 'label' => 'Médicos', 'show' => $user->can('medicos.ver') && $user->isPatient()],
            ['route' => 'patient.profile', 'match' => 'patient.profile*', 'icon' => 'bi-person-circle', 'label' => 'Mi perfil', 'show' => $user->can('reservas.propias') && $user->data->patientId !== null],
        ],
        'Médico' => [
            ['route' => 'doctor.day', 'match' => 'doctor.day', 'icon' => 'bi-clipboard2-pulse', 'label' => 'Hoy', 'show' => $user->isDoctor()],
            ['route' => 'doctor.agenda', 'match' => 'doctor.agenda', 'icon' => 'bi-calendar3', 'label' => 'Mi agenda', 'show' => $user->isDoctor()],
        ],
        'Recepción' => [
            ['route' => 'reception.index', 'match' => 'reception.index', 'icon' => 'bi-display', 'label' => 'Citas del día', 'show' => $user->can('reservas.gestionar')],
            ['route' => 'reception.patients.index', 'match' => 'reception.patients.*', 'icon' => 'bi-people', 'label' => 'Pacientes', 'show' => $user->can('pacientes.ver')],
            ['route' => 'booking.create', 'match' => 'booking.*', 'icon' => 'bi-calendar-plus', 'label' => 'Nueva reserva', 'show' => ! $user->isPatient() && $user->can('reservas.crear')],
        ],
        'Administración' => [
            ['route' => 'admin.reservations.index', 'match' => 'admin.reservations.*', 'icon' => 'bi-journal-medical', 'label' => 'Reservas', 'show' => $user->can('reservas.gestionar')],
            ['route' => 'admin.doctors.index', 'match' => 'admin.doctors.*', 'icon' => 'bi-person-badge', 'label' => 'Médicos', 'show' => ! $user->isPatient() && $user->can('medicos.ver')],
            ['route' => 'admin.schedules.index', 'match' => 'admin.schedules.index|admin.schedules.create|admin.schedules.day', 'icon' => 'bi-calendar-range', 'label' => 'Agendas', 'show' => $user->can('agenda.gestionar')],
            ['route' => 'admin.schedules.blocks', 'match' => 'admin.schedules.blocks', 'icon' => 'bi-slash-circle', 'label' => 'Bloqueos', 'show' => $user->can('agenda.bloquear')],
            ['route' => 'admin.users.index', 'match' => 'admin.users.*', 'icon' => 'bi-person-gear', 'label' => 'Usuarios', 'show' => $user->can('usuarios.ver')],
            ['route' => 'admin.specialties.index', 'match' => 'admin.specialties.*', 'icon' => 'bi-heart-pulse', 'label' => 'Especialidades', 'show' => $user->can('especialidades.gestionar')],
            ['route' => 'admin.branches.index', 'match' => 'admin.branches.*', 'icon' => 'bi-building', 'label' => 'Sedes', 'show' => $user->can('sedes.gestionar')],
        ],
        'Control' => [
            ['route' => 'reports.index', 'match' => 'reports.*', 'icon' => 'bi-bar-chart-line', 'label' => 'Reportes', 'show' => $user->can('reportes.ver')],
            ['route' => 'audit.index', 'match' => 'audit.*', 'icon' => 'bi-shield-check', 'label' => 'Auditoría', 'show' => $user->can('auditoria.ver')],
        ],
    ];
    $seen = [];
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $title ? $title.' · ' : '' }}Nexa Salud</title>
    <link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3E%3Crect width='16' height='16' rx='4' fill='%230F2742'/%3E%3Cpath d='M7 4h2v3h3v2H9v3H7V9H4V7h3z' fill='%23fff'/%3E%3C/svg%3E">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="app-body">
<a class="skip-link" href="#main-content">Saltar al contenido principal</a>

<aside class="app-sidebar offcanvas-lg offcanvas-start" id="appSidebar" tabindex="-1" aria-labelledby="appSidebarLabel">
    <div class="offcanvas-header d-lg-none">
        <span class="brand-name" id="appSidebarLabel">Nexa Salud</span>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" data-bs-target="#appSidebar" aria-label="Cerrar menú"></button>
    </div>
    <div class="d-flex flex-column h-100">
        <a href="{{ route('dashboard') }}" class="brand d-none d-lg-flex align-items-center gap-2 text-decoration-none px-3 py-4">
            <span class="brand-mark" aria-hidden="true"><i class="bi bi-plus-lg"></i></span>
            <span>
                <span class="brand-name d-block">Nexa Salud</span>
                <span class="brand-sub d-block">Gestión de Citas Médicas</span>
            </span>
        </a>
        <nav aria-label="Navegación principal" class="flex-grow-1 overflow-auto px-2 pb-3">
            @foreach ($sections as $section => $items)
                @php
                    $visible = array_filter($items, function ($item) use (&$seen) {
                        if (! $item['show'] || in_array($item['route'], $seen, true)) {
                            return false;
                        }
                        $seen[] = $item['route'];

                        return true;
                    });
                @endphp
                @if ($visible)
                    <p class="nav-section">{{ $section }}</p>
                    <ul class="nav flex-column">
                        @foreach ($visible as $item)
                            @php $active = request()->routeIs(...explode('|', $item['match'])); @endphp
                            <li class="nav-item">
                                <a href="{{ route($item['route']) }}" class="nav-link @if ($active) active @endif" @if ($active) aria-current="page" @endif>
                                    <i class="bi {{ $item['icon'] }}" aria-hidden="true"></i>
                                    <span>{{ $item['label'] }}</span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                @endif
            @endforeach
        </nav>
        <div class="px-3 py-3 small sidebar-foot">
            <i class="bi bi-shield-lock me-1" aria-hidden="true"></i> Sesión segura
        </div>
    </div>
</aside>

<div class="app-main">
    <header class="app-topbar">
        <button class="btn btn-light d-lg-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#appSidebar" aria-controls="appSidebar" aria-label="Abrir menú">
            <i class="bi bi-list" aria-hidden="true"></i>
        </button>
        <span class="d-lg-none fw-semibold text-navy">Nexa Salud</span>
        <div class="ms-auto dropdown">
            <button class="btn btn-link text-decoration-none d-flex align-items-center gap-2 p-1" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                <span class="avatar" aria-hidden="true">{{ $user->data->initials() }}</span>
                <span class="text-start d-none d-sm-block lh-sm">
                    <span class="d-block fw-semibold text-body">{{ $user->name() }}</span>
                    <span class="d-block small text-secondary">{{ $roleLabel }}</span>
                </span>
                <i class="bi bi-chevron-down small text-secondary" aria-hidden="true"></i>
                <span class="visually-hidden">Menú de usuario</span>
            </button>
            <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                <li><span class="dropdown-item-text small text-secondary">Usuario: {{ $user->data->username }}</span></li>
                @if ($user->data->patientId !== null && $user->can('reservas.propias'))
                    <li><a class="dropdown-item" href="{{ route('patient.profile') }}"><i class="bi bi-person-circle me-2" aria-hidden="true"></i>Mi perfil</a></li>
                @endif
                <li><hr class="dropdown-divider"></li>
                <li>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="dropdown-item text-danger"><i class="bi bi-box-arrow-right me-2" aria-hidden="true"></i>Cerrar sesión</button>
                    </form>
                </li>
            </ul>
        </div>
    </header>

    <main id="main-content" class="app-content" tabindex="-1">
        @if ($title)
            <div class="page-header">
                <div>
                    @isset($breadcrumb)
                        <nav aria-label="Ruta de navegación">{{ $breadcrumb }}</nav>
                    @endisset
                    <h1 class="h3 mb-1">{{ $title }}</h1>
                    @if ($subtitle)
                        <p class="text-secondary mb-0">{{ $subtitle }}</p>
                    @endif
                </div>
                @isset($actions)
                    <div class="d-flex flex-wrap gap-2">{{ $actions }}</div>
                @endisset
            </div>
        @endif

        <div aria-live="polite">
            @if (session('success'))
                <x-alert type="success" dismissible>{{ session('success') }}</x-alert>
            @endif
            @if (session('status'))
                <x-alert type="info" dismissible>{{ session('status') }}</x-alert>
            @endif
        </div>
        <div aria-live="assertive">
            @if (session('error'))
                <x-alert type="danger" dismissible>
                    {{ session('error') }}
                    @if (session('error_code'))
                        <span class="d-block small opacity-75 mt-1">Código: {{ session('error_code') }}</span>
                    @endif
                </x-alert>
            @endif
            @if ($errors->any() && ! session('error'))
                <x-alert type="danger">
                    <strong>Revisa los datos ingresados:</strong>
                    <ul class="mb-0 mt-1">
                        @foreach ($errors->all() as $message)
                            <li>{{ $message }}</li>
                        @endforeach
                    </ul>
                </x-alert>
            @endif
        </div>

        {{ $slot }}
    </main>

    <footer class="app-footer small text-secondary">
        Nexa Salud · Gestión de Citas Médicas · Correlación <span class="font-monospace">{{ app(\App\Support\RequestContext::class)->correlationId() }}</span>
    </footer>
</div>

<x-modal id="confirmModal" title="Confirmar acción">
    <p class="mb-0" data-confirm-message></p>
    <x-slot:footer>
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Volver</button>
        <button type="button" class="btn btn-primary" data-confirm-accept>Confirmar</button>
    </x-slot:footer>
</x-modal>

@stack('modals')
</body>
</html>
