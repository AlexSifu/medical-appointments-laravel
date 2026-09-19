{{-- Modal de cancelación reutilizable. El botón que lo abre define data-action, data-version y data-code. --}}
@php
    $staff = auth()->user()->can('reservas.gestionar');
    $options = collect($reasons ?? [])
        ->filter(fn ($r) => $staff || ! (bool) ($r['SoloPersonal'] ?? false))
        ->mapWithKeys(fn ($r) => [$r['MotivoCancelacionId'] => $r['Nombre']])
        ->all();
@endphp
@push('modals')
    <div class="modal fade" id="cancelModal" tabindex="-1" aria-labelledby="cancelModal-title" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form method="POST" action="#" class="modal-content" data-submit-once>
                @csrf
                <input type="hidden" name="version" value="">
                <div class="modal-header">
                    <h2 class="modal-title h5" id="cancelModal-title">Cancelar cita <span class="code-pill" data-cancel-code></span></h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <p class="text-secondary small">El horario quedará libre para otros pacientes. Esta acción no se puede deshacer.</p>
                    <x-select name="reason_id" label="Motivo de cancelación" :options="$options" placeholder="Selecciona un motivo" required id="cancel-reason" />
                    <div class="mb-0">
                        <label for="cancel-notes" class="form-label">Comentario (opcional)</label>
                        <textarea id="cancel-notes" name="notes" class="form-control" rows="2" maxlength="500"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Volver</button>
                    <button type="submit" class="btn btn-danger" data-loading-text="Cancelando…"><i class="bi bi-x-circle me-1" aria-hidden="true"></i>Cancelar cita</button>
                </div>
            </form>
        </div>
    </div>
@endpush
