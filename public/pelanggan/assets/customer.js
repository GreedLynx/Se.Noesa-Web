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
let selectedAddressId = null;
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
    $('#continue-checkout').onclick = () => directCheckout();
    $$('[data-close-cart]').forEach(b => b.onclick = () => $('#cart-dialog').close());
    const addressChange = $('#cart-address-change');
    if (addressChange) addressChange.onclick = () => openAddressSheet();
    $$('[data-close-address]').forEach(b => b.onclick = () => {
        const sheet = $('#address-sheet');
        if (sheet && sheet.open) sheet.close()
    });
    const addressUse = $('#address-use');
    if (addressUse) addressUse.onclick = () => useSelectedAddress();
    const addressAddNew = $('#address-add-new');
    if (addressAddNew) addressAddNew.onclick = () => openAddressForm();
    $$('[data-close-af]').forEach(b => b.onclick = () => {
        const f = $('#address-form-sheet');
        if (f && f.open) f.close()
    });
    const afSave = $('#af-save');
    if (afSave) afSave.onclick = () => saveAddressForm();
    const afReset = $('#af-reset');
    if (afReset) afReset.onclick = () => resetAddressForm();
    $$('#af-chips .asf-chip').forEach(chip => {
        chip.onclick = () => setFormLabel(chip.dataset.label)
    });
    const afSheet = $('#address-form-sheet');
    if (afSheet) afSheet.addEventListener('click', e => {
        if (e.target === afSheet) afSheet.close()
    });
    const addressSheet = $('#address-sheet');
    if (addressSheet) addressSheet.addEventListener('click', e => {
        if (e.target === addressSheet) addressSheet.close()
    });
    $$('[data-close-detail]').forEach(b => b.onclick = closeDetail);

    const detailDialog = $('#detail-sheet-root'),
        detailContent = $('#detail-content'),
        cartDialog = $('#cart-dialog');
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

function setText(selector, value) {
    const el = $(selector);
    if (el) el.textContent = value
}

async function ensureAddresses() {
    if (savedAddresses.length) return;
    try {
        const d = await api('api/orders.php?addresses=1');
        savedAddresses = Array.isArray(d.addresses) ? d.addresses : [];
        renderAddressCard()
    } catch (e) {
        renderAddressCard()
    }
}

function currentAddress() {
    if (!savedAddresses.length) return null;
    return savedAddresses.find(x => Number(x.id) === Number(selectedAddressId))
        || savedAddresses.find(x => x.is_default)
        || savedAddresses[0]
}

function renderAddressCard() {
    const body = $('#cart-address-body'), badge = $('#cart-address-badge');
    if (!body) return;
    const a = currentAddress();
    if (badge) badge.hidden = !a || !a.is_default;
    if (!a) {
        body.innerHTML = '<p class="cart-address-empty">Belum ada alamat tersimpan. Tambahkan saat checkout.</p>';
        return
    }
    body.innerHTML = '<div class="cart-address-line"><b>' + esc(a.nama_penerima) + '</b>'
        + '<span>(' + esc(a.telepon) + ')</span></div>'
        + '<p class="cart-address-text">' + esc(a.alamat) + (a.label ? ' (' + esc(a.label) + ')' : '') + '</p>'
        + '<div class="cart-address-foot"><span class="material-symbols-outlined text-[16px]">check_circle</span>'
        + '<span>' + (a.is_default ? 'Alamat pengiriman utama' : 'Alamat pengiriman dipilih') + '</span></div>'
}

function addressIcon(label) {
    const l = String(label || '').toLowerCase();
    if (l.includes('rumah')) return 'home';
    if (l.includes('kantor') || l.includes('office')) return 'corporate_fare';
    if (l.includes('tua') || l.includes('ibu') || l.includes('ayah') || l.includes('keluarga')) return 'family_restroom';
    return 'place'
}

async function openAddressSheet() {
    await ensureAddresses();
    if (!selectedAddressId) {
        const def = savedAddresses.find(x => x.is_default) || savedAddresses[0];
        selectedAddressId = def ? def.id : null
    }
    renderAddressSheet();
    const sheet = $('#address-sheet');
    if (sheet && !sheet.open) sheet.showModal()
}

