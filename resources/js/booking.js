/**
 * Wizard de reserva / reprogramación (§73, §75).
 * Pasos: 1 Especialidad → 2 Médico/Sede → 3 Fecha → 4 Horario → 5 Confirmación.
 * Solo guía la selección: la disponibilidad real y la reserva las valida SQL Server.
 * Todo texto recibido se inserta con textContent (sin innerHTML con datos).
 */

const STATE_LABELS = {
    DISPONIBLE: 'Disponible',
    BLOQUEADO: 'Bloqueado',
    NO_DISPONIBLE: 'No disponible',
};

function el(tag, attrs = {}, ...children) {
    const node = document.createElement(tag);
    Object.entries(attrs).forEach(([key, value]) => {
        if (value === null || value === undefined || value === false) return;
        if (key === 'class') node.className = value;
        else if (key === 'dataset') Object.assign(node.dataset, value);
        else node.setAttribute(key, value === true ? '' : value);
    });
    children.flat().forEach((child) => {
        if (child === null || child === undefined) return;
        node.append(child instanceof Node ? child : document.createTextNode(String(child)));
    });
    return node;
}

async function getJson(url, params) {
    const query = new URLSearchParams();
    Object.entries(params).forEach(([k, v]) => {
        if (v !== null && v !== undefined && v !== '') query.set(k, v);
    });
    const response = await fetch(`${url}?${query}`, {
        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        credentials: 'same-origin',
    });
    if (response.status === 401 || response.status === 419) {
        window.location.reload();
        throw new Error('Sesión expirada');
    }
    const body = await response.json().catch(() => ({}));
    if (!response.ok) throw new Error(body.message || 'No se pudo cargar la información. Intenta nuevamente.');
    return body.data || [];
}

