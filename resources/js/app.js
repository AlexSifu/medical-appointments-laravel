import * as bootstrap from 'bootstrap';

window.bootstrap = bootstrap;

/* Mostrar / ocultar contraseña (botón accesible con aria-pressed). */
function initPasswordToggles() {
    document.querySelectorAll('[data-password-toggle]').forEach((button) => {
        const input = document.getElementById(button.dataset.passwordToggle);
        if (!input) return;
        button.addEventListener('click', () => {
            const show = input.type === 'password';
            input.type = show ? 'text' : 'password';
            button.setAttribute('aria-pressed', String(show));
            button.setAttribute('aria-label', show ? 'Ocultar contraseña' : 'Mostrar contraseña');
            const icon = button.querySelector('i');
            if (icon) icon.className = show ? 'bi bi-eye-slash' : 'bi bi-eye';
        });
    });
}

/* Evita doble envío: deshabilita el botón y muestra "Procesando…". */
function initSubmitOnce() {
    document.querySelectorAll('form[data-submit-once]').forEach((form) => {
        form.addEventListener('submit', (event) => {
            if (form.dataset.submitted === '1') {
                event.preventDefault();
                return;
            }
            if (!form.checkValidity()) return;
            // Si requiere confirmación, se bloquea recién cuando el usuario confirma en el modal.
            if (form.dataset.confirm && form.dataset.confirmed !== '1') return;
            form.dataset.submitted = '1';
            form.querySelectorAll('button[type="submit"]').forEach((btn) => {
                btn.disabled = true;
                btn.setAttribute('aria-busy', 'true');
                const label = btn.dataset.loadingText;
                if (label) {
                    btn.innerHTML = '';
                    const spinner = document.createElement('span');
                    spinner.className = 'spinner-border spinner-border-sm me-2';
                    spinner.setAttribute('aria-hidden', 'true');
                    btn.append(spinner, document.createTextNode(label));
                }
            });
        });
    });
}

/* Confirmación con modal Bootstrap (sin window.confirm). */
function initConfirmations() {
    const modalEl = document.getElementById('confirmModal');
    if (!modalEl) return;
    const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
    const message = modalEl.querySelector('[data-confirm-message]');
    const accept = modalEl.querySelector('[data-confirm-accept]');
    let pending = null;

    document.addEventListener('submit', (event) => {
        const form = event.target;
        if (!(form instanceof HTMLFormElement) || !form.dataset.confirm || form.dataset.confirmed === '1') return;
        event.preventDefault();
        pending = form;
        message.textContent = form.dataset.confirm;
        accept.className = 'btn ' + (form.dataset.confirmVariant === 'danger' ? 'btn-danger' : 'btn-primary');
        modal.show();
    });

    accept.addEventListener('click', () => {
        if (!pending) return;
        pending.dataset.confirmed = '1';
        modal.hide();
        pending.requestSubmit();
    });
}

/* Filtros que se aplican al cambiar (select con data-autosubmit). */
function initAutoSubmit() {
    document.querySelectorAll('[data-autosubmit]').forEach((el) => {
        el.addEventListener('change', () => el.form?.requestSubmit());
    });
}

/* Modal de cancelación reutilizable: copia id/versión/acción desde el botón que lo abre. */
function initCancelModal() {
    const modalEl = document.getElementById('cancelModal');
    if (!modalEl) return;
    modalEl.addEventListener('show.bs.modal', (event) => {
        const trigger = event.relatedTarget;
        if (!trigger) return;
        const form = modalEl.querySelector('form');
        form.action = trigger.dataset.action;
        form.querySelector('[name="version"]').value = trigger.dataset.version;
        const label = modalEl.querySelector('[data-cancel-code]');
        if (label) label.textContent = trigger.dataset.code || '';
    });
}

/* Modal de bloqueo de un horario: copia slot id y etiqueta desde el botón. */
function initBlockSlotModal() {
    const modalEl = document.getElementById('blockSlotModal');
    if (!modalEl) return;
    modalEl.addEventListener('show.bs.modal', (event) => {
        const trigger = event.relatedTarget;
        if (!trigger) return;
        modalEl.querySelector('[data-slot-input]').value = trigger.dataset.slotId || '';
        modalEl.querySelector('[data-slot-label]').textContent = trigger.dataset.slotLabel || '';
    });
}

/* Botones de impresión sin manejadores inline. */
function initPrint() {
    document.querySelectorAll('[data-print]').forEach((el) => el.addEventListener('click', () => window.print()));
}

function initTooltips() {
    document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach((el) => new bootstrap.Tooltip(el));
}

document.addEventListener('DOMContentLoaded', () => {
    initPasswordToggles();
    initSubmitOnce();
    initConfirmations();
    initAutoSubmit();
    initCancelModal();
    initTooltips();
    initPrint();
    initBlockSlotModal();

    const scheduleForm = document.querySelector('form[data-schedule-form]');
    if (scheduleForm) {
        import('./schedule').then((m) => m.default(scheduleForm));
    }
    if (document.getElementById('booking-wizard')) {
        import('./booking').then((m) => m.default(document.getElementById('booking-wizard')));
    }
    if (document.querySelector('canvas[data-chart]')) {
        import('./charts').then((m) => m.default());
    }
});
