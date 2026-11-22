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
        const availabilityUrl = input.getAttribute('data-availability-url');
        let availability = new Map();
        const loadedMonths = new Map();
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
        if (availabilityUrl) {
            const legend = document.createElement('div');
            legend.className = 'date-availability-legend';
            legend.innerHTML = '<span><i class="date-available"></i>Tersedia</span>'
                + '<span><i class="date-nearly-full"></i>Hampir penuh</span>'
                + '<span><i class="date-full"></i>Penuh</span>'
                + '<span><i class="date-menu-empty"></i>Menu Habis</span>'
                + '<span><i class="date-closed"></i>Libur</span>';
            dialog.append(legend);
        }
        input.hidden = true;
        label.htmlFor = trigger.id;
        let visibleMonth;

        function monthRange(date = visibleMonth) {
            const year = date.getFullYear();
            const month = date.getMonth();
            const first = dateValue(new Date(year, month, 1, 12));
            const last = dateValue(new Date(year, month + 1, 0, 12));
            return {
                start: input.min && first < input.min ? input.min : first,
                end: input.max && last > input.max ? input.max : last,
            };
        }

        function selectable(value) {
            if (!isAvailable(value, input.min, input.max)) return false;
            if (!availabilityUrl) return true;
            const state = availability.get(value);
            return !state || !['full', 'menu_empty', 'closed'].includes(state.status);
        }

        function ensureAvailability(month = visibleMonth) {
            if (!availabilityUrl) return Promise.resolve(true);
            const { start, end } = monthRange(month);
            const key = start.slice(0, 7);
            if (start > end) return Promise.resolve(true);
            if (!loadedMonths.has(key)) loadedMonths.set(key,
                fetch(`${availabilityUrl}?start=${start}&end=${end}`, { cache: 'no-store' })
                    .then(async response => {
                        const data = await response.json();
                        if (!response.ok || data.error) throw Error(data.error || 'Gagal memuat tanggal');
                        (data.dates || []).forEach(day => availability.set(day.tanggal, day));
                        return true;
                    }).catch(() => {
                        loadedMonths.delete(key);
                        return false;
                    }).finally(paintAvailability)
            );
            return loadedMonths.get(key);
        }

        function paintAvailability() {
            if (!availabilityUrl) return;
            dialog.querySelectorAll('[data-day]').forEach(button => {
                const value = button.dataset.day;
                const state = availability.get(value);
                button.classList.remove('date-available', 'date-nearly-full', 'date-full', 'date-menu-empty', 'date-closed');
                if (state) {
                    button.classList.add('date-' + state.status.replaceAll('_', '-'));
                    const statusLabel = state.status === 'closed' ? 'Libur'
                        : state.status === 'full' ? 'Penuh'
                        : state.status === 'menu_empty' ? 'Menu Habis'
                        : state.status === 'nearly_full' ? 'Hampir penuh' : 'Tersedia';
                    button.setAttribute('aria-label', `${dayFormat.format(dateFromValue(value))}, ${statusLabel}`);
                    button.title = state.status === 'closed' ? 'Tanggal libur'
                        : state.status === 'full'
                            ? 'Kuota dapur penuh'
                            : state.status === 'menu_empty'
                                ? 'Tidak ada menu yang dapat dipesan'
                            : `Sisa ${state.sisa} porsi`;
                }
                button.disabled = !selectable(value);
            });
        }

        function sync() {
            const date = dateFromValue(input.value);
            trigger.querySelector('.date-trigger-value').textContent = date ? dayFormat.format(date) : 'Pilih tanggal';
            trigger.setAttribute('aria-label', `${label.textContent}: ${date ? dayFormat.format(date) : 'belum dipilih'}`);
        }

        function select(value) {
            if (!selectable(value)) return;
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
                button.disabled = !selectable(value);
                button.setAttribute('aria-label', dayFormat.format(dateFromValue(value)));
                button.setAttribute('aria-pressed', String(value === input.value));
                if (value === today) button.setAttribute('aria-current', 'date');
                grid.append(button);
            });
            dialog.querySelector('[data-month="-1"]').disabled = Boolean(input.min && days[0].slice(0, 7) <= input.min.slice(0, 7));
            dialog.querySelector('[data-month="1"]').disabled = Boolean(input.max && days[0].slice(0, 7) >= input.max.slice(0, 7));
            const first = dialog.querySelector('.date-first-button');
            if (availabilityUrl) first.setAttribute('aria-live', 'polite');
            const firstDay = input.min && today < input.min ? input.min : today;
            first.textContent = availabilityUrl ? 'Tanggal tersedia' : firstDay === today ? 'Ke hari ini' : 'Tanggal terawal';
            first.disabled = availabilityUrl ? Boolean(input.max && firstDay > input.max) : !selectable(firstDay);
            first.onclick = async () => {
                if (!availabilityUrl) return select(firstDay);
                first.disabled = true;
                first.textContent = 'Mencari tanggal...';
                let failed = false;
                // shortcut: pencarian dibatasi 12 bulan agar satu klik tidak memuat kalender tanpa batas.
                for (let offset = 0; offset < 12; offset++) {
                    const base = dateFromValue(firstDay);
                    const month = new Date(base.getFullYear(), base.getMonth() + offset, 1, 12);
                    const { start, end } = monthRange(month);
                    if (start > end) break;
                    if (!await ensureAvailability(month)) { failed = true; break; }
                    if (!dialog.open) return;
                    const next = [...availability.values()]
                        .filter(day => day.tanggal >= firstDay && day.tanggal <= end && day.status === 'available')
                        .sort((a, b) => a.tanggal.localeCompare(b.tanggal))[0];
                    if (next) return select(next.tanggal);
                }
                first.textContent = failed ? 'Gagal memuat. Coba lagi' : 'Tidak ada tanggal tersedia';
                first.disabled = !failed;
            };
            paintAvailability();
            ensureAvailability();
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
            if (!selectable(value)) return;
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
