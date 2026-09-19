@use('App\Services\CatalogService')
@php
    $editing = $doctor !== null;
    $specialtyOptions = CatalogService::options($specialties, 'EspecialidadId');
    $branchOptions = CatalogService::options($branches, 'SedeId');
@endphp
<x-app-layout :title="$editing ? $doctor->fullName : 'Nuevo médico'" :subtitle="$editing ? 'CMP '.$doctor->cmp : 'Registra al médico con su especialidad y sede principales.'">
    <x-slot:breadcrumb>
        <a href="{{ route('admin.doctors.index') }}" class="small text-decoration-none"><i class="bi bi-arrow-left me-1" aria-hidden="true"></i>Médicos</a>
    </x-slot:breadcrumb>
    @if ($editing)
        <x-slot:actions>
            <x-badge :variant="$doctor->active ? 'success' : 'secondary'">{{ $doctor->active ? 'Activo' : 'Inactivo' }}</x-badge>
        </x-slot:actions>
    @endif

    <div class="row g-3">
        <div class="col-12 col-xl-7">
            <form method="POST" action="{{ $editing ? route('admin.doctors.update', $doctor->id) : route('admin.doctors.store') }}" data-submit-once novalidate>
                @csrf
                @if ($editing)
                    @method('PUT')
                    <input type="hidden" name="version" value="{{ $doctor->version }}">
                @endif
                <x-card title="Datos del médico" icon="bi-person-badge">
                    <div class="row gx-3">
                        <div class="col-12 col-md-4"><x-input name="cmp" label="CMP" :value="$doctor?->cmp" maxlength="20" required help="Mayúsculas, números y guion." /></div>
                        <div class="col-12 col-md-8"></div>
                        <div class="col-12 col-md-6"><x-input name="first_names" label="Nombres" :value="$doctor?->firstNames" maxlength="100" required /></div>
                        <div class="col-12 col-md-6"><x-input name="last_names" label="Apellidos" :value="$doctor?->lastNames" maxlength="100" required /></div>
                        <div class="col-12 col-md-6"><x-input name="phone" label="Teléfono" type="tel" :value="$doctor?->phone" maxlength="20" /></div>
                        <div class="col-12 col-md-6"><x-input name="email" label="Correo" type="email" :value="$doctor?->email" maxlength="150" /></div>
                        @unless ($editing)
                            <div class="col-12 col-md-6"><x-select name="specialty_id" label="Especialidad principal" :options="$specialtyOptions" placeholder="Selecciona" required /></div>
                            <div class="col-12 col-md-6"><x-select name="branch_id" label="Sede" :options="$branchOptions" placeholder="Selecciona" required /></div>
                        @endunless
                    </div>
                    <x-slot:footer>
                        <x-button icon="bi-check2" loading="Guardando…">{{ $editing ? 'Guardar cambios' : 'Registrar médico' }}</x-button>
                    </x-slot:footer>
                </x-card>
            </form>

            @if ($editing)
                <x-card title="Estado" icon="bi-toggle-on" class="mt-3">
                    <p class="text-secondary small">
                        Desactivar al médico impide nuevas reservas con él. Las citas ya confirmadas no se cancelan automáticamente.
                    </p>
                    <form method="POST" action="{{ route('admin.doctors.status', $doctor->id) }}"
                        data-confirm="{{ $doctor->active ? '¿Desactivar a '.$doctor->fullName.'?' : '¿Activar a '.$doctor->fullName.'?' }}"
                        @if ($doctor->active) data-confirm-variant="danger" @endif>
                        @csrf
                        <input type="hidden" name="version" value="{{ $doctor->version }}">
                        <input type="hidden" name="active" value="{{ $doctor->active ? 0 : 1 }}">
                        <x-button :variant="$doctor->active ? 'outline-danger' : 'success'" :icon="$doctor->active ? 'bi-slash-circle' : 'bi-check-circle'">
                            {{ $doctor->active ? 'Desactivar médico' : 'Activar médico' }}
                        </x-button>
                    </form>
                </x-card>
            @endif
        </div>

        @if ($editing)
            <div class="col-12 col-xl-5">
                <x-card title="Especialidades" icon="bi-heart-pulse" class="mb-3">
                    <ul class="list-group list-group-flush mb-3">
                        @forelse ($doctor->specialtyIds as $sid)
                            <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                                <span>
                                    {{ $specialtyOptions[$sid] ?? 'Especialidad inactiva #'.$sid }}
                                    @if ($sid === $doctor->mainSpecialtyId)<x-badge variant="primary" class="ms-1">Principal</x-badge>@endif
                                </span>
                                <span class="d-flex gap-1">
                                    @if ($sid !== $doctor->mainSpecialtyId)
                                        <form method="POST" action="{{ route('admin.doctors.assign', $doctor->id) }}">
                                            @csrf
                                            <input type="hidden" name="kind" value="specialty"><input type="hidden" name="target_id" value="{{ $sid }}">
                                            <input type="hidden" name="assign" value="1"><input type="hidden" name="main" value="1">
                                            <button class="btn btn-sm btn-outline-primary">Hacer principal</button>
                                        </form>
                                    @endif
                                    <form method="POST" action="{{ route('admin.doctors.assign', $doctor->id) }}" data-confirm="¿Quitar esta especialidad?" data-confirm-variant="danger">
                                        @csrf
                                        <input type="hidden" name="kind" value="specialty"><input type="hidden" name="target_id" value="{{ $sid }}">
                                        <input type="hidden" name="assign" value="0">
                                        <button class="btn btn-sm btn-outline-danger" aria-label="Quitar especialidad"><i class="bi bi-x-lg" aria-hidden="true"></i></button>
                                    </form>
                                </span>
                            </li>
                        @empty
                            <li class="list-group-item px-0 text-secondary">Sin especialidades asignadas.</li>
                        @endforelse
                    </ul>
                    @php $availableSpecialties = array_diff_key($specialtyOptions, array_flip($doctor->specialtyIds)); @endphp
                    @if ($availableSpecialties)
                        <form method="POST" action="{{ route('admin.doctors.assign', $doctor->id) }}" class="d-flex gap-2 align-items-end">
                            @csrf
                            <input type="hidden" name="kind" value="specialty"><input type="hidden" name="assign" value="1">
                            <div class="flex-grow-1">
                                <label for="add-specialty" class="form-label small">Agregar especialidad</label>
                                <select id="add-specialty" name="target_id" class="form-select form-select-sm" required>
                                    @foreach ($availableSpecialties as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach
                                </select>
                            </div>
                            <button class="btn btn-sm btn-primary"><i class="bi bi-plus-lg" aria-hidden="true"></i> Agregar</button>
                        </form>
                    @endif
                </x-card>

                <x-card title="Sedes" icon="bi-building">
                    <ul class="list-group list-group-flush mb-3">
                        @forelse ($doctor->branchIds as $bid)
                            <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                                <span>{{ $branchOptions[$bid] ?? 'Sede inactiva #'.$bid }}</span>
                                <form method="POST" action="{{ route('admin.doctors.assign', $doctor->id) }}" data-confirm="¿Quitar esta sede?" data-confirm-variant="danger">
                                    @csrf
                                    <input type="hidden" name="kind" value="branch"><input type="hidden" name="target_id" value="{{ $bid }}">
                                    <input type="hidden" name="assign" value="0">
                                    <button class="btn btn-sm btn-outline-danger" aria-label="Quitar sede"><i class="bi bi-x-lg" aria-hidden="true"></i></button>
                                </form>
                            </li>
                        @empty
                            <li class="list-group-item px-0 text-secondary">Sin sedes asignadas.</li>
                        @endforelse
                    </ul>
                    @php $availableBranches = array_diff_key($branchOptions, array_flip($doctor->branchIds)); @endphp
                    @if ($availableBranches)
                        <form method="POST" action="{{ route('admin.doctors.assign', $doctor->id) }}" class="d-flex gap-2 align-items-end">
                            @csrf
                            <input type="hidden" name="kind" value="branch"><input type="hidden" name="assign" value="1">
                            <div class="flex-grow-1">
                                <label for="add-branch" class="form-label small">Agregar sede</label>
                                <select id="add-branch" name="target_id" class="form-select form-select-sm" required>
                                    @foreach ($availableBranches as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach
                                </select>
                            </div>
                            <button class="btn btn-sm btn-primary"><i class="bi bi-plus-lg" aria-hidden="true"></i> Agregar</button>
                        </form>
                    @endif
                </x-card>
            </div>
        @endif
    </div>
</x-app-layout>
