(() => {
    const dayFormat = new Intl.DateTimeFormat('id-ID', { dateStyle: 'full' });
    const monthFormat = new Intl.DateTimeFormat('id-ID', { month: 'long', year: 'numeric' });
    const today = new Intl.DateTimeFormat('en-CA', {
        timeZone: 'Asia/Jakarta', year: 'numeric', month: '2-digit', day: '2-digit'
    }).format(new Date());

    function dateFromValue(value) {
        if (!/^\d{4}-\d{2}-\d{2}$/.test(value)) return null;
        const date = new Date(value + 'T12:00:00');
        return Number.isNaN(date.getTime()) || dateValue(date) !== value ? null : date;
    }

    function dateValue(date) {
        return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
    }

    function monthDays(year, month) {
        const first = new Date(year, month, 1, 12);
        return {
            offset: (first.getDay() + 6) % 7,
            days: Array.from({ length: new Date(year, month + 1, 0).getDate() },
                (_, index) => dateValue(new Date(year, month, index + 1, 12)))
        };
    }

    function isAvailable(value, min, max) {
        return (!min || value >= min) && (!max || value <= max);
    }

    document.querySelectorAll('[data-date-picker]').forEach(input => {
        if (typeof HTMLDialogElement === 'undefined') return;
        const field = input.closest('.date-field');
        const label = field.querySelector('label');
        const trigger = document.createElement('button');
        trigger.type = 'button';
        trigger.id = input.id + '-trigger';
        trigger.className = 'date-trigger btn';
        trigger.setAttribute('aria-haspopup', 'dialog');
        trigger.setAttribute('aria-expanded', 'false');
        trigger.setAttribute('aria-describedby', input.getAttribute('aria-describedby'));
        trigger.innerHTML = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><rect x="3" y="5" width="18" height="16" rx="4"/><path d="M7 3v4M17 3v4M3 11h18m-12 5h6"/></svg><span class="date-trigger-value"></span><span class="date-trigger-action">Ubah <span aria-hidden="true">⌄</span></span>`;
        const dialog = document.createElement('dialog');
        dialog.className = 'date-calendar';
        dialog.id = input.id + '-calendar';
        dialog.setAttribute('aria-labelledby', input.id + '-calendar-title');
        trigger.setAttribute('aria-controls', dialog.id);
        dialog.innerHTML = `<div class="date-calendar-heading"><div><span class="date-eyebrow">PILIH TANGGAL</span><h2 id="${input.id}-calendar-title"></h2></div><button type="button" class="date-icon-button" data-dismiss aria-label="Tutup kalender">×</button></div><div class="date-month-nav"><button type="button" class="date-icon-button" data-month="-1" aria-label="Bulan sebelumnya">‹</button><strong aria-live="polite"></strong><button type="button" class="date-icon-button" data-month="1" aria-label="Bulan berikutnya">›</button></div><div class="date-weekdays" aria-hidden="true"><span>Sen</span><span>Sel</span><span>Rab</span><span>Kam</span><span>Jum</span><span>Sab</span><span>Min</span></div><div class="date-days" role="group" aria-label="Tanggal dalam bulan"></div><div class="date-calendar-footer"><span><i aria-hidden="true"></i> Hari ini</span><button type="button" class="date-first-button"></button></div>`;
        dialog.querySelector('h2').textContent = label.textContent;
        input.after(trigger, dialog);
        input.hidden = true;
        label.htmlFor = trigger.id;
        let visibleMonth;

        function sync() {
            const date = dateFromValue(input.value);
            trigger.querySelector('.date-trigger-value').textContent = date ? dayFormat.format(date) : 'Pilih tanggal';
            trigger.setAttribute('aria-label', `${label.textContent}: ${date ? dayFormat.format(date) : 'belum dipilih'}`);
        }

        function select(value) {
            if (!isAvailable(value, input.min, input.max)) return;
            const changed = input.value !== value;
            input.value = value;
            sync();
            dialog.close();
            if (changed) {
                input.dispatchEvent(new Event('input', { bubbles: true }));
                input.dispatchEvent(new Event('change', { bubbles: true }));
            }
        }

        function render() {
            dialog.querySelector('.date-month-nav strong').textContent = monthFormat.format(visibleMonth);
            const { offset, days } = monthDays(visibleMonth.getFullYear(), visibleMonth.getMonth());
            const grid = dialog.querySelector('.date-days');
            grid.replaceChildren();
            for (let index = 0; index < offset; index++) {
                const blank = document.createElement('span');
                blank.setAttribute('aria-hidden', 'true');
                grid.append(blank);
            }
            days.forEach(value => {
                const button = document.createElement('button');
                button.type = 'button';
                button.textContent = Number(value.slice(-2));
                button.dataset.day = value;
                button.disabled = !isAvailable(value, input.min, input.max);
                button.setAttribute('aria-label', dayFormat.format(dateFromValue(value)));
                button.setAttribute('aria-pressed', String(value === input.value));
                if (value === today) button.setAttribute('aria-current', 'date');
                grid.append(button);
            });
            dialog.querySelector('[data-month="-1"]').disabled = Boolean(input.min && days[0].slice(0, 7) <= input.min.slice(0, 7));
            dialog.querySelector('[data-month="1"]').disabled = Boolean(input.max && days[0].slice(0, 7) >= input.max.slice(0, 7));
            const first = dialog.querySelector('.date-first-button');
            const firstDay = input.min && today < input.min ? input.min : today;
            first.textContent = firstDay === today ? 'Ke hari ini' : 'Tanggal terawal';
            first.disabled = !isAvailable(firstDay, input.min, input.max);
            first.onclick = () => select(firstDay);
        }

        trigger.addEventListener('click', () => {
            sync();
            const value = dateFromValue(input.value) || dateFromValue(input.min) || dateFromValue(today);
            visibleMonth = new Date(value.getFullYear(), value.getMonth(), 1, 12);
            render();
            dialog.showModal();
            trigger.setAttribute('aria-expanded', 'true');
            const focusDay = dialog.querySelector('[aria-pressed="true"]:not(:disabled), [data-day]:not(:disabled)');
            focusDay?.focus();
        });
        dialog.addEventListener('click', event => {
            const button = event.target.closest('button');
            if (button?.dataset.day) select(button.dataset.day);
            if (button?.dataset.month) {
                visibleMonth.setMonth(visibleMonth.getMonth() + Number(button.dataset.month));
                render();
            }
            if (button?.hasAttribute('data-dismiss')) dialog.close();
            if (event.target === dialog) {
                const bounds = dialog.getBoundingClientRect();
                if (event.clientX < bounds.left || event.clientX > bounds.right || event.clientY < bounds.top || event.clientY > bounds.bottom) dialog.close();
            }
        });
        dialog.addEventListener('keydown', event => {
            const day = event.target.dataset.day;
            const shifts = { ArrowLeft: -1, ArrowRight: 1, ArrowUp: -7, ArrowDown: 7 };
            if (!day || !shifts[event.key]) return;
            event.preventDefault();
            const date = dateFromValue(day);
            date.setDate(date.getDate() + shifts[event.key]);
            const value = dateValue(date);
            if (!isAvailable(value, input.min, input.max)) return;
            visibleMonth = new Date(date.getFullYear(), date.getMonth(), 1, 12);
            render();
            dialog.querySelector(`[data-day="${value}"]`)?.focus();
        });
        dialog.addEventListener('close', () => {
            trigger.setAttribute('aria-expanded', 'false');
            trigger.focus();
        });
        input.addEventListener('change', sync);
        input.addEventListener('input', sync);
        sync();
    });
})();
