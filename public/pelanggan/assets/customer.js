const $ = (s, r = document) => r.querySelector(s),
    $$ = (s, r = document) => [...r.querySelectorAll(s)];
const money = n => 'Rp ' + Number(n || 0).toLocaleString('id-ID');
const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({
    '&': '&amp;',
    '<': '&lt;',
    '>': '&gt;',
    '"': '&quot;',
    "'": '&#39;'
}[c]));

// Token CSRF dari meta tag layout; hanya dilampirkan pada permintaan mutasi (D1).
const csrfToken = () =>
    document.querySelector('meta[name="csrf-token"]')?.content || "";

async function api(url, options = {}) {
    const headers = {...options.headers};
    if (!(options.body instanceof FormData)) headers['Content-Type'] = 'application/json';
    if (options.method && options.method.toUpperCase() !== 'GET') {
        headers['X-CSRF-Token'] = csrfToken();
    }

    const response = await fetch(url, {
        ...options,
        headers,
        body: options.body instanceof FormData
            ? options.body
            : options.body ? JSON.stringify(options.body) : undefined
    });
    const data = await response.json().catch(() => ({error: 'Respons server tidak valid'}));
    if (response.status === 401) {
        location.href = '../login.php';
        throw Error('Sesi berakhir')
    }
    if (!response.ok) throw Error(data.error || 'Terjadi kesalahan');

    return data
}

function toast(message) {
    const el = $('#customer-toast');
    if (!el) return;

    el.textContent = message;
    el.classList.add('show');
    setTimeout(() => el.classList.remove('show'), 2600)
}

function wireLogout() {
    $$('[data-logout]').forEach(b => b.onclick = async () => {
        b.disabled = true;
        const confirmed = await new Promise(resolve => {
            const dialog = document.createElement('dialog');
            dialog.className = 'customer-dialog';
            dialog.innerHTML = '<div style="padding:26px"><p style="margin:0 0 18px">Yakin ingin keluar dari akun Se.Noesa?</p><div style="display:flex;gap:10px"><button type="button" class="customer-button secondary" data-cancel>Batal</button><button type="button" class="customer-button primary" data-yes>Ya, Keluar</button></div></div>';
            const yes = dialog.querySelector('[data-yes]');
            const no = dialog.querySelector('[data-cancel]');
            const done = (v) => { dialog.close(); dialog.remove(); resolve(v); };
            yes.onclick = () => done(true);
            no.onclick = () => done(false);
            dialog.onclick = (e) => { if (e.target === dialog) done(false); };
            document.body.appendChild(dialog);
            dialog.showModal();
        });
        if (!confirmed) { b.disabled = false; return; }
        try {
            await api('../api/auth.php?a=logout');
            location.href = '../login.php'
        } catch (e) {
            b.disabled = false;
            toast(e.message)
        }
    })
}

