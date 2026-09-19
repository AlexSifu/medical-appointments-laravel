@use('App\Models\Role')
@php
    $assignable = collect($roles)
        ->filter(fn ($r) => (bool) ($r['Activo'] ?? true))
        ->filter(fn ($r) => $r['Codigo'] !== Role::SUPERADMIN || auth()->user()->hasRole(Role::SUPERADMIN))
        ->mapWithKeys(fn ($r) => [$r['Codigo'] => Role::label($r['Codigo'])])
        ->all();
@endphp
<x-app-layout title="Nuevo usuario" subtitle="La contraseña se guarda con hash; nunca se muestra ni se registra.">
    <x-slot:breadcrumb>
        <a href="{{ route('admin.users.index') }}" class="small text-decoration-none"><i class="bi bi-arrow-left me-1" aria-hidden="true"></i>Usuarios</a>
    </x-slot:breadcrumb>

    <form method="POST" action="{{ route('admin.users.store') }}" data-submit-once novalidate autocomplete="off">
        @csrf
        <div class="row g-3">
            <div class="col-12 col-xl-7">
                <x-card title="Cuenta" icon="bi-person-gear">
                    <div class="row gx-3">
                        <div class="col-12 col-md-6"><x-input name="username" label="Usuario" maxlength="50" required autocomplete="off" help="Letras, números, punto, guion y guion bajo." /></div>
                        <div class="col-12 col-md-6"><x-select name="role" label="Rol inicial" :options="$assignable" placeholder="Selecciona" required /></div>
                        <div class="col-12 col-md-6"><x-input name="first_names" label="Nombres" maxlength="100" required /></div>
                        <div class="col-12 col-md-6"><x-input name="last_names" label="Apellidos" maxlength="100" required /></div>
                        <div class="col-12"><x-input name="email" label="Correo" type="email" maxlength="150" /></div>
                        <div class="col-12 col-md-6"><x-input name="doctor_id" label="Id de médico vinculado" type="number" min="1" help="Solo para cuentas con rol Médico." /></div>
                        <div class="col-12 col-md-6"><x-input name="patient_id" label="Id de paciente vinculado" type="number" min="1" help="Solo para cuentas con rol Paciente." /></div>
                    </div>
                </x-card>
            </div>
            <div class="col-12 col-xl-5">
                <x-card title="Contraseña inicial" icon="bi-key">
                    <x-input name="password" label="Contraseña" type="password" required autocomplete="new-password"
                        help="Mínimo 10 caracteres, con mayúsculas, minúsculas y números." />
                    <x-input name="password_confirmation" label="Confirmar contraseña" type="password" required autocomplete="new-password" />
                    <x-button icon="bi-person-check" class="w-100" loading="Creando…">Crear usuario</x-button>
                </x-card>
            </div>
        </div>
    </form>
</x-app-layout>
