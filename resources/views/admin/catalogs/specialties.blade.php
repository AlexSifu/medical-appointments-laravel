<x-app-layout title="Especialidades" subtitle="Sin borrado físico: una especialidad inactiva deja de ofrecerse para nuevas reservas.">
    <div class="row g-3">
        <div class="col-12 col-xl-8">
            <x-card flush>
                @if (count($items) === 0)
                    <x-empty-state icon="bi-heart-pulse" title="No hay especialidades registradas" />
                @else
                    <x-table caption="Especialidades" :headers="['Especialidad', 'Médicos activos', 'Estado', '>Acciones']">
                        @foreach ($items as $s)
                            <tr @class(['text-secondary' => ! $s['Activo']])>
                                <td><div class="fw-semibold">{{ $s['Nombre'] }}</div><div class="small text-secondary">{{ $s['Descripcion'] }}</div></td>
                                <td>{{ $s['MedicosActivos'] }}</td>
                                <td><x-badge :variant="$s['Activo'] ? 'success' : 'secondary'">{{ $s['Activo'] ? 'Activa' : 'Inactiva' }}</x-badge></td>
                                <td class="text-end">
                                    <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="collapse" data-bs-target="#edit-s-{{ $s['EspecialidadId'] }}"
                                        aria-expanded="false" aria-controls="edit-s-{{ $s['EspecialidadId'] }}">
                                        <i class="bi bi-pencil" aria-hidden="true"></i> Editar<span class="visually-hidden"> {{ $s['Nombre'] }}</span>
                                    </button>
                                </td>
                            </tr>
                            <tr class="collapse" id="edit-s-{{ $s['EspecialidadId'] }}">
                                <td colspan="4" class="bg-light">
                                    <form method="POST" action="{{ route('admin.specialties.update', $s['EspecialidadId']) }}" class="row g-2 align-items-end" data-submit-once>
                                        @csrf
                                        @method('PUT')
                                        <div class="col-12 col-md-4">
                                            <label class="form-label small" for="s-name-{{ $s['EspecialidadId'] }}">Nombre</label>
                                            <input class="form-control form-control-sm" id="s-name-{{ $s['EspecialidadId'] }}" name="name" value="{{ $s['Nombre'] }}" maxlength="100" required>
                                        </div>
                                        <div class="col-12 col-md-5">
                                            <label class="form-label small" for="s-desc-{{ $s['EspecialidadId'] }}">Descripción</label>
                                            <input class="form-control form-control-sm" id="s-desc-{{ $s['EspecialidadId'] }}" name="description" value="{{ $s['Descripcion'] }}" maxlength="250">
                                        </div>
                                        <div class="col-6 col-md-1">
                                            <div class="form-check form-switch">
                                                <input type="hidden" name="active" value="0">
                                                <input class="form-check-input" type="checkbox" role="switch" id="s-active-{{ $s['EspecialidadId'] }}" name="active" value="1" @checked($s['Activo'])>
                                                <label class="form-check-label small" for="s-active-{{ $s['EspecialidadId'] }}">Activa</label>
                                            </div>
                                        </div>
                                        <div class="col-6 col-md-2"><button class="btn btn-sm btn-primary w-100">Guardar</button></div>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </x-table>
                @endif
            </x-card>
        </div>
        <div class="col-12 col-xl-4">
            <x-card title="Nueva especialidad" icon="bi-plus-circle">
                <form method="POST" action="{{ route('admin.specialties.store') }}" data-submit-once novalidate>
                    @csrf
                    <input type="hidden" name="active" value="1">
                    <x-input name="name" label="Nombre" maxlength="100" required />
                    <div class="mb-3">
                        <label for="f-description" class="form-label">Descripción</label>
                        <textarea id="f-description" name="description" rows="3" maxlength="250" @class(['form-control', 'is-invalid' => $errors->has('description')])>{{ old('description') }}</textarea>
                        @error('description')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>
                    <x-button icon="bi-check2" class="w-100" loading="Guardando…">Registrar</x-button>
                </form>
            </x-card>
        </div>
    </div>
</x-app-layout>