function imagePath(name) {
    const raw = String(name || '');
    if (/^uploads\/(products|payment-methods)\//i.test(raw)) return '../' + raw;

    const file = raw.replace(/[^a-zA-Z0-9_.-]/g, '');
    return file ? '../assets/img/produk/' + file : '../assets/img/produk/NK-01.png'
}

let catalog = [], productCache = {
}, category = '', schedule = null, catalogClosed = false, cart = {
}, activeProduct = null, activeQty = 1, searchTimer, savedAddresses = [], detailOpener = null;
function dateLabelShort(value) {
    const d = new Date(value + 'T00:00:00+07:00');
    return new Intl.DateTimeFormat('id-ID', {
        weekday: 'long', day: 'numeric', month: 'short', year: 'numeric', timeZone: 'Asia/Jakarta'
    }).format(d);
}

/* ---------- Kalender jadwal pengiriman ---------- */
const CAL = {min: '', holidays: [], month: null, selected: '', cache: {}, ticket: 0};

// Tanggal ISO ('YYYY-MM-DD') diubah ke Date lokal khusus aritmetika kalender.
const isoOf = value => {
    const [y, m, d] = String(value || '').split('-').map(Number);
    return new Date(y, (m || 1) - 1, d || 1);
};
const isoDate = d => [d.getFullYear(), String(d.getMonth() + 1).padStart(2, '0'), String(d.getDate()).padStart(2, '0')].join('-');
const monthIndex = d => d.getFullYear() * 12 + d.getMonth();

function shortDate(value) {
    return new Intl.DateTimeFormat('id-ID', {
        day: 'numeric', month: 'short', year: 'numeric', timeZone: 'Asia/Jakarta'
    }).format(new Date(value + 'T00:00:00+07:00'));
}

function isHolidayDate(value) {
    return CAL.holidays.some(r => value >= r.mulai && value <= r.selesai);
}

function calendarInit() {
    const input = $('#catalog-delivery-date'), cfg = window.customerCalendar || {};
    CAL.min = input?.min || cfg.minDate || input?.value || '';
    CAL.holidays = Array.isArray(cfg.holidays) ? cfg.holidays.filter(r => r && r.mulai && r.selesai) : [];
}

function slotBadgeClass(kind) {
    const tone = {
        ok: 'bg-primary-fixed text-on-primary-fixed',
        full: 'bg-error-container text-on-error-container',
        checking: 'bg-surface-container text-on-surface-variant'
    }[kind] || 'bg-surface-container text-on-surface-variant';
    return 'px-2 py-0.5 rounded-full font-semibold shrink-0 ' + tone;
}

function syncCalendarSelection() {
    const short = $('#calendar-selected-label'), sub = $('#calendar-confirm-sub');
    if (short) short.textContent = CAL.selected ? shortDate(CAL.selected) : '—';
    if (sub) sub.textContent = CAL.selected ? dateLabelShort(CAL.selected) : 'Pilih tanggal pada kalender';
}

function syncCalendarNav() {
    const prev = $('#calendar-prev'), next = $('#calendar-next');
    if (!prev || !next || !CAL.month || !CAL.min) return;
    const first = isoOf(CAL.min);
    prev.disabled = monthIndex(CAL.month) <= monthIndex(first);
    // Libur operasional hanya dikirim untuk ~5 bulan ke depan; batasi navigasi sejauh itu.
    next.disabled = monthIndex(CAL.month) >= monthIndex(first) + 5;
}

function renderCalendar() {
    const grid = $('#calendar-days');
    if (!grid || !CAL.month) return;
    $('#calendar-month-label').textContent = new Intl.DateTimeFormat('id-ID', {
        month: 'long', year: 'numeric'
    }).format(CAL.month);

    const year = CAL.month.getFullYear(), month = CAL.month.getMonth();
    const lastDay = new Date(year, month + 1, 0).getDate();
    const offset = (new Date(year, month, 1).getDay() + 6) % 7; // Senin = kolom pertama
    // Isi penuh 5/6 baris seperti kalender biasa: tanggal bulan sebelah tetap tampil (redup).
    const totalCells = Math.ceil((offset + lastDay) / 7) * 7;
    const cells = [];
    const mutedCell = 'w-full max-w-[2rem] aspect-square rounded-full flex items-center justify-center text-[12px] font-medium text-outline-variant/60';

    for (let i = 0; i < totalCells; i++) {
        const date = new Date(year, month, 1 - offset + i);
        const iso = isoDate(date);
        const outside = date.getMonth() !== month;
        const holiday = isHolidayDate(iso);
        const tooEarly = iso < CAL.min;
        const selected = iso === CAL.selected;
        const day = date.getDate();

        if (selected) {
            cells.push('<button type="button" data-day="' + iso + '" aria-current="date" aria-label="'
                + esc(dateLabelShort(iso)) + ' (dipilih)" class="relative w-full max-w-[2rem] aspect-square rounded-full bg-primary text-on-primary text-[13px] font-bold flex items-center justify-center shadow-md ring-2 ring-primary-fixed/60 active:scale-95 transition-all">'
                + day + '<span class="absolute -bottom-0.5 w-1.5 h-1.5 rounded-full bg-secondary-fixed"></span></button>');
        } else if (outside) {
            cells.push('<span aria-hidden="true" class="' + mutedCell + '">' + day + '</span>');
        } else if (holiday || tooEarly) {
            const reason = holiday ? 'Hari libur operasional' : 'Minimal pemesanan H-7';
            cells.push('<span role="img" aria-label="' + day + ' ' + reason + '" title="' + reason
                + '" class="' + mutedCell + (holiday ? ' line-through' : '') + '">' + day + '</span>');
        } else {
            cells.push('<button type="button" data-day="' + iso + '" aria-label="' + esc(dateLabelShort(iso))
                + '" class="w-full max-w-[2rem] aspect-square rounded-full flex items-center justify-center text-[13px] font-semibold '
                + (date.getDay() === 0 ? 'text-tertiary' : 'text-primary')
                + ' hover:bg-surface-container active:scale-95 transition-all">' + day + '</button>');
        }
    }

    grid.innerHTML = cells.join('');
    $$('[data-day]', grid).forEach(btn => btn.onclick = () => selectCalendarDate(btn.dataset.day));
    syncCalendarNav();
    syncCalendarSelection();
}

function shiftCalendarMonth(step) {
    if (!CAL.month) return;
    const base = isoOf(CAL.min || CAL.selected);
    const target = new Date(CAL.month.getFullYear(), CAL.month.getMonth() + step, 1);
    if (monthIndex(target) < monthIndex(base) || monthIndex(target) > monthIndex(base) + 5) return;
    CAL.month = target;
    renderCalendar();
}

function applySlotState(iso, info) {
    const badge = $('#calendar-slot-badge'), confirm = $('#calendar-confirm');
    if (!badge || !confirm) return;
    const closed = Boolean(info.closed) || isHolidayDate(iso);
    const full = !closed && Number(info.remaining) <= 0;
    const kind = closed || full ? 'full' : 'ok';
    badge.className = slotBadgeClass(kind);
    badge.textContent = closed ? 'Libur' : (full ? 'Kuota Penuh' : 'Sisa ' + Number(info.remaining) + ' Slot');
    confirm.disabled = closed || full;
}

async function updateCalendarAvailability() {
    const badge = $('#calendar-slot-badge'), confirm = $('#calendar-confirm');
    const iso = CAL.selected;
    if (!badge || !confirm || !iso) return;

    const cached = CAL.cache[iso];
    if (cached) {
        applySlotState(iso, cached);
        return;
    }

    badge.className = slotBadgeClass('checking');
    badge.textContent = 'Memeriksa…';
    confirm.disabled = true;
    const ticket = ++CAL.ticket;

    let info;
    try {
        const d = await api('api/catalog.php?tgl_kirim=' + encodeURIComponent(iso));
        info = {
            closed: Boolean(d.tanggal_libur),
            remaining: Number(d.kitchenRemaining) || 0
        };
        CAL.cache[iso] = info;
    } catch (err) {
        if (ticket !== CAL.ticket || iso !== CAL.selected) return;
        badge.className = slotBadgeClass('checking');
        badge.textContent = 'Kuota tidak diketahui';
        confirm.disabled = false;
        return;
    }
    if (ticket !== CAL.ticket || iso !== CAL.selected) return;
    applySlotState(iso, info);
}

function selectCalendarDate(iso) {
    if (!iso || (CAL.min && iso < CAL.min) || isHolidayDate(iso)) return;
    const base = isoOf(iso);
    CAL.selected = iso;
    CAL.month = new Date(base.getFullYear(), base.getMonth(), 1);
    renderCalendar();
    updateCalendarAvailability();
}

function openDateModal() {
    calendarInit();
    const input = $('#catalog-delivery-date');
    const current = input.value && input.value >= CAL.min ? input.value : CAL.min;
    CAL.selected = current;
    const base = isoOf(current);
    CAL.month = new Date(base.getFullYear(), base.getMonth(), 1);

    const root = $('#date-sheet-root');
    if (!root) return;
    root.classList.remove('hidden');
    root.setAttribute('aria-hidden', 'false');
    document.body.classList.add('calendar-open');
    renderCalendar();
    updateCalendarAvailability();
    $('#date-sheet')?.focus();
}

function closeDateModal() {
    const root = $('#date-sheet-root');
    if (!root) return;
    root.classList.add('hidden');
    root.setAttribute('aria-hidden', 'true');
    document.body.classList.remove('calendar-open');
    $('#open-date-picker')?.focus();
}

function confirmDeliveryDate() {
    const confirm = $('#calendar-confirm');
    if (!CAL.selected || confirm?.disabled) return;
    const input = $('#catalog-delivery-date');
    input.value = CAL.selected;
    $('#selected-date-text').textContent = dateLabelShort(CAL.selected);
    closeDateModal();
    refreshCatalog(true);
}

function catalogPage() {
    const search = $('#catalog-search'),
        filters = $$('.category-chip');
    const syncFilters = () => filters.forEach(x => {
        const active = x.dataset.category === category;
        x.classList.toggle('bg-primary', active);
        x.classList.toggle('text-on-primary', active);
        x.classList.toggle('bg-surface-container', !active);
        x.classList.toggle('text-on-surface-variant', !active);
        x.setAttribute('aria-pressed', String(active));
    });

    syncFilters();
    filters.forEach(b => b.onclick = () => {
        if (category === b.dataset.category) return;
        category = b.dataset.category;
        syncFilters();
        refreshCatalog(true);
    });

    search.oninput = () => {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(() => refreshCatalog(true), 200)
    };
    // Bayangan halus di batas atas area menu saat konten mulai tergulir.
    const catalogScroll = $('.catalog-scroll'), catalogPinned = $('.catalog-pinned');
    if (catalogScroll && catalogPinned) {
        const syncScrollState = () => catalogPinned.classList.toggle('is-scrolled', catalogScroll.scrollTop > 4);
        catalogScroll.addEventListener('scroll', syncScrollState, { passive: true });
        syncScrollState();
    }
    $('#checkout-button').onclick = openCart;
    $('#continue-checkout').onclick = () => {
        $('#cart-dialog').close();
        openCheckout()
    };
    $$('[data-close-cart]').forEach(b => b.onclick = () => $('#cart-dialog').close());
    $$('[data-close-detail]').forEach(b => b.onclick = closeDetail);

    const detailDialog = $('#detail-sheet-root'),
        detailContent = $('#detail-content'),
        cartDialog = $('#cart-dialog'),
        checkoutDialog = $('#checkout-dialog');
    detailContent.addEventListener('click', e => {
        if (e.target.closest('.sheet-qty')) e.stopPropagation()
    });
    document.addEventListener('keydown', e => {
        if (e.key !== 'Escape') return;
        if (detailDialog && !detailDialog.classList.contains('hidden')) closeDetail();
        if (!$('#date-sheet-root').classList.contains('hidden')) closeDateModal();
    });
    cartDialog.addEventListener('click', e => {
        if (e.target === cartDialog) cartDialog.close()
    });
    checkoutDialog.addEventListener('click', e => {
        if (e.target === checkoutDialog) checkoutDialog.close()
    });
    $$('[data-close-checkout]').forEach(b => b.onclick = () => checkoutDialog.close());
    $('#address-select').onchange = () => toggleAddressFields();
    $('#checkout-form').onsubmit = submitOrder;
    $('#catalog-delivery-date').onchange = () => {
        $('#selected-date-text').textContent = dateLabelShort($('#catalog-delivery-date').value);
        refreshCatalog();
    };
    $('#open-date-picker').onclick = openDateModal;
    const deliveryCard = $('#delivery-card');
    if (deliveryCard) {
        deliveryCard.onclick = e => {
            if (!e.target.closest('#open-date-picker')) openDateModal();
        };
        deliveryCard.onkeydown = e => {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                openDateModal();
            }
        };
    }
    $('#close-date-sheet').onclick = closeDateModal;
    $('[data-close-date-sheet]').onclick = closeDateModal;
    $('#calendar-prev').onclick = () => shiftCalendarMonth(-1);
    $('#calendar-next').onclick = () => shiftCalendarMonth(1);
    $('#calendar-confirm').onclick = confirmDeliveryDate;
    $('#checkout-form').elements.tgl_kirim.onchange = async e => {
        $('#catalog-delivery-date').value = e.target.value;
        $('#catalog-delivery-date').dispatchEvent(new Event('input', { bubbles: true }));
        try {
            await loadCatalog();
            $('#schedule-note').textContent = catalogClosed
                ? 'Tanggal ini libur. Pesanan untuk tanggal tersebut tidak dapat dikirim.'
                : 'Sisa kapasitas mengikuti tanggal ' + e.target.value + '. Kuota diperiksa kembali saat pesanan dikirim.';
            $('#submit-order').disabled = catalogClosed;
        } catch (err) {
            $('#checkout-error').textContent = err.message
        }
    };

    const selected = $('#catalog-delivery-date').value;
    $('#selected-date-text').textContent = dateLabelShort(selected);
    refreshCatalog();
}
async function refreshCatalog(animate = false) {
    try {
        await loadCatalog(animate);
    } catch (err) {
        const grid = $('#menu-container');
        grid.innerHTML = '<div class="text-center py-8 text-body-sm text-error">' + esc(err.message)
            + ' <button type="button" class="px-3 py-1.5 rounded-full bg-surface-container text-primary font-label-md" data-retry-catalog>Coba lagi</button></div>';
        $('[data-retry-catalog]').onclick = () => refreshCatalog(animate);
    }
}

