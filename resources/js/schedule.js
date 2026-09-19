/*
 * Formulario de agenda: modo día/rango y selects dependientes del médico.
 * Solo filtra opciones en pantalla; SQL Server vuelve a validar asignaciones y cruces.
 */
export default function initScheduleForm(form) {
    let doctors = [];
    try {
        doctors = JSON.parse(form.dataset.doctors || '[]');
    } catch {
        doctors = [];
    }

    const doctorSelect = form.querySelector('[name="doctor_id"]');
    const branchSelect = form.querySelector('[name="branch_id"]');
    const roomSelect = form.querySelector('[data-room-select]');

    const setPanel = (mode) => {
        form.querySelectorAll('[data-mode-panel]').forEach((panel) => {
            const active = panel.dataset.modePanel === mode;
            panel.hidden = !active;
            panel.querySelectorAll('input, select').forEach((el) => { el.disabled = !active; });
        });
    };

    const filterOptions = (select, allowed) => {
        if (!select) return;
        select.querySelectorAll('option[value]:not([value=""])').forEach((opt) => {
            const ok = allowed === null || allowed.includes(Number(opt.value));
            opt.hidden = !ok;
            opt.disabled = !ok;
        });
        if (select.selectedOptions[0]?.disabled) select.value = '';
    };

    const filterRooms = () => {
        if (!roomSelect) return;
        const branch = branchSelect?.value || '';
        roomSelect.querySelectorAll('option[data-branch]').forEach((opt) => {
            const ok = branch !== '' && opt.dataset.branch === branch;
            opt.hidden = !ok;
            opt.disabled = !ok;
        });
        if (roomSelect.selectedOptions[0]?.disabled) roomSelect.value = '';
    };

    const applyDoctor = () => {
        const doctor = doctors.find((d) => String(d.id) === doctorSelect?.value);
        form.querySelectorAll('[data-filter-by]').forEach((select) => {
            filterOptions(select, doctor ? (doctor[select.dataset.filterBy] || []) : null);
        });
        filterRooms();
    };

    form.querySelectorAll('[data-mode]').forEach((radio) => {
        radio.addEventListener('change', () => setPanel(radio.value));
    });
    doctorSelect?.addEventListener('change', applyDoctor);
    branchSelect?.addEventListener('change', filterRooms);

    setPanel(form.querySelector('[data-mode]:checked')?.value || 'single');
    applyDoctor();
}
