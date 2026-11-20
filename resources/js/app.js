import './bootstrap';

window.addEventListener('DOMContentLoaded', () => {
    const searchBox = document.querySelector('#global-search');
    const searchInput = document.querySelector('#global-search-input');
    const searchResults = document.querySelector('#global-search-results');
    const searchItems = [...document.querySelectorAll('[data-search-item]')];
    const searchEmpty = document.querySelector('#global-search-empty');

    const filterSections = () => {
        if (!searchInput || !searchResults) return;
        const term = searchInput.value.trim().toLocaleLowerCase('es');
        let visible = 0;

        searchItems.forEach((item) => {
            const matches = !term || item.dataset.searchText.includes(term);
            item.classList.toggle('hidden', !matches);
            if (matches) visible += 1;
        });

        searchEmpty?.classList.toggle('hidden', visible > 0);
        searchResults.classList.remove('hidden');
    };

    searchInput?.addEventListener('focus', filterSections);
    searchInput?.addEventListener('input', filterSections);
    searchInput?.addEventListener('keydown', (event) => {
        if (event.key === 'Enter') {
            const firstResult = searchItems.find((item) => !item.classList.contains('hidden'));
            if (firstResult) {
                event.preventDefault();
                window.location.href = firstResult.href;
            }
        }
        if (event.key === 'Escape') {
            searchResults?.classList.add('hidden');
            searchInput.blur();
        }
    });

    document.addEventListener('keydown', (event) => {
        if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'k') {
            event.preventDefault();
            searchInput?.focus();
        }
    });

    document.addEventListener('click', (event) => {
        if (searchBox && !searchBox.contains(event.target)) {
            searchResults?.classList.add('hidden');
        }
    });

    const sidebar = document.querySelector('#sidebar');
    const sidebarBackdrop = document.querySelector('#sidebar-backdrop');
    const sidebarOpen = document.querySelector('#sidebar-open');
    const sidebarClose = document.querySelector('#sidebar-close');

    const toggleSidebar = (open) => {
        if (!sidebar || !sidebarBackdrop) return;
        sidebar.classList.toggle('-translate-x-full', !open);
        sidebarBackdrop.classList.toggle('hidden', !open);
        document.body.classList.toggle('overflow-hidden', open);
    };

    sidebarOpen?.addEventListener('click', () => toggleSidebar(true));
    sidebarClose?.addEventListener('click', () => toggleSidebar(false));
    sidebarBackdrop?.addEventListener('click', () => toggleSidebar(false));

    const positionAnchoredPopover = (trigger) => {
        const popoverId = trigger?.getAttribute('popovertarget');
        const popover = popoverId ? document.getElementById(popoverId) : null;
        if (!popover) return;

        const triggerRect = trigger.getBoundingClientRect();
        const width = popover.offsetWidth || 224;
        const height = popover.offsetHeight || 260;
        const gap = 8;
        const margin = 12;
        const left = Math.min(
            Math.max(margin, triggerRect.right - width),
            window.innerWidth - width - margin,
        );
        let top = triggerRect.bottom + gap;

        if (top + height > window.innerHeight - margin) {
            top = Math.max(margin, triggerRect.top - height - gap);
        }

        popover.style.position = 'fixed';
        popover.style.inset = 'auto';
        popover.style.left = `${left}px`;
        popover.style.top = `${top}px`;
        popover.style.margin = '0';
    };

    document.querySelectorAll('[popovertarget]').forEach((trigger) => {
        trigger.addEventListener('click', () => positionAnchoredPopover(trigger));
    });

    document.querySelectorAll('[data-dialog-close]').forEach((button) => {
        button.addEventListener('click', () => button.closest('dialog')?.close());
    });

    document.querySelectorAll('[data-flash-message]').forEach((message) => {
        window.setTimeout(() => {
            message.classList.add('opacity-0', '-translate-y-2');
            window.setTimeout(() => message.remove(), 500);
        }, 4500);
    });

    document.querySelectorAll('[data-finish-dialog]').forEach((dialog) => {
        const base = Number(dialog.dataset.baseCost || 0);
        const discount = dialog.querySelector('[data-finish-discount]');
        const extra = dialog.querySelector('[data-finish-extra]');
        const total = dialog.querySelector('[data-finish-total]');
        const money = (value) => Number(value).toLocaleString('es-MX', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
        });
        const updateTotal = () => {
            if (!total) return;
            const subtotal = base + Number(extra?.value || 0);
            const discountPercentage = Math.min(100, Math.max(0, Number(discount?.value || 0)));
            total.textContent = money(Math.max(0, subtotal * (1 - discountPercentage / 100)));
        };

        discount?.addEventListener('input', updateTotal);
        extra?.addEventListener('input', updateTotal);
        updateTotal();
    });

    const passwordInput = document.querySelector('[data-password-strength-input]');
    const passwordStrength = document.querySelector('[data-password-strength]');
    const passwordStrengthBar = document.querySelector('[data-password-strength-bar]');
    const passwordStrengthLabel = document.querySelector('[data-password-strength-label]');
    const passwordStrengthRules = passwordStrength
        ? [...passwordStrength.querySelectorAll('[data-rule]')]
        : [];

    const strengthLevels = [
        { label: 'Sin evaluar', width: '0%', color: 'bg-slate-300', text: 'text-slate-500' },
        { label: 'Mala', width: '25%', color: 'bg-red-500', text: 'text-red-600' },
        { label: 'Regular', width: '50%', color: 'bg-amber-500', text: 'text-amber-600' },
        { label: 'Buena', width: '75%', color: 'bg-[#9700cf]', text: 'text-[#9700cf]' },
        { label: 'Muy segura', width: '100%', color: 'bg-emerald-500', text: 'text-emerald-600' },
    ];

    const updatePasswordStrength = () => {
        if (!passwordInput || !passwordStrengthBar || !passwordStrengthLabel) return;

        const password = passwordInput.value;
        const checks = {
            length: password.length >= 8,
            case: /[a-z]/.test(password) && /[A-Z]/.test(password),
            number: /\d/.test(password),
            symbol: /[^A-Za-z0-9]/.test(password),
        };
        const passed = Object.values(checks).filter(Boolean).length;
        const level = password.length === 0 ? strengthLevels[0] : strengthLevels[passed];

        passwordStrengthBar.className = `h-full rounded-full transition-all duration-300 ${level.color}`;
        passwordStrengthBar.style.width = level.width;
        passwordStrengthLabel.className = `text-xs font-bold ${level.text}`;
        passwordStrengthLabel.textContent = level.label;

        passwordStrengthRules.forEach((rule) => {
            const isValid = checks[rule.dataset.rule];
            rule.classList.toggle('text-emerald-600', isValid);
            rule.classList.toggle('text-slate-500', !isValid);
        });
    };

    passwordInput?.addEventListener('input', updatePasswordStrength);
    updatePasswordStrength();

    const draggableEvents = document.querySelectorAll('.draggable-event');
    const calendarCells = document.querySelectorAll('.calendar-cell[data-date]');
    let draggedElement = null;
    let pendingCalendarMove = null;

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

    const showCalendarToast = (message, type = 'success') => {
        const toast = document.createElement('div');
        toast.className = `fixed bottom-6 right-6 z-50 max-w-sm rounded-2xl px-5 py-3 text-sm font-semibold text-white shadow-xl ${type === 'error' ? 'bg-red-600' : 'bg-[#4B136B]'}`;
        toast.textContent = message;
        document.body.appendChild(toast);
        setTimeout(() => toast.remove(), 3500);
    };

    const formatDate = (value) => {
        const [year, month, day] = String(value || '').split('-').map(Number);
        if (!year || !month || !day) return value;
        return new Date(year, month - 1, day).toLocaleDateString('es-MX', {
            weekday: 'long',
            year: 'numeric',
            month: 'long',
            day: 'numeric',
        });
    };

    const isPastCalendarDate = (value) => {
        const [year, month, day] = String(value || '').split('-').map(Number);
        if (!year || !month || !day) return true;
        const target = new Date(year, month - 1, day);
        const today = new Date();
        today.setHours(0, 0, 0, 0);
        return target < today;
    };

    const ensureCalendarMoveModal = () => {
        let modal = document.getElementById('calendar-move-modal');
        if (modal) return modal;

        modal = document.createElement('div');
        modal.id = 'calendar-move-modal';
        modal.className = 'fixed inset-0 z-[80] hidden items-center justify-center bg-slate-950/50 px-4 backdrop-blur-sm';
        modal.innerHTML = `
            <div class="w-full max-w-md rounded-3xl bg-white p-6 shadow-2xl">
                <div class="flex items-start gap-3">
                    <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-[#f6e8ff] text-[#9700cf]">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M5 4h14a2 2 0 0 1 2 2v14H3V6a2 2 0 0 1 2-2ZM7 2v4m10-4v4M3 9h18"/></svg>
                    </span>
                    <div>
                        <p class="text-xs font-bold uppercase tracking-wider text-[#9700cf]">Confirmar cambio</p>
                        <h2 class="mt-1 text-xl font-bold text-slate-900">Mover cita en calendario</h2>
                        <p class="mt-1 text-sm text-slate-500">Revisa los datos antes de guardar el cambio.</p>
                    </div>
                </div>
                <div class="mt-5 space-y-3 rounded-2xl bg-slate-50 p-4 text-sm">
                    <div><p class="text-xs font-bold uppercase tracking-wider text-slate-400">Paciente</p><p data-calendar-move-patient class="mt-1 font-semibold text-slate-800"></p></div>
                    <div><p class="text-xs font-bold uppercase tracking-wider text-slate-400">Tratamiento</p><p data-calendar-move-treatment class="mt-1 font-semibold text-slate-800"></p></div>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div><p class="text-xs font-bold uppercase tracking-wider text-slate-400">Fecha actual</p><p data-calendar-move-current class="mt-1 font-semibold text-slate-800"></p></div>
                        <div><p class="text-xs font-bold uppercase tracking-wider text-slate-400">Nueva fecha</p><p data-calendar-move-target class="mt-1 font-semibold text-slate-800"></p></div>
                    </div>
                    <div>
                        <p class="text-xs font-bold uppercase tracking-wider text-slate-400">Hora</p>
                        <p data-calendar-move-time class="mt-1 font-semibold text-slate-800"></p>
                    </div>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <label class="block">
                            <span class="text-xs font-bold uppercase tracking-wider text-slate-400">Hora de inicio</span>
                            <input type="time" data-calendar-move-start class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm font-semibold text-slate-800 outline-none focus:border-[#9700cf] focus:ring-2 focus:ring-[#9700cf]/15">
                        </label>
                        <label class="block">
                            <span class="text-xs font-bold uppercase tracking-wider text-slate-400">Hora de término</span>
                            <input type="time" data-calendar-move-end class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm font-semibold text-slate-800 outline-none focus:border-[#9700cf] focus:ring-2 focus:ring-[#9700cf]/15">
                        </label>
                    </div>
                </div>
                <p data-calendar-move-error class="mt-4 hidden rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-700"></p>
                <div class="mt-6 flex justify-end gap-3">
                    <button type="button" data-calendar-move-cancel class="rounded-xl border border-slate-200 px-5 py-3 text-sm font-semibold text-slate-600 transition hover:bg-slate-50">Cancelar</button>
                    <button type="button" data-calendar-move-confirm class="rounded-xl bg-[#9700cf] px-5 py-3 text-sm font-bold text-white shadow-lg shadow-[#d3a2f2] transition hover:bg-[#512569]">Confirmar</button>
                </div>
            </div>
        `;
        document.body.appendChild(modal);

        modal.querySelector('[data-calendar-move-cancel]')?.addEventListener('click', () => {
            pendingCalendarMove = null;
            modal.classList.add('hidden');
            modal.classList.remove('flex');
        });

        modal.addEventListener('click', (event) => {
            if (event.target === modal) {
                pendingCalendarMove = null;
                modal.classList.add('hidden');
                modal.classList.remove('flex');
            }
        });

        modal.querySelector('[data-calendar-move-confirm]')?.addEventListener('click', async () => {
            if (!pendingCalendarMove) return;

            const confirmButton = modal.querySelector('[data-calendar-move-confirm]');
            const errorBox = modal.querySelector('[data-calendar-move-error]');
            const startInput = modal.querySelector('[data-calendar-move-start]');
            const endInput = modal.querySelector('[data-calendar-move-end]');
            const selectedStartTime = startInput?.value || pendingCalendarMove.targetTime;
            const selectedEndTime = endInput?.value || pendingCalendarMove.targetEndTime;

            if (!selectedStartTime) {
                errorBox.textContent = 'Selecciona la hora de inicio.';
                errorBox.classList.remove('hidden');
                return;
            }

            if (selectedEndTime && selectedEndTime <= selectedStartTime) {
                errorBox.textContent = 'La hora de término debe ser posterior a la hora de inicio.';
                errorBox.classList.remove('hidden');
                return;
            }

            confirmButton.disabled = true;
            confirmButton.textContent = 'Guardando...';
            errorBox.classList.add('hidden');

            try {
                const response = await fetch(pendingCalendarMove.url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                    body: JSON.stringify({
                        fecha: pendingCalendarMove.targetDate,
                        hora: selectedStartTime,
                        hora_fin: selectedEndTime,
                    }),
                });
                const payload = await response.json().catch(() => ({}));

                if (!response.ok) {
                    throw new Error(payload.message || 'No se pudo mover la cita.');
                }

                pendingCalendarMove.targetCell.appendChild(pendingCalendarMove.element);
                pendingCalendarMove.element.dataset.currentDate = pendingCalendarMove.targetDate;
                pendingCalendarMove.element.dataset.time = selectedStartTime;
                if (selectedEndTime) pendingCalendarMove.element.dataset.endTime = selectedEndTime;
                modal.classList.add('hidden');
                modal.classList.remove('flex');
                showCalendarToast(payload.message || 'Cita reprogramada correctamente.');
                pendingCalendarMove = null;
                window.setTimeout(() => window.location.reload(), 650);
            } catch (error) {
                errorBox.textContent = error.message;
                errorBox.classList.remove('hidden');
            } finally {
                confirmButton.disabled = false;
                confirmButton.textContent = 'Confirmar';
            }
        });

        return modal;
    };

    const openCalendarMoveModal = (element, targetCell, targetDate, targetTime = null, targetEndTime = null) => {
        const modal = ensureCalendarMoveModal();
        const currentDate = element.dataset.currentDate || '';
        const time = element.dataset.time || '';
        const endTime = element.dataset.endTime || '';
        const editableStartTime = targetTime || time;
        const editableEndTime = targetEndTime || endTime;

        pendingCalendarMove = {
            element,
            targetCell,
            targetDate,
            targetTime: editableStartTime,
            targetEndTime: editableEndTime,
            url: element.dataset.moveUrl,
        };

        modal.querySelector('[data-calendar-move-patient]').textContent = element.dataset.patient || 'Sin paciente';
        modal.querySelector('[data-calendar-move-treatment]').textContent = element.dataset.treatment || 'Sin tratamiento';
        modal.querySelector('[data-calendar-move-current]').textContent = formatDate(currentDate);
        modal.querySelector('[data-calendar-move-target]').textContent = formatDate(targetDate);
        const currentTimeLabel = endTime ? `${time} - ${endTime}` : time;
        const targetTimeLabel = targetEndTime ? `${targetTime} - ${targetEndTime}` : targetTime;
        modal.querySelector('[data-calendar-move-time]').textContent = targetTime
            ? `${currentTimeLabel} a ${targetTimeLabel}`
            : currentTimeLabel;
        const startInput = modal.querySelector('[data-calendar-move-start]');
        const endInput = modal.querySelector('[data-calendar-move-end]');
        if (startInput) startInput.value = editableStartTime || '';
        if (endInput) endInput.value = editableEndTime || '';
        modal.querySelector('[data-calendar-move-error]').classList.add('hidden');
        modal.classList.remove('hidden');
        modal.classList.add('flex');
    };

    draggableEvents.forEach((eventEl) => {
        eventEl.addEventListener('dragstart', (e) => {
            draggedElement = e.currentTarget;
            e.dataTransfer.effectAllowed = 'move';
            e.dataTransfer.setData('text/plain', e.currentTarget.dataset.eventId || '');
        });
    });

    document.querySelectorAll('.calendar-cell[data-date], .calendar-slot[data-date][data-time]').forEach((cell) => {
        cell.addEventListener('dragover', (e) => {
            e.preventDefault();
            cell.classList.add('ring-2', 'ring-[#4B136B]/50');
        });

        cell.addEventListener('dragleave', () => {
            cell.classList.remove('ring-2', 'ring-[#4B136B]/50');
        });

        cell.addEventListener('drop', (e) => {
            e.preventDefault();
            cell.classList.remove('ring-2', 'ring-[#4B136B]/50');

            if (!draggedElement) {
                return;
            }

            const eventId = e.dataTransfer.getData('text/plain');
            const targetDate = cell.dataset.date;
            const targetTime = cell.dataset.time || null;
            const targetEndTime = cell.dataset.endTime || null;
            const currentDate = draggedElement.dataset.currentDate;

            if (!eventId || !targetDate || !draggedElement.dataset.moveUrl) {
                showCalendarToast('No se pudo identificar la cita para moverla.', 'error');
                draggedElement = null;
                return;
            }

            if (targetDate === currentDate && (!targetTime || targetTime === draggedElement.dataset.time)) {
                showCalendarToast('La cita ya esta en ese horario.', 'error');
                draggedElement = null;
                return;
            }

            if (isPastCalendarDate(targetDate)) {
                showCalendarToast('No puedes cambiar la cita a una fecha que ya paso.', 'error');
                draggedElement = null;
                return;
            }

            openCalendarMoveModal(draggedElement, cell, targetDate, targetTime, targetEndTime);
            draggedElement = null;
        });
    });
});