function updateChefSpotlight(items) {
    if (!Array.isArray(items) || !items.length) return;
    const featured = items.find(p => p.kategori === 'makanan') || items[0];
    const name = $('#chef-name'), price = $('#chef-price'), image = $('#chef-image'), badge = $('#chef-badge'), order = $('#chef-order');
    if (name) name.textContent = featured.nama;
    if (price) price.textContent = money(featured.harga);
    if (image) image.src = imagePath(featured.gambar);
    if (badge) badge.textContent = 'Sisa ' + Number(featured.sisa) + ' porsi';
    if (order) order.onclick = () => showProduct(featured);
}

async function loadCatalog(animate = false) {
    const grid = $('#menu-container');
    grid.classList.remove('category-transition');
    const day = $('#catalog-delivery-date').value;
    const d = await api('api/catalog.php?tgl_kirim=' + encodeURIComponent(day));
    if (day !== $('#catalog-delivery-date').value) return;
    catalogClosed = Boolean(d.tanggal_libur);
    // Simpan sisa kapasitas tanggal ini agar kalender tidak perlu permintaan ulang.
    if (d.tanggal) CAL.cache[d.tanggal] = {
        closed: Boolean(d.tanggal_libur),
        remaining: Number(d.kitchenRemaining) || 0
    };
    $('#delivery-hint').textContent = catalogClosed
        ? 'Tanggal ini merupakan hari libur. Semua kuota menu ditutup; silakan pilih tanggal pengiriman lain.'
        : 'Pilih tanggal untuk melihat menu yang tersedia. Pengiriman paling awal 7 hari dari hari ini.';
    const fullHint = $('#quota-full-hint');
    if (fullHint) {
        const full = Array.isArray(d.habis) ? d.habis : [];
        if (catalogClosed) {
            fullHint.textContent = 'Tanggal ini termasuk hari libur operasional. Pilih tanggal pengiriman lain.';
            fullHint.hidden = false;
        } else if (full.length) {
            const label = new Date(d.tanggal + 'T00:00:00+07:00').toLocaleDateString('id-ID', {
                day: '2-digit', month: 'short', year: 'numeric', timeZone: 'Asia/Jakarta'
            });
            fullHint.textContent = 'Kuota penuh untuk tanggal ' + label + ': '
                + full.map(p => p.nama).join(', ') + '. Pilih tanggal atau menu lain.';
            fullHint.hidden = false;
        } else {
            fullHint.textContent = '';
            fullHint.hidden = true;
        }
    }
    const checkoutButton = $('#checkout-button');
    if (checkoutButton) checkoutButton.disabled = catalogClosed;

    const previous = productCache;
    productCache = Object.fromEntries(d.items.map(p => [p.id, p]));
    const invalid = Object.entries(cart)
        .filter(([id, qty]) => !productCache[id] || qty > Number(productCache[id].sisa))
        .map(([id]) => id);
    for (const id of invalid)
        if (!productCache[id] && previous[id])
            productCache[id] = {...previous[id], sisa: 0, kuota_harian: 0};
    if (invalid.length) toast('Sebagian menu keranjang tidak tersedia untuk tanggal ini. Periksa keranjang sebelum melanjutkan.');

    catalog = d.items.filter(p =>
        (!category || p.kategori === category)
        && (!$('#catalog-search').value.trim()
            || (p.nama + ' ' + p.kode).toLowerCase().includes($('#catalog-search').value.trim().toLowerCase()))
    );
    schedule = d.schedule;
    const count = $('#menu-count');
    if (count) count.textContent = catalog.length + ' Pilihan';
    updateChefSpotlight(d.items);
    grid.innerHTML = catalog.length ? catalog.map((p, i) => {
        const sisa = Number(p.sisa);
        return '<button type="button" class="catalog-card w-full text-left bg-surface-container-lowest rounded-2xl p-3 shadow-[0_2px_12px_rgba(30,43,34,0.04)] flex gap-3.5 items-center transition-all hover:shadow-[0_4px_16px_rgba(30,43,34,0.08)]" data-product="' + Number(p.id) + '" style="--i:' + i + '">'
            + '<div class="relative w-20 h-20 shrink-0"><div class="w-20 h-20 rounded-full overflow-hidden bg-surface-container p-0.5 shadow-sm">'
            + '<img class="w-full h-full object-cover rounded-full" src="' + esc(imagePath(p.gambar)) + '" alt="' + esc(p.nama) + '" onerror="this.src=\'../assets/img/produk/NK-01.png\'">'
            + '</div></div><div class="flex flex-col flex-1 min-w-0 justify-center">'
            + '<span class="px-2 py-0.5 rounded-full bg-surface-container text-on-surface-variant font-label-sm text-label-sm w-fit">' + esc(p.kategori) + '</span>'
            + '<h3 class="font-headline-sm text-headline-sm text-on-surface truncate font-bold mt-1">' + esc(p.nama) + '</h3>'
            + '<p class="text-body-sm text-on-surface-variant mt-0.5">Min. ' + Number(p.min_pesan) + ' porsi · Sisa ' + sisa + ' porsi</p>'
            + '<div class="flex items-center justify-between mt-2"><span class="font-headline-sm text-headline-sm text-primary font-bold">' + money(p.harga) + '</span>'
            + '<span class="w-8 h-8 rounded-full bg-primary-container text-on-primary flex items-center justify-center shadow-sm"><span class="material-symbols-outlined text-[18px]">add</span></span></div>'
            + '</div></button>';
    }).join('') : '<div class="text-body-sm text-on-surface-variant py-8 text-center">Menu tidak ditemukan atau stoknya habis.</div>';

    $$('[data-product]', grid).forEach(card => card.onclick = () =>
        showProduct(catalog.find(p => Number(p.id) === Number(card.dataset.product)))
    );
    renderCart();
    if (animate) requestAnimationFrame(() => {
        grid.classList.add('category-transition');
        setTimeout(() => grid.classList.remove('category-transition'), 750)
    })
}
function closeDetail() {
    const panel = $('#detail-sheet-root');
    if (panel) {
        panel.classList.add('hidden');
        panel.setAttribute('aria-hidden', 'true');
    }
    document.body.classList.remove('sheet-open');
    if (detailOpener && typeof detailOpener.focus === 'function') detailOpener.focus();
    detailOpener = null;
}