function renderAddressSheet() {
    const list = $('#address-sheet-list');
    if (!list) return;
    if (!savedAddresses.length) {
        list.innerHTML = '<p class="address-sheet-empty">Belum ada alamat tersimpan. Tambah alamat baru untuk melanjutkan.</p>';
        return
    }
    list.innerHTML = savedAddresses.map(a => {
        const selected = Number(a.id) === Number(selectedAddressId);
        return '<div class="address-option' + (selected ? ' is-selected' : '') + '" data-address-id="' + Number(a.id) + '">'
            + '<div class="address-option-top">'
                + '<div class="address-option-title">'
                    + '<span class="material-symbols-outlined text-[18px]">' + addressIcon(a.label) + '</span>'
                    + '<span class="address-option-label">' + esc(a.label || 'Alamat') + '</span>'
                    + (a.is_default ? '<span class="address-option-badge">Utama</span>' : '')
                + '</div>'
                + '<span class="address-radio">' + (selected ? '<span class="material-symbols-outlined text-[14px]">check</span>' : '') + '</span>'
            + '</div>'
            + '<div class="address-option-body">'
                + '<div class="address-option-line"><b>' + esc(a.nama_penerima) + '</b><span>(' + esc(a.telepon) + ')</span></div>'
                + '<p class="address-option-text">' + esc(a.alamat) + '</p>'
            + '</div>'
            + (!a.is_default ? '<div class="address-option-foot"><button type="button" class="address-setdefault" data-set-default="' + Number(a.id) + '">Jadikan Utama</button></div>' : '')
        + '</div>'
    }).join('');
    $$('#address-sheet-list .address-option').forEach(el => {
        el.onclick = e => {
            if (e.target.closest('[data-set-default]')) return;
            selectedAddressId = Number(el.dataset.addressId);
            renderAddressSheet()
        }
    });
    $$('#address-sheet-list [data-set-default]').forEach(b => {
        b.onclick = async e => {
            e.stopPropagation();
            b.disabled = true;
            try {
                await api('api/orders.php', {
                    method: 'POST',
                    body: {action: 'set_default_address', id: Number(b.dataset.setDefault)}
                });
                const id = Number(b.dataset.setDefault);
                savedAddresses.forEach(x => x.is_default = Number(x.id) === id);
                selectedAddressId = id;
                renderAddressSheet();
                renderAddressCard()
            } catch (err) {
                b.disabled = false;
                toast(err.message)
            }
        }
    })
}

function useSelectedAddress() {
    const a = currentAddress();
    if (a) selectedAddressId = a.id;
    renderAddressCard();
    const sheet = $('#address-sheet');
    if (sheet && sheet.open) sheet.close()
}

let afLabel = 'Rumah';

function setFormLabel(label) {
    afLabel = label;
    $$('#af-chips .asf-chip').forEach(c => c.classList.toggle('is-active', c.dataset.label === label))
}

function resetAddressForm() {
    const name = $('#af-name'), phone = $('#af-phone'), addr = $('#af-address'), notes = $('#af-notes'),
        def = $('#af-default'), err = $('#af-error');
    if (name) name.value = window.customerInfo?.name || '';
    if (phone) phone.value = '';
    if (addr) addr.value = '';
    if (notes) notes.value = '';
    if (def) def.checked = false;
    if (err) err.textContent = '';
    setFormLabel('Rumah')
}

function openAddressForm() {
    const sheet = $('#address-sheet');
    if (sheet && sheet.open) sheet.close();
    resetAddressForm();
    const form = $('#address-form-sheet');
    if (form && !form.open) form.showModal()
}

async function saveAddressForm() {
    const name = ($('#af-name')?.value || '').trim(),
        phoneRaw = ($('#af-phone')?.value || '').trim(),
        addr = ($('#af-address')?.value || '').trim(),
        notes = ($('#af-notes')?.value || '').trim(),
        isDefault = !!$('#af-default')?.checked,
        err = $('#af-error');
    const digits = phoneRaw.replace(/\D/g, '');
    if (!name) {
        if (err) err.textContent = 'Nama penerima wajib diisi.';
        return
    }
    if (digits.length < 8) {
        if (err) err.textContent = 'Nomor HP tidak valid.';
        return
    }
    if (!addr) {
        if (err) err.textContent = 'Alamat wajib diisi.';
        return
    }
    if (err) err.textContent = '';
    const telepon = '+62 ' + digits.replace(/^0+/, '');
    const alamat = notes ? addr + '\nCatatan: ' + notes : addr;
    const btn = $('#af-save');
    if (btn) btn.disabled = true;
    try {
        const res = await api('api/orders.php', {
            method: 'POST',
            body: {
                action: 'save_address', label: afLabel, nama_penerima: name,
                telepon, alamat, is_default: isDefault
            }
        });
        if (isDefault || res.is_default) savedAddresses.forEach(x => x.is_default = false);
        savedAddresses.push({
            id: res.id, label: afLabel, nama_penerima: name,
            telepon, alamat, is_default: !!res.is_default
        });
        selectedAddressId = res.id;
        renderAddressCard();
        const form = $('#address-form-sheet');
        if (form && form.open) form.close();
        toast('Alamat ditambahkan')
    } catch (e) {
        if (err) err.textContent = e.message
    } finally {
        if (btn) btn.disabled = false
    }
}