export default function initBooking(root) {
    const urls = { doctors: root.dataset.doctorsUrl, dates: root.dataset.datesUrl, slots: root.dataset.slotsUrl };
    const live = root.querySelector('[data-live]');
    const form = root.querySelector('form[data-booking-form]');
    const slotInput = form.querySelector('[data-slot-input]');
    const submit = form.querySelector('button[type="submit"]');
    const stepItems = root.querySelectorAll('.wizard-steps li');
    const panels = root.querySelectorAll('[data-step]');
    const fixedSpecialty = root.dataset.fixedSpecialty || null;

    const state = {
        specialtyId: fixedSpecialty || root.dataset.preselectedSpecialty || null,
        specialtyName: root.dataset.fixedSpecialtyName || '',
        branchId: '',
        doctorId: root.dataset.preselectedDoctor || '',
        doctorName: '',
        date: null,
        slot: null,
        step: 1,
    };

    const announce = (text) => {
        if (live) live.textContent = text;
    };

    function go(step) {
        state.step = step;
        panels.forEach((panel) => {
            panel.hidden = Number(panel.dataset.step) !== step;
        });
        stepItems.forEach((li, index) => {
            const n = index + 1;
            li.classList.toggle('done', n < step);
            if (n === step) li.setAttribute('aria-current', 'step');
            else li.removeAttribute('aria-current');
        });
        const panel = root.querySelector(`[data-step="${step}"]`);
        const heading = panel?.querySelector('h2');
        if (heading) {
            heading.setAttribute('tabindex', '-1');
            heading.focus({ preventScroll: false });
        }
    }

    function setPressed(container, selected) {
        container.querySelectorAll('[aria-pressed]').forEach((b) => b.setAttribute('aria-pressed', String(b === selected)));
    }

    function loading(container, text = 'Cargando…') {
        container.replaceChildren(
            el('div', { class: 'd-flex align-items-center gap-2 text-secondary py-3', role: 'status' },
                el('span', { class: 'spinner-border spinner-border-sm', 'aria-hidden': 'true' }), text),
        );
    }

    function empty(container, text, icon = 'bi-calendar-x') {
        container.replaceChildren(el('div', { class: 'empty-state' }, el('i', { class: `bi ${icon}`, 'aria-hidden': 'true' }), el('p', { class: 'mb-0' }, text)));
    }

    function failure(container, error) {
        container.replaceChildren(el('div', { class: 'alert alert-danger', role: 'alert' }, error.message));
    }

    /* ---------- Paso 1: especialidad ---------- */
    root.querySelectorAll('[data-specialty-id]').forEach((button) => {
        button.addEventListener('click', () => {
            state.specialtyId = button.dataset.specialtyId;
            state.specialtyName = button.dataset.name;
            state.doctorId = '';
            setPressed(button.closest('[data-choices]'), button);
            loadDoctors();
            go(2);
        });
    });

    /* ---------- Paso 2: médico / sede ---------- */
    const doctorList = root.querySelector('[data-doctor-list]');
    const branchSelect = root.querySelector('[data-branch-select]');

    branchSelect?.addEventListener('change', () => {
        state.branchId = branchSelect.value;
        loadDoctors();
    });

    function doctorButton(doctor) {
        const pressed = String(state.doctorId) === String(doctor.id ?? '');
        const content = doctor.id
            ? [
                el('span', { class: 'avatar' }, doctor.initials),
                el('span', { class: 'min-w-0' },
                    el('span', { class: 'd-block fw-semibold text-truncate' }, doctor.name),
                    el('span', { class: 'd-block small text-secondary' }, `CMP ${doctor.cmp}`),
                    el('span', { class: 'd-block small text-secondary text-truncate' }, (doctor.branches || []).join(' · ')),
                    doctor.next ? el('span', { class: 'd-block small text-success' }, el('i', { class: 'bi bi-calendar-check me-1', 'aria-hidden': 'true' }), `Próxima agenda: ${doctor.next}`) : null),
            ]
            : [
                el('span', { class: 'avatar' }, el('i', { class: 'bi bi-people', 'aria-hidden': 'true' })),
                el('span', {},
                    el('span', { class: 'd-block fw-semibold' }, 'Cualquier médico'),
                    el('span', { class: 'd-block small text-secondary' }, 'Ver la primera disponibilidad')),
            ];
        const button = el('button', { type: 'button', class: 'choice-card d-flex gap-3 align-items-center', 'aria-pressed': String(pressed) }, content);
        button.addEventListener('click', () => {
            state.doctorId = doctor.id ?? '';
            state.doctorName = doctor.id ? doctor.name : '';
            setPressed(doctorList, button);
            loadDates();
            go(3);
        });
        return el('div', { class: 'col-12 col-md-6' }, button);
    }

    async function loadDoctors() {
        if (!doctorList || !state.specialtyId) return;
        loading(doctorList, 'Buscando médicos…');
        try {
            const doctors = await getJson(urls.doctors, { specialty_id: state.specialtyId, branch_id: state.branchId });
            if (doctors.length === 0) {
                empty(doctorList, 'No hay médicos activos para esta especialidad y sede.', 'bi-person-x');
                announce('No hay médicos disponibles.');
                return;
            }
            doctorList.replaceChildren(el('div', { class: 'row g-3' }, doctorButton({ id: null }), doctors.map(doctorButton)));
            announce(`${doctors.length} médicos encontrados.`);
        } catch (error) {
            failure(doctorList, error);
        }
    }

    /* ---------- Paso 3: fecha ---------- */
    const dateList = root.querySelector('[data-date-list]');

    async function loadDates() {
        loading(dateList, 'Buscando fechas disponibles…');
        try {
            const dates = await getJson(urls.dates, { specialty_id: state.specialtyId, branch_id: state.branchId, doctor_id: state.doctorId });
            if (dates.length === 0) {
                empty(dateList, 'No hay fechas con horarios libres en los próximos días. Prueba con otro médico o sede.');
                announce('No hay fechas disponibles.');
                return;
            }
            const wrap = el('div', { class: 'd-flex flex-wrap gap-2', role: 'group', 'aria-label': 'Fechas disponibles' });
            dates.forEach((d) => {
                const button = el('button', { type: 'button', class: 'date-chip', 'aria-pressed': 'false' },
                    el('span', { class: 'fw-semibold' }, d.label),
                    el('small', {}, `${d.slots} horarios · desde ${d.first}`));
                button.addEventListener('click', () => {
                    state.date = d.date;
                    setPressed(wrap, button);
                    loadSlots();
                    go(4);
                });
                wrap.append(button);
            });
            dateList.replaceChildren(wrap);
            announce(`${dates.length} fechas con disponibilidad.`);
        } catch (error) {
            failure(dateList, error);
        }
    }

    /* ---------- Paso 4: horario ---------- */
    const slotList = root.querySelector('[data-slot-list]');

    async function loadSlots() {
        loading(slotList, 'Cargando horarios…');
        state.slot = null;
        try {
            const slots = await getJson(urls.slots, { specialty_id: state.specialtyId, branch_id: state.branchId, doctor_id: state.doctorId, date: state.date });
            if (slots.length === 0) {
                empty(slotList, 'Ya no quedan horarios para esta fecha.');
                return;
            }
            const groups = new Map();
            slots.forEach((s) => {
                const key = `${s.doctorId}|${s.branch}`;
                if (!groups.has(key)) groups.set(key, []);
                groups.get(key).push(s);
            });

            const content = [];
            groups.forEach((items) => {
                const first = items[0];
                const grid = el('div', { class: 'slot-grid', role: 'group', 'aria-label': `Horarios de ${first.doctor}` });
                items.forEach((s) => {
                    const available = s.state === 'DISPONIBLE';
                    const cls = available ? 'is-available' : s.state === 'BLOQUEADO' ? 'is-blocked' : 'is-unavailable';
                    const button = el('button', {
                        type: 'button',
                        class: `time-slot ${cls}`,
                        'aria-pressed': available ? 'false' : null,
                        'aria-disabled': available ? null : 'true',
                        'aria-label': `${s.start} a ${s.end}, ${STATE_LABELS[s.state] || s.state}`,
                    }, s.start, el('small', {}, STATE_LABELS[s.state] || s.state));
                    if (available) {
                        button.addEventListener('click', () => {
                            state.slot = s;
                            slotList.querySelectorAll('.time-slot[aria-pressed]').forEach((b) => {
                                b.setAttribute('aria-pressed', String(b === button));
                                b.classList.toggle('is-selected', b === button);
                            });
                            showSummary();
                            go(5);
                        });
                    }
                    grid.append(button);
                });
                content.push(el('div', { class: 'mb-4' },
                    el('h3', { class: 'h6 mb-1' }, first.doctor),
                    el('p', { class: 'small text-secondary mb-2' }, `${first.branch} · Consultorio ${first.room} · ${first.careType}`),
                    grid));
            });
            slotList.replaceChildren(...content);
            const free = slots.filter((s) => s.state === 'DISPONIBLE').length;
            announce(`${free} horarios disponibles el ${slots[0].dateLabel}.`);
        } catch (error) {
            failure(slotList, error);
        }
    }

    /* ---------- Paso 5: confirmación ---------- */
    function showSummary() {
        const s = state.slot;
        const values = {
            specialty: s.specialty,
            doctor: s.doctor,
            branch: s.branch,
            room: s.room,
            date: s.dateLabel,
            time: `${s.start} – ${s.end}`,
            careType: s.careType,
        };
        Object.entries(values).forEach(([key, value]) => {
            const target = root.querySelector(`[data-summary="${key}"]`);
            if (target) target.textContent = value;
        });
        slotInput.value = s.id;
        submit.disabled = false;
    }

    /* Navegación "Atrás". */
    root.querySelectorAll('[data-back]').forEach((button) => {
        button.addEventListener('click', () => go(Math.max(fixedSpecialty ? 2 : 1, state.step - 1)));
    });

    /* Estado inicial (especialidad fija en reprogramación o preseleccionada desde el directorio). */
    if (state.specialtyId) {
        const preset = root.querySelector(`[data-specialty-id="${CSS.escape(String(state.specialtyId))}"]`);
        if (preset) {
            state.specialtyName = preset.dataset.name;
            preset.setAttribute('aria-pressed', 'true');
        }
        if (state.doctorId) {
            loadDates();
            go(3);
        } else {
            loadDoctors();
            go(2);
        }
    } else {
        go(1);
    }
}