function showProduct(product) {
    if (!product) return;
    detailOpener = document.activeElement;
    activeProduct = product;
    const minimum = Number(product.min_pesan), maximum = Number(product.sisa);
    activeQty = Math.min(maximum, Math.max(1, minimum));
    const kuota = Number(product.kuota) || maximum;
    const spicy = /sambal|pedas|balado/i.test(product.nama);
    const soldOut = maximum <= 0;
    const tagText = soldOut ? 'Stok habis' : (spicy ? 'Pedas Sedang' : 'Tersedia');
    const tagIcon = soldOut ? 'block' : (spicy ? 'local_fire_department' : 'check_circle');
    const jadwal = $('#catalog-delivery-date')?.value;
    const jadwalLabel = jadwal
        ? new Intl.DateTimeFormat('id-ID', {day: '2-digit', month: 'short', timeZone: 'Asia/Jakarta'})
            .format(new Date(jadwal + 'T00:00:00+07:00'))
        : '-';
    const stat = (icon, label, value) =>
        '<div class="flex items-center gap-1.5 px-2.5 py-1.5 rounded-xl bg-surface-container/70 border border-outline-variant/30 shrink-0">'
        + '<span class="material-symbols-outlined text-primary text-[16px]">' + icon + '</span>'
        + '<div class="flex flex-col"><span class="text-[9px] text-on-surface-variant uppercase tracking-wider font-semibold">' + label + '</span>'
        + '<span class="text-[11px] font-bold text-on-surface leading-tight">' + value + '</span></div></div>';

    $('#detail-content').innerHTML = `
        <div class="relative overflow-hidden rounded-t-3xl bg-primary text-on-primary pt-3.5 pb-5 px-5">
            <div class="w-full flex justify-center items-center pb-2"><div class="w-10 h-1 bg-white/40 rounded-full"></div></div>
            <div class="flex items-center justify-between relative z-10 mb-3">
                <div class="flex items-center gap-1.5">
                    <span class="bg-white/20 backdrop-blur-md text-white text-[10px] font-bold uppercase tracking-wider px-2.5 py-0.5 rounded-full">${esc(product.kategori)}</span>
                    <span class="bg-secondary-container text-on-secondary-fixed text-[10px] font-bold px-2 py-0.5 rounded-full flex items-center gap-1">
                        <span class="material-symbols-outlined text-[11px]" style="font-variation-settings: 'FILL' 1;">${tagIcon}</span>
                        ${tagText}
                    </span>
                </div>
                <button type="button" aria-label="Tutup" data-close-detail class="w-8 h-8 rounded-full bg-white/20 hover:bg-white/30 text-white backdrop-blur-md flex items-center justify-center transition-colors active:scale-95">
                    <span class="material-symbols-outlined text-[18px]">close</span>
                </button>
            </div>
            <div class="flex items-center justify-between gap-3 relative z-10">
                <div class="flex-1 pr-1">
                    <h2 class="font-headline-sm text-[20px] font-extrabold text-white tracking-tight leading-snug">${esc(product.nama)}</h2>
                    <div class="flex items-baseline gap-1 mt-1">
                        <span class="text-[19px] font-extrabold text-secondary-fixed">${money(product.harga)}</span>
                        <span class="text-[11px] text-white/80 font-medium">/ porsi</span>
                    </div>
                </div>
                <div class="relative shrink-0">
                    <div class="w-24 h-24 rounded-full p-1 bg-white/30 backdrop-blur-sm shadow-xl">
                        <img src="${esc(imagePath(product.gambar))}" alt="${esc(product.nama)}" onerror="this.src='../assets/img/produk/NK-01.png'" class="w-full h-full object-cover object-center rounded-full shadow-inner ring-2 ring-white/60">
                    </div>
                    <span class="absolute -bottom-1.5 -right-1 bg-surface-container-lowest text-primary text-[10px] font-bold px-2 py-0.5 rounded-full shadow-md flex items-center gap-0.5 border border-primary/10">
                        <span class="material-symbols-outlined text-[11px] text-secondary">pie_chart</span> Sisa ${maximum}
                    </span>
                </div>
            </div>
        </div>
        <div class="px-5 pt-3.5 pb-4 flex flex-col gap-3.5 bg-surface-container-lowest">
            <p class="text-[12px] text-on-surface-variant leading-relaxed">Menu pilihan Se.Noesa, dibuat segar setiap hari untuk menemani acara dan waktu makan Anda.</p>
            <div class="flex items-center gap-2 overflow-x-auto pb-0.5">
                ${stat('inventory_2', 'Min. Pesan', minimum + ' Porsi')}
                ${stat('pie_chart', 'Sisa Stok', maximum + ' / ' + kuota)}
                ${stat('event', 'Jadwal Kirim', jadwalLabel)}
            </div>
            <div class="flex items-center justify-between gap-3 pt-1">
                <div class="sheet-qty flex items-center bg-surface-container rounded-2xl p-1 gap-1 border border-outline-variant/30 shadow-inner">
                    <button type="button" id="btn-minus" data-qty="minus" aria-label="Kurangi porsi" class="w-9 h-9 rounded-xl flex items-center justify-center bg-surface-container-lowest text-on-surface hover:bg-surface-container-high transition-colors active:scale-90 shadow-sm">
                        <span class="material-symbols-outlined text-[17px]">remove</span>
                    </button>
                    <div class="flex items-center justify-center min-w-[32px] px-1"><span id="portion-count" class="text-[16px] text-primary font-extrabold">${activeQty}</span></div>
                    <button type="button" id="btn-plus" data-qty="plus" aria-label="Tambah porsi" class="w-9 h-9 rounded-xl flex items-center justify-center bg-primary text-on-primary hover:bg-primary-container transition-colors active:scale-90 shadow-sm">
                        <span class="material-symbols-outlined text-[17px]">add</span>
                    </button>
                </div>
                <button type="button" id="add-to-cart" class="flex-1 h-11 bg-primary hover:bg-primary-container active:scale-[0.98] text-on-primary rounded-2xl flex items-center justify-between px-4 shadow-lg shadow-primary/20 transition-all">
                    <div class="flex flex-col text-left">
                        <span class="text-[10px] text-on-primary-container leading-none font-medium">Total <span id="sheet-total-qty">${activeQty}</span> Porsi</span>
                        <span class="text-[14px] text-on-primary font-extrabold tracking-wide leading-tight" id="sheet-total-price">${money(activeQty * Number(product.harga))}</span>
                    </div>
                    <div class="flex items-center gap-1 bg-white/20 px-2.5 py-1 rounded-xl">
                        <span class="text-[12px] font-bold tracking-wide">Pesan</span>
                        <span class="material-symbols-outlined text-[16px]">arrow_forward</span>
                    </div>
                </button>
            </div>
        </div>`;

    $$('[data-close-detail]', $('#detail-content')).forEach(b => b.onclick = closeDetail);
    const add = $('#add-to-cart'), qtyText = $('#portion-count');
    function setQuantity(value, normalize = false) {
        let quantity = Number(value);
        if (!Number.isFinite(quantity)) { add.disabled = true; return; }
        quantity = Math.floor(quantity);
        if (normalize) quantity = Math.max(minimum, Math.min(maximum, quantity));
        if (quantity < minimum || quantity > maximum) { add.disabled = true; return; }
        activeQty = quantity;
        if (qtyText) qtyText.textContent = quantity;
        const totalQty = $('#sheet-total-qty'), totalPrice = $('#sheet-total-price');
        if (totalQty) totalQty.textContent = quantity;
        if (totalPrice) totalPrice.textContent = money(quantity * Number(product.harga));
        add.disabled = false;
    }
    $$('[data-qty]').forEach(b => b.onclick = e => {
        e.preventDefault();
        e.stopPropagation();
        const step = b.dataset.qty === 'plus' ? 1 : -1;
        if (step < 0 && activeQty <= minimum) {
            toast('Minimal pesan ' + minimum + ' porsi');
            return;
        }
        if (step > 0 && activeQty >= maximum) {
            toast('Sisa stok untuk tanggal ini ' + maximum + ' porsi');
            return;
        }
        setQuantity(activeQty + step, true)
    });
    if (maximum < minimum) {
        add.disabled = true;
        add.innerHTML = '<span class="text-[12px] font-bold">Stok kurang dari minimal pesanan</span>';
    }
    add.onclick = () => {
        if (add.disabled) return;
        const existing = Number(cart[product.id] || 0);
        if (existing + activeQty > maximum) {
            toast('Sisa stok ' + product.nama + ' tinggal ' + maximum + ' porsi');
            return;
        }
        cart[product.id] = existing + activeQty;
        closeDetail();
        renderCart();
        toast(product.nama + ' ditambahkan ke keranjang')
    };
    const panel = $('#detail-sheet-root');
    panel.classList.remove('hidden');
    panel.setAttribute('aria-hidden', 'false');
    document.body.classList.add('sheet-open');
    const sheet = $('#detail-sheet');
    if (sheet) {
        sheet.scrollTop = 0;
        sheet.focus();
    }
}

function cartTotals() {
    let count = 0, total = 0;
    for (const [id, qty] of Object.entries(cart)) {
        const p = productCache[id];
        if (p) {
            count += Number(qty);
            total += Number(qty) * Number(p.harga)
        }
    }
    return {count, total}
}