function renderCart() {
    const {count, total} = cartTotals(),
        menus = Object.keys(cart).filter(id => productCache[id]).length,
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
    setText('#cart-dialog-total', money(total));
    setText('#cart-footer-total', money(total));
    setText('#cart-subtotal', money(total));
    setText('#cart-menu-badge', menus + ' Menu Terpilih');
    setText('#cart-summary-badge', menus + ' Menu • ' + count + ' Porsi');
    setText('#cart-breakdown-count', count + ' Total Porsi');
    setText('#cart-cta-items', count + ' Porsi Pre-Order');
    setText('#cart-nav-count', count);
    const navCount = $('#cart-nav-count');
    if (navCount) navCount.hidden = count === 0
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
    renderAddressCard();
    ensureAddresses();
    lines.innerHTML = Object.entries(cart).map(([id, qty]) => {
        const p = productCache[id];
        if (!p) return '';
        const subtotal = Number(qty) * Number(p.harga);
        return '<div class="cart-line">'
            + '<div class="cart-line-main">'
                + '<div class="cart-thumb"><img src="' + esc(imagePath(p.gambar)) + '" alt="' + esc(p.nama) + '"></div>'
                + '<div class="cart-info">'
                    + '<div class="cart-info-top">'
                        + '<h3 class="cart-name">' + esc(p.nama) + '</h3>'
                        + '<button type="button" class="cart-remove" data-remove="' + Number(id) + '" aria-label="Hapus menu"><span class="material-symbols-outlined text-[18px]">delete</span></button>'
                    + '</div>'
                    + '<p class="cart-meta">' + Number(qty) + ' porsi × ' + money(p.harga) + '</p>'
                + '</div>'
            + '</div>'
            + '<div class="cart-line-foot">'
                + '<div class="cart-qty-control">'
                    + '<button type="button" data-dec="' + Number(id) + '" aria-label="Kurangi"><span class="material-symbols-outlined text-[16px]">remove</span></button>'
                    + '<span>' + Number(qty) + '</span>'
                    + '<button type="button" data-inc="' + Number(id) + '" aria-label="Tambah"><span class="material-symbols-outlined text-[16px]">add</span></button>'
                + '</div>'
                + '<span class="cart-subtotal">' + money(subtotal) + '</span>'
            + '</div>'
        + '</div>';
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
    const cartDialog = $('#cart-dialog');
    if (!cartDialog.open) cartDialog.showModal();
}

async function directCheckout() {
    if (catalogClosed) {
        toast('Tanggal pengiriman ini libur. Silakan pilih tanggal lain.');
        return
    }
    const entries = Object.entries(cart);
    if (!entries.length) {
        toast('Keranjang masih kosong');
        return
    }
    await ensureAddresses();
    let a = currentAddress();
    if (a && !selectedAddressId) selectedAddressId = a.id;
    if (!a || !selectedAddressId) {
        toast('Tambahkan alamat pengiriman dulu');
        openAddressSheet();
        return
    }
    const date = $('#catalog-delivery-date')?.value;
    if (!date) {
        toast('Pilih tanggal pengiriman dulu');
        return
    }
    const button = $('#continue-checkout');
    if (button) button.disabled = true;
    try {
        const d = await api('api/orders.php', {
            method: 'POST',
            body: {
                nama_pemesan: window.customerInfo?.name || a.nama_penerima,
                alamat_id: Number(selectedAddressId),
                tgl_kirim: date,
                items: entries.map(([id, qty]) => ({id: Number(id), qty: Number(qty)}))
            }
        });
        cart = {};
        renderCart();
        location.href = 'detail.php?order=' + d.id
    } catch (err) {
        toast(err.message);
        if (button) button.disabled = false
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
let historyStatus = '';
function historyPage() {
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
    const id = new URLSearchParams(location.search).get('order');
    if (id) {
        location.href = 'detail.php?order=' + Number(id);
        return;
    }
    loadHistory();
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
    $$('[data-order]').forEach(card => card.onclick = () => { location.href = 'detail.php?order=' + Number(card.dataset.order) })
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

document.addEventListener("DOMContentLoaded", function () {
    const checkoutForm = $('#checkout-form');
    const checkoutDateInput = checkoutForm?.elements?.tgl_kirim || document.getElementById('checkout-date');
    if (!checkoutDateInput) return;
});