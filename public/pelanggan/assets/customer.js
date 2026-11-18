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
}, activeProduct = null, activeQty = 1, searchTimer, savedAddresses = [];
function catalogPage() {
    const search = $('#catalog-search'),
        filters = $$('.customer-category-rail [data-category]');
    const syncFilters = () => filters.forEach(x => {
        const active = x.dataset.category === category;
        x.classList.toggle('active', active);
        x.setAttribute('aria-pressed', active);
    });

    syncFilters();
    filters.forEach(b => b.onclick = () => {
        if (category === b.dataset.category) return;

        category = b.dataset.category;
        syncFilters();
        $('#menu-grid').scrollTo({top: 0, behavior: 'smooth'});
        refreshCatalog(true);
    });

    search.oninput = () => {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(() => refreshCatalog(true), 200)
    };
    $('#checkout-button').onclick = openCart;
    $('#continue-checkout').onclick = () => {
        $('#cart-dialog').close();
        openCheckout()
    };
    $$('[data-close-cart]').forEach(b => b.onclick = () => $('#cart-dialog').close());
    $$('[data-close-detail]').forEach(b => b.onclick = () => $('#detail-dialog').close());

    const detailDialog = $('#detail-dialog'),
        detailContent = $('#detail-content'),
        cartDialog = $('#cart-dialog'),
        checkoutDialog = $('#checkout-dialog');
    detailContent.addEventListener('click', e => {
        if (e.target.closest('.quantity-control')) e.stopPropagation()
    });
    // Pola yang sama dengan detail/history dialog: klik backdrop menutup,
    // klik di dalam konten tidak menutup. Escape/fokus tetap ditangani native <dialog>.
    detailDialog.addEventListener('click', e => {
        if (e.target === detailDialog) detailDialog.close()
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
    $('#catalog-delivery-date').onchange = () => refreshCatalog();
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

    refreshCatalog();
}
async function refreshCatalog(animate = false) {
    try {
        await loadCatalog(animate);
    } catch (err) {
        const grid = $('#menu-grid');
        grid.innerHTML = '<div class="empty-state catalog-error"><p>' + esc(err.message) + '</p><button type="button" class="customer-button secondary" data-retry-catalog>Coba lagi</button></div>';
        $('[data-retry-catalog]').onclick = () => refreshCatalog(animate);
    }
}
async function loadCatalog(animate = false) {
    const grid = $('#menu-grid');
    grid.classList.remove('category-transition');
    const day = $('#catalog-delivery-date').value;
    const d = await api('api/catalog.php?tgl_kirim=' + encodeURIComponent(day));
    if (day !== $('#catalog-delivery-date').value) return;
    catalogClosed = Boolean(d.tanggal_libur);
    $('#delivery-hint').textContent = catalogClosed
        ? 'Tanggal ini merupakan hari libur. Semua kuota menu ditutup; silakan pilih tanggal pengiriman lain.'
        : 'Pilih tanggal untuk melihat menu yang tersedia. Pengiriman paling awal 7 hari dari hari ini.';
    const fullHint = $('#quota-full-hint');
    if (fullHint) {
        const full = Array.isArray(d.habis) ? d.habis : [];
        if (full.length && !catalogClosed) {
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
    $('#checkout-button').disabled = catalogClosed;

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
    grid.innerHTML = catalog.length ? catalog.map((p, i) => {
        const pSisa = Number(p.sisa), pKuota = Number(p.kuota);
        const showStock = pKuota > 0 && pSisa < pKuota * 0.5;
        return '<article class="menu-card" data-product="' + Number(p.id)
            + '" style="--i:' + i + '"><img class="menu-image" data-open-product="' + Number(p.id) + '" src="' + esc(imagePath(p.gambar))
            + '" alt="' + esc(p.nama) + '" onerror="this.src=\'../assets/img/produk/NK-01.png\'"><div class="menu-card-copy"><span class="category-pill">'
            + esc(p.kategori) + '</span><h3>' + esc(p.nama) + '</h3><p>Min. ' + Number(p.min_pesan) + ' porsi' + (showStock ? ' · Sisa ' + pSisa + ' porsi' : '')
            + '</p><strong class="menu-price">' + money(p.harga) + '</strong></div><button class="menu-add" data-add="'
            + Number(p.id) + '" aria-label="Lihat ' + esc(p.nama) + '">+</button></article>';
    }).join(''): '<div class="empty-state">Menu tidak ditemukan atau stoknya habis.</div>';

    $$('[data-product]').forEach(card => card.onclick = e => {
        if (e.target.closest('[data-add]')) return;
        showProduct(catalog.find(p => Number(p.id) === Number(card.dataset.product)))
    });
    $$('[data-add]').forEach(b => b.onclick = e => {
        e.stopPropagation();
        showProduct(catalog.find(p => Number(p.id) === Number(b.dataset.add)))
    });
    $$('[data-open-product]').forEach(image => image.onclick = e => {
        e.stopPropagation();
        showProduct(catalog.find(p => Number(p.id) === Number(image.dataset.openProduct)))
    });
    renderCart();
    if (animate) requestAnimationFrame(() => {
        grid.classList.add('category-transition');
        setTimeout(() => grid.classList.remove('category-transition'), 750)
    })
}
function showProduct(product) {
    if (!product) return;
    activeProduct = product;
    const minimum = Number(product.min_pesan), maximum = Number(product.sisa);
    activeQty = Math.min(maximum, Math.max(1, minimum));
    const sisaAfter = maximum - activeQty;
    const showSisa = Number(product.kuota) > 0 && sisaAfter < Number(product.kuota) * 0.5;
    $('#detail-content').innerHTML = '<div class="detail-product detail-compact"><div class="detail-compact-hero"><img class="detail-compact-image" src="'
        + esc(imagePath(product.gambar)) + '" alt="' + esc(product.nama) + '" onerror="this.src=\'../assets/img/produk/NK-01.png\'"><div class="detail-compact-copy"><span class="category-pill">'
        + esc(product.kategori) + '</span><h2>' + esc(product.nama) + '</h2><b>Deskripsi</b><p>Menu pilihan Se.Noesa, dibuat segar untuk menemani acara dan waktu makan Anda.</p></div><div class="product-facts"><span>Min. Pesan<b>'
        + minimum + ' porsi</b></span><span>Jumlah dipesan<b id="detail-qty">' + activeQty + ' porsi</b></span>' + (showSisa ? '<span>Sisa Porsi<b id="detail-sisa">' + sisaAfter + ' porsi</b></span>' : '') + '</div></div><div class="detail-compact-price">' + money(product.harga)
        + ' / porsi</div><div class="quantity-line"><label for="quantity-value">Atur jumlah porsi</label><div class="quantity-control"><button type="button" data-qty="minus" aria-label="Kurangi">&minus;</button><input id="quantity-value" type="number" min="'
        + minimum + '" max="' + maximum + '" step="1" inputmode="numeric" value="' + activeQty + '" aria-label="Jumlah porsi"><button type="button" data-qty="plus" aria-label="Tambah">+</button></div></div><button class="customer-button primary detail-add" id="add-to-cart">Pesan Sekarang</button></div>';
    const input = $('#quantity-value'), add = $('#add-to-cart'), sisaEl = $('#detail-sisa');
    function setQuantity(value, normalize = false) {
        if (value === '' && !normalize) {
            add.disabled = true;
            return
        }

        let quantity = Number(value);
        if (!Number.isFinite(quantity)) {
            add.disabled = true;
            return
        }
        quantity = Math.floor(quantity);
        if (normalize) quantity = Math.max(minimum, Math.min(maximum, quantity));
        if (quantity < minimum || quantity > maximum) {
            add.disabled = true;
            return
        }
        activeQty = quantity;
        input.value = quantity;
        $('#detail-qty').textContent = quantity + ' porsi';
        const remaining = maximum - quantity;
        const shouldShow = Number(product.kuota) > 0 && remaining < Number(product.kuota) * 0.5;
        if (sisaEl) {
            if (shouldShow) {
                sisaEl.textContent = remaining + ' porsi';
                sisaEl.closest('span').style.display = '';
            } else {
                sisaEl.closest('span').style.display = 'none';
            }
        }
        add.textContent = 'Pesan Sekarang';
        add.disabled = false
    }
    input.addEventListener('input', () => setQuantity(input.value));
    input.addEventListener('change', () => setQuantity(input.value, true));
    input.addEventListener('blur', () => setQuantity(input.value, true));
    $$('[data-qty]').forEach(b => b.onclick = e => {
        e.preventDefault();
        e.stopPropagation();
        setQuantity(activeQty + (b.dataset.qty === 'plus' ? 1: -1), true)
    });
    input.addEventListener('click', e => e.stopPropagation());
    if (maximum < minimum) {
        input.disabled = true;
        add.disabled = true;
        add.textContent = 'Stok kurang dari minimal pesanan'
    }
    add.onclick = () => {
        if (add.disabled) return;
        cart[product.id] = (cart[product.id] || 0) + activeQty;
        $('#detail-dialog').close();
        renderCart();
        toast(product.nama + ' ditambahkan ke keranjang')
    };
    $('#detail-dialog').showModal()
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
        bar = $('#cart-bar');
    bar.hidden = count === 0;
    document.body.classList.toggle('has-cart', count > 0);
    $('#cart-count').textContent = count;
    $('#cart-total').textContent = money(total);
    $('#cart-summary').textContent = count + ' porsi dari ' + Object.keys(cart).length + ' menu';
    $('#cart-dialog-total').textContent = money(total)
}

function openCart() {
    if (catalogClosed) {
        toast('Tanggal pengiriman ini libur. Silakan pilih tanggal lain.');
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
        $$('#history-tabs button').forEach(x => x.classList.toggle('active', x === b));
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
        return '<button type="button" class="history-card" data-order="' + Number(o.id) + '" style="--i:' + i + '"><div class="history-card-top"><div><b>'
            + esc(o.kode) + '</b><p class="history-date">Dibuat ' + dateLabel(o.created_at) + ' · Kirim ' + dateLabel(o.tgl_kirim)
            + '</p><p class="history-date">' + (o.status_updated_at ? 'Status sejak ' + dateLabel(o.status_updated_at, true)
                : 'Waktu perubahan status belum tercatat') + '</p></div><span class="status-label ' + esc(o.status) + '">'
            + esc(statusLabels[o.status] || o.status) + '</span></div>' +
            (o.status === 'ditolak' && o.alasan_penolakan
                ? '<p class="history-rejection-reason"><b>Alasan penolakan:</b> ' + esc(o.alasan_penolakan) + '</p>'
                : '') + '<div class="history-products">' + (first.gambar ? '<img src="'
            + esc(imagePath(first.gambar)) + '" alt="">': '<span class="history-icon">&#9634;</span>') + '<span><b>' + esc(first.nama || 'Pesanan Se.Noesa')
            + '</b><small>' + Number(first.qty || o.pax) + ' porsi' + (o.items.length > 1 ? ' + ' + (o.items.length - 1) + ' menu lainnya' : '')
            + '</small></span></div>' + (o.reschedule_pending ? '<p class="history-rejection-reason">Menunggu konfirmasi jadwal</p>' : '')
            + '<div class="history-total"><span><small>TOTAL</small><strong>' + money(o.total) + '</strong></span><span class="open-detail">Detail &rarr;</span></div></button>'
    }).join(''): '<div class="empty-state">Belum ada pesanan pada kategori ini.</div>';
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
        const dateLabel = checkoutDateInput.closest('label') || checkoutDateInput;
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