function renderCart() {
    const {count, total} = cartTotals(),
        bar = $('#floating-cart-bar'),
        badge = $('#nav-cart-count');
    if (bar) bar.classList.toggle('hidden', count === 0);
    document.body.classList.toggle('cart-visible', count > 0);
    if (badge) {
        badge.textContent = count;
        badge.classList.toggle('hidden', count === 0);
    }
    const price = $('#cart-total-price'), items = $('#cart-total-items');
    if (price) price.textContent = money(total);
    if (items) items.textContent = count + ' Porsi';
    const dialogTotal = $('#cart-dialog-total');
    if (dialogTotal) dialogTotal.textContent = money(total)
}

function openCart() {
    if (catalogClosed) {
        toast('Tanggal pengiriman ini libur. Silakan pilih tanggal lain.');
        return;
    }
    if (!Object.keys(cart).length) {
        const dialog = $('#cart-dialog');
        if (dialog && dialog.open) dialog.close();
        else toast('Keranjang masih kosong. Pilih menu terlebih dahulu.');
        return;
    }
    const lines = $('#cart-lines');
    lines.innerHTML = Object.entries(cart).map(([id, qty]) => {
        const p = productCache[id];
        const min = Number(p?.min_pesan || 4);
        return p ? '<div class="cart-line"><img src="' + esc(imagePath(p.gambar)) + '" alt=""><span><b>' + esc(p.nama) + '</b><small>'
            + Number(qty) + ' × ' + money(p.harga) + '</small></span><div class="cart-qty-control"><button type="button" data-dec="' + Number(id) + '" aria-label="Kurangi">&minus;</button><span>' + Number(qty) + '</span><button type="button" data-inc="' + Number(id) + '" aria-label="Tambah">+</button></div><strong>' + money(Number(qty) * Number(p.harga)) + '</strong><button data-remove="' + Number(id) + '" aria-label="Hapus semua">&times;</button></div>': ''
    }).join('');
    $$('[data-dec]').forEach(b => b.onclick = () => {
        const id = b.dataset.dec;
        const p = productCache[id];
        const min = Number(p?.min_pesan || 4);
        if (cart[id] > min) {
            // Jangan pernah menurunkan jumlah ke bawah minimum produk.
            // Jika hasil pengurangan lebih kecil dari minimum, clamp ke minimum.
            if (cart[id] - min < min) toast('Minimum pesanan ' + (p?.nama || 'produk ini') + ' adalah ' + min + ' porsi');
            cart[id] = Math.max(min, cart[id] - min);
        } else {
            delete cart[id];
        }
        renderCart();
        openCart()
    });
    $$('[data-inc]').forEach(b => b.onclick = () => {
        const id = b.dataset.inc;
        const p = productCache[id];
        const max = Number(p?.sisa || 0);
        if ((cart[id] || 0) + Number(p?.min_pesan || 4) <= max) cart[id] = (cart[id] || 0) + Number(p?.min_pesan || 4);
        else toast('Kuota tidak mencukupi');
        renderCart();
        openCart()
    });
    $$('[data-remove]').forEach(b => b.onclick = () => {
        delete cart[b.dataset.remove];
        renderCart();
        openCart()
    });
    if (!Object.keys(cart).length) {
        $('#cart-dialog').close();
        return
    }
    $('#cart-dialog').showModal()
}

function toggleAddressFields() {
    const useSaved = !!$('#address-select').value;
    $('#address-fields').hidden = useSaved;
    $('#address-fields').querySelectorAll('input:not([type=checkbox]),textarea')
        .forEach(x => x.required = !useSaved)
}
async function openCheckout() {
    if (!Object.keys(cart).length) {
        toast('Keranjang masih kosong');
        return
    }

    const form = $('#checkout-form');
    form.elements.nama_pemesan.value = window.customerInfo?.name || '';
    const d = await api('api/orders.php?addresses=1');
    savedAddresses = d.addresses;
    const select = $('#address-select');
    select.innerHTML = '<option value="">+ Tambah alamat baru</option>' + savedAddresses.map(a =>
        '<option value="' + Number(a.id) + '" ' + (a.is_default ? 'selected' : '') + '>'
            + esc(a.label) + ' — ' + esc(a.nama_penerima) + ' (' + esc(a.telepon) + ')'
            + (a.is_default ? ' · Default' : '') + '</option>'
    ).join('');
    const defaultAddress = savedAddresses.find(a => a.is_default);
    if (defaultAddress) {
        form.elements.nama_penerima.value = defaultAddress.nama_penerima;
        form.elements.telepon.value = defaultAddress.telepon;
        form.elements.alamat.value = defaultAddress.alamat
    } else select.value = '';

    toggleAddressFields();
    form.elements.tgl_kirim.min = $('#catalog-delivery-date').min;
    form.elements.tgl_kirim.removeAttribute('max');
    form.elements.tgl_kirim.value = $('#catalog-delivery-date').value;
    form.elements.tgl_kirim.dispatchEvent(new Event('input', {bubbles: true}));
    $('#schedule-note').textContent = catalogClosed
        ? 'Tanggal ini libur. Pesanan untuk tanggal tersebut tidak dapat dikirim.'
        : 'Kuota mengikuti tanggal pengiriman yang dipilih. Pemesanan minimal 7 hari sebelumnya.';
    $('#checkout-error').textContent = '';
    $('#submit-order').disabled = catalogClosed;
    $('#checkout-dialog').showModal()
}

async function submitOrder(e) {
    e.preventDefault();
    const form = e.currentTarget,
        button = $('#submit-order');
    if (catalogClosed) {
        $('#checkout-error').textContent = 'Tanggal pengiriman ini libur. Silakan pilih tanggal lain.';
        return;
    }
    button.disabled = true;
    $('#checkout-error').textContent = '';
    try {
        let addressId = form.elements.alamat_id.value;
        if (!addressId && form.elements.simpan_alamat.checked) {
            const saved = await api('api/orders.php', {
                method: 'POST',
                body: {
                    action: 'save_address',
                    label: form.elements.label_alamat?.value || 'Rumah',
                    nama_penerima: form.elements.nama_penerima.value,
                    telepon: form.elements.telepon.value,
                    alamat: form.elements.alamat.value,
                    is_default: form.elements.jadikan_default.checked
                }
            });
            addressId = saved.id
        }

        const values = Object.fromEntries(new FormData(form));
        values.alamat_id = addressId;
        values.items = Object.entries(cart).map(([id, qty]) => ({id: Number(id), qty: Number(qty)}));
        const d = await api('api/orders.php', {method: 'POST', body: values});
        cart = {};
        location.href = 'riwayat.php?order=' + d.id
    } catch (err) {
        $('#checkout-error').textContent = err.message;
        button.disabled = false
    }
}
const statusLabels = {
    menunggu_pembayaran: 'Menunggu pembayaran', 
    menunggu_validasi: 'Menunggu verifikasi', 
    diproses: 'Diproses',
    selesai: 'Selesai', 
    ditolak: 'Ditolak',
    dibatalkan: 'Dibatalkan'
};
function statusProgress(status) {
    const steps = ['Pesanan', 'Pembayaran', 'Verifikasi', 'Diproses', 'Selesai'];
    if (status === 'dibatalkan' || status === 'ditolak') {
        return '<div class="detail-status-line"><span class="detail-step" style="color:var(--red); font-weight:700;">Pesanan ' + statusLabels[status] + '</span></div>';
    }
    const complete = status === 'selesai' ? 5: status === 'diproses' ? 4: status === 'menunggu_validasi' ? 3: 1;
    return '<div class="detail-status-line">' + steps.map((s, i) => '<span class="detail-step ' + (i < complete ? 'done': '')
        + '">' + s + '</span>').join('') + '</div>'
}

let historyStatus = '', historyTimer, paymentExpiryTimer = null;
function historyPage() {
    const dialog = $('#history-dialog');
    $$('[data-close-history]').forEach(b => b.onclick = () => {
        clearTimeout(paymentExpiryTimer);
        dialog.close();
    });
    dialog.addEventListener('click', e => {
        if (e.target === dialog) {
            clearTimeout(paymentExpiryTimer);
            dialog.close();
        }
    });
    $$('#history-tabs button').forEach(b => b.onclick = () => {
        $$('#history-tabs button').forEach(x => {
            const active = x === b;
            x.classList.toggle('active', active);
            x.classList.toggle('bg-primary', active);
            x.classList.toggle('text-on-primary', active);
            x.classList.toggle('bg-surface-container', !active);
            x.classList.toggle('text-on-surface-variant', !active);
        });
        historyStatus = b.dataset.status;
        loadHistory()
    });
    loadHistory();
    const id = new URLSearchParams(location.search).get('order');
    if (id) setTimeout(() => openHistoryDetail(Number(id)), 350)
}

