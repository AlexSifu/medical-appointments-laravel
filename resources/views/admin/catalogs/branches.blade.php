<x-app-layout title="Sedes" subtitle="Sin borrado físico: una sede inactiva deja de ofrecerse para nuevas agendas y reservas.">
    <div class="row g-3">
        <div class="col-12 col-xl-8">
            <x-card flush>
                @if (count($items) === 0)
                    <x-empty-state icon="bi-building" title="No hay sedes registradas" />
                @else
                    <x-table caption="Sedes" :headers="['Código', 'Sede', 'Dirección', 'Estado', '>Acciones']">
                        @foreach ($items as $b)
                            <tr @class(['text-secondary' => ! $b['Activo']])>
                                <td><span class="code-pill">{{ $b['Codigo'] }}</span></td>
                                <td class="fw-semibold">{{ $b['Nombre'] }}</td>
                                <td class="small">{{ $b['Direccion'] ?? '—' }}</td>
                                <td><x-badge :variant="$b['Activo'] ? 'success' : 'secondary'">{{ $b['Activo'] ? 'Activa' : 'Inactiva' }}</x-badge></td>
                                <td class="text-end">
                                    <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="collapse" data-bs-target="#edit-b-{{ $b['SedeId'] }}"
                                        aria-expanded="false" aria-controls="edit-b-{{ $b['SedeId'] }}">
                                        <i class="bi bi-pencil" aria-hidden="true"></i> Editar<span class="visually-hidden"> {{ $b['Nombre'] }}</span>
                                    </button>
                                </td>
                            </tr>
                            <tr class="collapse" id="edit-b-{{ $b['SedeId'] }}">
                                <td colspan="5" class="bg-light">
                                    <form method="POST" action="{{ route('admin.branches.update', $b['SedeId']) }}" class="row g-2 align-items-end" data-submit-once>
                                        @csrf
                                        @method('PUT')
                                        <div class="col-6 col-md-2">
                                            <label class="form-label small" for="b-code-{{ $b['SedeId'] }}">Código</label>
                                            <input class="form-control form-control-sm" id="b-code-{{ $b['SedeId'] }}" name="code" value="{{ $b['Codigo'] }}" maxlength="20" required pattern="[A-Z0-9_-]+">
                                        </div>
                                        <div class="col-6 col-md-3">
                                            <label class="form-label small" for="b-name-{{ $b['SedeId'] }}">Nombre</label>
                                            <input class="form-control form-control-sm" id="b-name-{{ $b['SedeId'] }}" name="name" value="{{ $b['Nombre'] }}" maxlength="100" required>
                                        </div>
                                        <div class="col-12 col-md-4">
                                            <label class="form-label small" for="b-addr-{{ $b['SedeId'] }}">Dirección</label>
                                            <input class="form-control form-control-sm" id="b-addr-{{ $b['SedeId'] }}" name="address" value="{{ $b['Direccion'] }}" maxlength="200">
                                        </div>
                                        <div class="col-6 col-md-1">
                                            <div class="form-check form-switch">
                                                <input type="hidden" name="active" value="0">
                                                <input class="form-check-input" type="checkbox" role="switch" id="b-active-{{ $b['SedeId'] }}" name="active" value="1" @checked($b['Activo'])>
                                                <label class="form-check-label small" for="b-active-{{ $b['SedeId'] }}">Activa</label>
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
            <x-card title="Nueva sede" icon="bi-plus-circle">
                <form method="POST" action="{{ route('admin.branches.store') }}" data-submit-once novalidate>
                    @csrf
                    <input type="hidden" name="active" value="1">
                    <x-input name="code" label="Código" maxlength="20" required help="Mayúsculas, números, guion y guion bajo." />
                    <x-input name="name" label="Nombre" maxlength="100" required />
                    <x-input name="address" label="Dirección" maxlength="200" />
                    <x-button icon="bi-check2" class="w-100" loading="Guardando…">Registrar</x-button>
                </form>
            </x-card>
        </div>
    </div>
</x-app-layout>