async function loadHistory() {
    let d;
    try {
        d = await api('api/orders.php' + (historyStatus ? '?status=' + encodeURIComponent(historyStatus): ''));
    } catch (err) {
        $('#history-list').innerHTML = '<div class="empty-state" role="alert">Riwayat pesanan belum dapat dimuat. ' + esc(err.message) + '</div>';
        return;
    }
    $('#history-list').innerHTML = d.orders.length ? d.orders.map((o, i) => {
        const first = o.items[0] || {};
        return '<button type="button" class="history-card w-full text-left bg-surface-container-lowest rounded-2xl p-4 shadow-[0_2px_12px_rgba(30,43,34,0.04)] hover:shadow-[0_4px_16px_rgba(30,43,34,0.08)] transition-all" data-order="' + Number(o.id) + '" style="--i:' + i + '">'
            + '<div class="flex items-start justify-between gap-3"><div class="min-w-0"><span class="font-headline-sm text-headline-sm text-primary font-bold">' + esc(o.kode) + '</span>'
            + '<p class="text-body-sm text-on-surface-variant mt-1">Dibuat ' + dateLabel(o.created_at) + ' · Kirim ' + dateLabel(o.tgl_kirim) + '</p>'
            + '<p class="text-body-sm text-on-surface-variant">' + (o.status_updated_at ? 'Status sejak ' + dateLabel(o.status_updated_at, true) : 'Waktu perubahan status belum tercatat') + '</p></div>'
            + '<span class="status-label ' + esc(o.status) + ' px-2.5 py-1 rounded-full font-label-sm text-label-sm shrink-0">'
            + esc(statusLabels[o.status] || o.status) + '</span></div>'
            + (o.status === 'ditolak' && o.alasan_penolakan ? '<p class="text-body-sm text-error mt-2"><b>Alasan penolakan:</b> ' + esc(o.alasan_penolakan) + '</p>' : '')
            + '<div class="flex items-center gap-3 border-t border-surface-container mt-3 pt-3">' + (first.gambar ? '<img class="w-11 h-11 rounded-xl object-cover bg-surface-container" src="'
            + esc(imagePath(first.gambar)) + '" alt="">' : '<span class="w-11 h-11 rounded-xl bg-surface-container flex items-center justify-center text-on-surface-variant">&#9634;</span>')
            + '<span class="min-w-0"><b class="block font-label-md text-label-md text-on-surface truncate">' + esc(first.nama || 'Pesanan Se.Noesa')
            + '</b><small class="text-body-sm text-on-surface-variant">' + Number(first.qty || o.pax) + ' porsi' + (o.items.length > 1 ? ' + ' + (o.items.length - 1) + ' menu lainnya' : '') + '</small></span></div>'
            + (o.reschedule_pending ? '<p class="text-body-sm text-secondary mt-2">Menunggu konfirmasi jadwal</p>' : '')
            + '<div class="flex items-center justify-between border-t border-surface-container mt-3 pt-3"><span><small class="block font-label-sm text-label-sm text-on-surface-variant">TOTAL</small><strong class="font-headline-sm text-headline-sm text-primary">' + money(o.total) + '</strong></span>'
            + '<span class="font-label-md text-label-md text-primary flex items-center gap-1">Detail <span class="material-symbols-outlined text-[16px]">arrow_forward</span></span></div></button>'
    }).join(''): '<div class="text-body-sm text-on-surface-variant py-8 text-center">Belum ada pesanan pada kategori ini.</div>';
    $$('[data-order]').forEach(card => card.onclick = () => openHistoryDetail(Number(card.dataset.order)))
}

function dateLabel(value, withTime = false) {
    if (value === null || value === undefined) return '-';
    const d = new Date(value);
    return d.toLocaleString('id-ID', withTime ? {
        day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit', timeZone: 'Asia/Jakarta'
    }: {
        day: '2-digit', month: 'short', year: 'numeric', timeZone: 'Asia/Jakarta'
    })
}

async function openHistoryDetail(id) {
    const d = await api('api/orders.php?id=' + id);
    const itemsArray = Array.isArray(d.items) ? d.items : [];
    const items = itemsArray.map(i => '<div class="detail-menu-line"><span><b>' + esc(i.nama !== undefined && i.nama !== null ? i.nama : 'Menu') + '</b><small>' + Number(i.qty)
        + ' porsi × ' + money(i.harga !== undefined && i.harga !== null ? Number(i.harga) : 0) + '</small></span><strong>' + money(i.subtotal !== undefined && i.subtotal !== null ? Number(i.subtotal) : 0) + '</strong></div>').join('');
    const menuNoticeLabels = {
        menunggu_respons: 'Menunggu responsmu',
        menunggu_konfirmasi: 'Menunggu konfirmasi Admin',
        menunggu_tindak_lanjut: 'Menunggu tindak lanjut Admin',
        dikonfirmasi: 'Perubahan dikonfirmasi Admin',
        selesai: 'Tindak lanjut selesai',
    };
    const menuNotices = Array.isArray(d.menu_notifications) ? d.menu_notifications : [];
    const menuNoticeSection = menuNotices.length
        ? '<section class="detail-section"><h3>Notifikasi perubahan menu</h3>' + menuNotices.map(notice =>
            '<div class="detail-menu-line"><span><b>' + esc(notice.nama_produk_snapshot || notice.nama_produk_lama_snapshot)
            + '</b><small>' + esc(menuNoticeLabels[notice.status_respons] || notice.status_respons || '')
            + ' · ' + esc(notice.catatan_admin || '') + '</small></span><a class="customer-button secondary" href="menu-notifications.php?notice_id='
            + Number(notice.id) + '">Lihat dan tindak lanjuti</a></div>'
        ).join('') + '</section>'
        : '';
    const proof = d.bukti_bayar ? '<img class="uploaded-proof" src="../' + esc(d.bukti_bayar).replace(/^\/+/, '')
        + '" alt="Bukti pembayaran">' : '';
    const paymentData = await api('api/payment-methods.php');
    const qris = paymentData.items.length ? '<section class="detail-section"><h3>Pembayaran</h3>' + paymentData.items.map(method => {
        return '<div class="payment-method-card"><span>' + esc(method.nama) + '</span><div class="qris-display"><img src="'
            + esc(imagePath(method.gambar)) + '" alt="' + esc(method.nama) + '" onerror="this.src=\'../assets/img/produk/NK-01.png\'"></div></div>'
    }).join('') + '</section>': '<section class="detail-section"><h3>Pembayaran</h3><div class="payment-method-card"><span>Metode Pembayaran</span><div class="qris-display"><div class="qris-placeholder"><strong>Belum tersedia</strong><small>Silakan hubungi owner untuk informasi pembayaran.</small></div></div></div></section>';
    const canUploadProof = d.can_upload_proof === true, hasProof = !!d.bukti_bayar;
    const proofForm = canUploadProof ? '<form class="proof-upload" id="proof-form" ' + (hasProof ? 'hidden': '') + '><label for="payer-name">Atas nama</label><input id="payer-name" name="nama_pembayar" type="text" maxlength="100" value="'
        + esc(d.nama_pembayar || d.nama_pemesan || '') + '" placeholder="Nama yang membayar" required><label for="proof-file">'
        + (hasProof ? 'Pilih bukti pembayaran baru': 'Upload bukti transfer') + '</label><input id="proof-file" name="proof" type="file" accept="image/jpeg,image/png,image/webp" required><small class="upload-hint">Format JPG, PNG, WebP · Maksimal 2 MB</small><input type="hidden" name="id" value="'
        + Number(d.id) + '"><input type="hidden" name="action" value="upload_proof"><button class="customer-button primary">'
        + (hasProof ? 'Kirim bukti baru': 'Kirim bukti pembayaran') + '</button><p class="form-error" id="proof-error" role="alert"></p></form>': '';

    const expiredAdminContact = `
        <div class="payment-expired-notice">
            <b>Tenggat waktu upload bukti pembayaran telah berakhir.</b>
            <p>Jika Anda sudah melakukan pembayaran namun belum sempat mengunggah bukti transfer, silakan segera hubungi Admin dengan menyertakan kode pesanan <b>${esc(d.kode)}</b> dan bukti transfer Anda.</p>
            <p class="admin-wa-text">Nomor WA Admin: 08123456789</p>
        </div>
    `;

    const isPaymentExpiredWithoutProof = !hasProof && (
        (d.status === 'menunggu_pembayaran' && !canUploadProof) ||
        d.status === 'dibatalkan'
    );

    const proofSection = canUploadProof
        ? '<section class="detail-section"><h3>Bukti Pembayaran</h3>'
            + (hasProof
                ? '<div class="proof-display-row">' + proof + '<button type="button" class="customer-button secondary replace-proof" id="replace-proof">Ganti Bukti</button></div>'
                : proof)
            + (d.status === 'menunggu_pembayaran'
                ? '<p class="muted">Unggah bukti transfer sebelum ' + dateLabel(d.payment_expires_at, true) + ' WIB. Pesanan tanpa bukti akan dibatalkan otomatis.</p>'
                : '')
            + proofForm + '</section>'
        : (proof
            ? '<section class="detail-section"><h3>Bukti Pembayaran</h3>' + proof + '</section>'
            : (isPaymentExpiredWithoutProof
                ? '<section class="detail-section"><h3>Bukti Pembayaran</h3>' + expiredAdminContact + '</section>'
                : ''));

    const deliveryPhotos = Array.isArray(d.foto_kiriman_list)
        ? d.foto_kiriman_list
        : d.foto_kiriman
          ? [d.foto_kiriman]
          : [];
    const deliveryPhotoSection = deliveryPhotos.length
        ? '<section class="detail-section"><h3>Foto Siap Kirim</h3><div class="dispatch-photos">' + deliveryPhotos.map(path => {
            const photoUrl = /^https?:\/\//.test(path) || path.startsWith('/')
                ? path
                : '../' + path.replace(/^\.\//, '');
            return '<a class="dispatch-photo-link" href="' + esc(photoUrl) + '" target="_blank" rel="noopener"><img class="uploaded-proof" src="'
                + esc(photoUrl) + '" alt="Foto pesanan siap kirim"></a>';
        }).join('') + '</div></section>'
        : '';

    const refundNotice = 'Jika sudah mentransfer, refund penuh ditangani Admin di luar aplikasi. Hubungi Admin untuk mengurus refund.';
    const offer = d.reschedule_offer;
    const offerPending = d.can_respond_reschedule === true;
    const offerPanel = offer ? '<section class="detail-section reschedule-offer" role="status"><h3>'
        + (offer.status_tawaran === 'menunggu' ? 'Tawaran jadwal pengganti' : 'Riwayat tawaran jadwal')
        + '</h3><p><b>Alasan:</b> ' + esc(offer.alasan) + '</p><div class="detail-schedule"><span>Tanggal sebelumnya<b>'
        + dateLabel(offer.tanggal_asal) + '</b></span><span>Tanggal usulan<b>' + dateLabel(offer.tanggal_usulan)
        + '</b></span></div>' + (offer.status_tawaran === 'menunggu'
            ? '<p>Tanggapi sebelum ' + dateLabel(offer.berlaku_sampai, true) + ' WIB. Untuk pesanan yang menunggu pembayaran, tenggat pembayaran satu jam tetap berlaku.</p>'
                + '<button type="button" class="customer-button primary" id="accept-reschedule">Terima jadwal</button> '
                + '<button type="button" class="customer-button danger" id="reject-reschedule">Tolak jadwal</button><p class="form-error" id="reschedule-error" role="alert"></p>'
            : '<p>Status tawaran: ' + esc(offer.status_tawaran) + '</p>') + '</section>' : '';
    const isStatusCancellable = !offerPending && ['menunggu_pembayaran', 'menunggu_validasi', 'diproses'].includes(d.status);
    const cancelLimitDate = d.cancel_before ? new Date(new Date(d.cancel_before).getTime() - 1) : null;
    const cancelLimitLabel = cancelLimitDate ? cancelLimitDate.toLocaleDateString('id-ID', {
        day: '2-digit', month: 'short', year: 'numeric', timeZone: 'Asia/Jakarta'
    }) : 'belum tersedia';
    const jakartaCalendar = new Intl.DateTimeFormat('sv-SE', { timeZone: 'Asia/Jakarta' });
    const daysLeftToCancel = cancelLimitDate ? Math.max(0, Math.round((Date.parse(jakartaCalendar.format(cancelLimitDate))
        - Date.parse(jakartaCalendar.format(new Date()))) / 86400000)) : 0;
    let cancelSection = '';
    if (isStatusCancellable) {
        if (d.can_cancel === true) {
            cancelSection = `
                <div style="margin-top:24px; padding-top:16px; border-top:1px solid var(--line); text-align:center;">
                    <button type="button" class="customer-button danger" id="cancel-order-btn" style="width:100%; background:var(--red); color:#fff; border:0; margin-bottom:12px;">Batalkan Pesanan</button>
                    <div style="background:var(--soft); padding:12px; border-radius:10px; font-size:11px; line-height:1.5; color:var(--brown); text-align:left;">
                        <b>Syarat Pembatalan:</b><br>
                        Pesanan hanya dapat dibatalkan maksimal <b>2 hari (H-2)</b> sebelum tanggal pengiriman.<br>
                        Batas akhir pembatalan: <b>${cancelLimitLabel}, akhir hari WIB</b>.<br>
                        Sisa waktu Anda untuk membatalkan: <b>${daysLeftToCancel} hari lagi</b>.<br>
                        ${refundNotice}
                    </div>
                </div>
            `;
        } else {
            cancelSection = `
                <div style="margin-top:24px; padding-top:16px; border-top:1px solid var(--line);">
                    <div style="background:#fdf2f2; border:1px solid #f5c6c6; padding:12px; border-radius:10px; font-size:11px; line-height:1.5; color:var(--red); text-align:center;">
                        <b>Batas Waktu Pembatalan Terlewati</b><br>
                        Pesanan tidak dapat dibatalkan karena batas H-2 sudah terlewati atau tanggal pengiriman belum tersedia.
                    </div>
                </div>
            `;
        }
    } else if (d.status === 'dibatalkan' && hasProof) {
        cancelSection = '<section class="detail-section"><h3>Refund</h3><p>' + refundNotice + '</p></section>';
    }

    const rejectionNotice = d.status === 'ditolak' && d.alasan_penolakan
        ? '<section class="rejection-notice" role="alert"><b>Pesanan ditolak</b><p>Alasan: ' + esc(d.alasan_penolakan) + '</p></section>'
        : '';
    $('#history-detail').innerHTML = '<div class="detail-product"><p class="eyebrow">DETAIL PESANAN</p><h2>' + esc(d.kode)
        + '</h2><span class="status-label ' + esc(d.status) + '">' + esc(statusLabels[d.status] || d.status) + '</span><p class="history-date status-changed-at">'
        + (d.status_updated_at ? 'Status sejak ' + dateLabel(d.status_updated_at, true): 'Waktu perubahan status belum tercatat')
        + '</p>' + statusProgress(d.status) + rejectionNotice + offerPanel + '<section class="detail-section"><h3>Pesanan</h3>' + items + '</section><section class="detail-section"><h3>Alamat pengiriman</h3><div class="detail-schedule"><span>'
        + esc(d.nama_pemesan) + ' · ' + esc(d.telepon_penerima || '') + '<b>' + esc(d.alamat_pengiriman || 'Alamat belum tercatat')
        + '</b></span><span>Tanggal kirim<b>' + dateLabel(d.tgl_kirim) + '</b></span></div></section>' + qris + '<div class="detail-total"><span>Total</span><b>'
        + (d.total !== undefined && d.total !== null ? money(d.total) : 'Rp 0') + '</b></div>' 
        + menuNoticeSection
        + proofSection 
        + deliveryPhotoSection 
        + cancelSection 
        + '</div>';
        
    $('#history-dialog').showModal();

    // --- AUTO REFRESH SAAT WAKTU PEMBAYARAN HABIS ---
    clearTimeout(paymentExpiryTimer);
    if (d.status === 'menunggu_pembayaran' && canUploadProof && d.payment_expires_at) {
        const remainingMs = new Date(d.payment_expires_at).getTime() - Date.now();
        if (remainingMs > 0) {
            paymentExpiryTimer = setTimeout(() => {
                if ($('#history-dialog')?.open) {
                    openHistoryDetail(id);
                    loadHistory();
                }
            }, remainingMs + 500);
        }
    }
    // -----------------------------------------------
    for (const decision of ['accept', 'reject']) {
        const button = $('#'+ decision + '-reschedule');
        if (!button) continue;
        button.onclick = async () => {
            const accepted = decision === 'accept';
            if (!confirm(accepted
                ? 'Terima jadwal pengiriman pengganti ini? Ketersediaan akan diperiksa ulang.'
                : 'Tolak jadwal ini dan batalkan pesanan? ' + (hasProof ? refundNotice : ''))) return;
            button.disabled = true;
            try {
                await api('api/orders.php', { method: 'POST', body: {
                    action: 'respond_reschedule', id: d.id, decision,
                    offer_version: offer.dibuat_at
                }});
                toast(accepted ? 'Jadwal pengiriman diperbarui' : 'Pesanan dibatalkan');
                await openHistoryDetail(id);
                loadHistory();
            } catch (err) {
                const error = $('#reschedule-error');
                if (error) error.textContent = err.message;
                button.disabled = false;
            }
        };
    }
    
    // --- EVENT LISTENER UNTUK TOMBOL BATAL ---
    const cancelBtn = $('#cancel-order-btn');
    if (cancelBtn) {
        cancelBtn.onclick = async () => {
            if (!confirm('Batalkan pesanan ini? Tindakan ini tidak dapat dikembalikan. ' + refundNotice)) return;
            
            cancelBtn.disabled = true;
            cancelBtn.textContent = 'Membatalkan...';
            try {
                await api('api/orders.php', { method: 'POST', body: { action: 'cancel_order', id: d.id } });
                toast('Pesanan berhasil dibatalkan');
                await openHistoryDetail(id); // Refresh detail
                loadHistory(); // Refresh list
            } catch (err) {
                toast(err.message);
                cancelBtn.disabled = false;
                cancelBtn.textContent = 'Batalkan Pesanan';
            }
        };
    }
    // -----------------------------------------

    const form = $('#proof-form');
    const replaceButton = $('#replace-proof');
    if (replaceButton) replaceButton.onclick = () => {
        replaceButton.hidden = true;
        form.hidden = false
    };
    if (form) form.onsubmit = async e => {
        e.preventDefault();
        const button = form.querySelector('button');
        button.disabled = true;
        try {
            const body = new FormData(form);
            await api('api/orders.php', { method: 'POST', body });
            toast('Bukti pembayaran berhasil dikirim');
            await openHistoryDetail(id);
            loadHistory()
        } catch (err) {
            $('#proof-error').textContent = err.message;
            button.disabled = false
        }
    };
}

const page = document.body.dataset.page;
wireLogout();
const navCart = $('#nav-cart');
if (navCart) navCart.onclick = () => {
    if (!Object.keys(cart).length && page !== 'catalog') {
        location.href = 'index.php';
        return;
    }
    openCart();
};
if (page === 'catalog') catalogPage();
if (page === 'history') historyPage();

/* ==========================================================================
   FITUR CEK KETERSEDIAAN MENU DI CHECKOUT
   ========================================================================== */
document.addEventListener("DOMContentLoaded", function () {
    const checkoutForm = $('#checkout-form');
    const checkoutDateInput = checkoutForm?.elements?.tgl_kirim || document.getElementById('checkout-date');
    if (!checkoutDateInput) return;

    const scheduleNote = document.getElementById('schedule-note');
    if (scheduleNote) scheduleNote.style.display = 'none';

    let alertBox = document.getElementById('checkout-quota-alert');
    if (!alertBox) {
        alertBox = document.createElement('div');
        alertBox.id = 'checkout-quota-alert';
        alertBox.style.cssText = 'display:none; margin:10px 0 15px; padding:12px 14px; border-radius:12px; font-size:12px; line-height:1.5; border:1px solid transparent;';
        const dateLabel = checkoutDateInput.closest('.date-field') || checkoutDateInput.closest('label') || checkoutDateInput;
        dateLabel.parentNode.insertBefore(alertBox, dateLabel.nextSibling);
    }

    async function cekKetersediaanMenu(selectedDate) {
        const cartIds = Object.keys(cart).filter(id => Number(cart[id]) > 0);
        if (!selectedDate) return;

        alertBox.style.display = 'block';
        alertBox.style.background = '#f7f8f7';
        alertBox.style.borderColor = '#e4ece5';
        alertBox.style.color = '#666';
        alertBox.innerHTML = '⏳ Memeriksa ketersediaan menu...';

        try {
            const d = await api('api/catalog.php?tgl_kirim=' + encodeURIComponent(selectedDate));

            if (d.tanggal_libur) {
                alertBox.style.background = '#fff3cd';
                alertBox.style.borderColor = '#ffeeba';
                alertBox.style.color = '#856404';
                alertBox.innerHTML = '<b>⚠️ Tanggal Libur</b><br>Se.Noesa tidak beroperasi pada tanggal ini. Silakan ganti tanggal pengiriman.';
                return;
            }

            if (cartIds.length === 0) {
                alertBox.style.background = '#e0f2fe';
                alertBox.style.borderColor = '#bae6fd';
                alertBox.style.color = '#0369a1';
                alertBox.innerHTML = '<b>ℹ️ Informasi</b><br>Keranjang masih kosong. Silakan pilih menu terlebih dahulu.';
                return;
            }

            const items = Array.isArray(d.items) ? d.items : [];
            let hasError = false;
            let listHtml = '<div style="font-weight:600; margin-bottom:6px; color:#333;">Status Menu di Keranjang:</div>';
            listHtml += '<ul style="margin:0; padding:0; list-style:none; display:grid; gap:6px;">';

            for (const id of cartIds) {
                const qty = Number(cart[id]);
                const produk = items.find(p => String(p.id) === String(id));
                const fallbackName = productCache[id]?.nama || 'Menu #' + id;

                if (!produk) {
                    hasError = true;
                    listHtml += `<li style="display:flex; gap:6px; color:#dc3545;"><span>❌</span><span><b>${esc(fallbackName)}</b> tidak tersedia di tanggal ini.</span></li>`;
                    continue;
                }

                const sisa = Number(produk.sisa ?? produk.kuota ?? 0);
                if (sisa <= 0) {
                    hasError = true;
                    listHtml += `<li style="display:flex; gap:6px; color:#dc3545;"><span>❌</span><span><b>${esc(produk.nama)}</b> (Habis)</span></li>`;
                } else if (qty > sisa) {
                    hasError = true;
                    listHtml += `<li style="display:flex; gap:6px; color:#dc3545;"><span>❌</span><span><b>${esc(produk.nama)}</b> sisa ${sisa} porsi (Anda pesan ${qty}).</span></li>`;
                } else {
                    listHtml += `<li style="display:flex; gap:6px; color:#155724;"><span>✅</span><span><b>${esc(produk.nama)}</b> tersedia (Sisa: ${sisa} porsi).</span></li>`;
                }
            }
            listHtml += '</ul>';

            alertBox.style.background = hasError ? '#fff5f5' : '#f0fdf4';
            alertBox.style.borderColor = hasError ? '#feb2b2' : '#bbf7d0';
            alertBox.style.color = hasError ? '#c53030' : '#276749';
            alertBox.innerHTML = listHtml;
        } catch (error) {
            alertBox.style.background = '#fef2f2';
            alertBox.style.borderColor = '#fecaca';
            alertBox.style.color = '#991b1b';
            alertBox.innerHTML = `❌ Gagal memeriksa menu: <b>${esc(error.message)}</b>.`;
        }
    }

    checkoutDateInput.addEventListener('change', () => cekKetersediaanMenu(checkoutDateInput.value));

    const checkoutDialog = document.getElementById('checkout-dialog');
    if (checkoutDialog) {
        new MutationObserver(() => {
            if (checkoutDialog.hasAttribute('open') && checkoutDateInput.value) {
                cekKetersediaanMenu(checkoutDateInput.value);
            }
        }).observe(checkoutDialog, { attributes: true, attributeFilter: ['open'] });
    }
});