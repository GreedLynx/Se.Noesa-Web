// =========================================
// FUNGSI UMUM (dipakai di halaman pembayaran)
// =========================================

document.addEventListener('DOMContentLoaded', function () {
    // Isi ringkasan pesanan
    const ringkasan = document.getElementById('ringkasan');
    if (ringkasan) {
        try {
            const isiRingkasan = localStorage.getItem('ringkasanPesanan');
            if (isiRingkasan) ringkasan.value = isiRingkasan;
        } catch (err) { /* abaikan */ }
    }

    // Preview gambar bukti pembayaran
    const fileUpload = document.getElementById('file-upload');
    const previewImage = document.getElementById('preview-image');
    const uploadIcon = document.querySelector('.upload-icon');

    if (fileUpload && previewImage) {
        fileUpload.addEventListener('change', function (e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function (event) {
                    previewImage.src = event.target.result;
                    previewImage.style.display = 'block';
                    if (uploadIcon) uploadIcon.style.display = 'none';
                };
                reader.readAsDataURL(file);
            }
        });
    }
});

function konfirmasiPesanan() {
    try {
        localStorage.setItem('sudahPesan', 'true');
        sessionStorage.removeItem('cart');
        localStorage.removeItem('ringkasanPesanan');
        localStorage.removeItem('detailPesanan');
    } catch (err) { /* abaikan */ }

    alert('Pesanan berhasil dikonfirmasi');
    window.location.href = 'riwayat.php';
}

// Dropdown logout dashboard admin
function toggleLogout(event) {
    event.preventDefault();
    const logoutBtn = document.getElementById('logoutMenu');
    if (logoutBtn) logoutBtn.classList.toggle('show');
}

document.addEventListener('click', function (event) {
    const profileMenu = document.querySelector('.profile-menu');
    const logoutBtn = document.getElementById('logoutMenu');
    if (profileMenu && !profileMenu.contains(event.target)) {
        if (logoutBtn) logoutBtn.classList.remove('show');
    }
});


// =========================================
// HALAMAN PESANAN (Select Your Choices)
// =========================================
(function () {
    'use strict';

    if (!document.getElementById('orderMain')) return;

    var STORAGE_KEY = 'cart';

    function $(sel, root) { return (root || document).querySelector(sel); }
    function $$(sel, root) { return Array.prototype.slice.call((root || document).querySelectorAll(sel)); }
    function rupiah(n) { return Number(n).toLocaleString('id-ID'); }
    function esc(s) {
        return String(s).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    /* ---------- Data menu dari HTML ---------- */
    var items = {};
    $$('.menu-item').forEach(function (el) {
        var d = el.dataset;
        items[d.id] = {
            id: d.id, nama: d.nama, desk: d.desk,
            harga: +d.harga, min: +d.min, sisa: +d.sisa, total: +d.total,
            foto: d.foto, emoji: d.emoji
        };
    });

    /* ---------- Keranjang ---------- */
    var cart = {};
    try {
        var saved = JSON.parse(sessionStorage.getItem(STORAGE_KEY) || '[]');
        saved.forEach(function (r) {
            if (items[r.id] && r.qty > 0) cart[r.id] = Math.min(r.qty, items[r.id].sisa);
        });
    } catch (err) { /* abaikan */ }

    var main = $('#orderMain');
    var cartEl = $('#cart');

    /* ---------- Ganti kategori ---------- */
    function setCat(cat) {
        $$('.rail-item').forEach(function (b) { b.classList.toggle('active', b.dataset.cat === cat); });
        $$('.menu-list').forEach(function (l) { l.classList.toggle('active', l.dataset.cat === cat); });
        main.scrollTop = 0;
        applyFilter();
    }
    $$('.rail-item').forEach(function (btn) {
        btn.addEventListener('click', function () { setCat(btn.dataset.cat); });
    });

    /* ---------- Pencarian ---------- */
    var searchInput = $('#searchInput');
    function applyFilter() {
        var q = searchInput.value.trim().toLowerCase();
        var list = $('.menu-list.active');
        var visible = 0;
        $$('.menu-item', list).forEach(function (el) {
            var ok = el.dataset.nama.toLowerCase().indexOf(q) !== -1;
            el.style.display = ok ? '' : 'none';
            if (ok) visible++;
        });
        $('#menuEmpty').style.display = visible ? 'none' : 'block';
    }
    searchInput.addEventListener('input', applyFilter);

    /* ---------- Detail produk ---------- */
    var sheet = $('#sheet');
    var overlay = $('#sheetOverlay');
    var current = null;
    var qty = 0;

    function updateQty() {
        $('#qtyVal').textContent = qty;
        $('#statJumlah').textContent = qty + ' porsi';
        $('#qtyMinus').disabled = qty <= current.min;
        $('#qtyPlus').disabled = qty >= current.sisa;
    }

    function openSheet(id) {
        var p = items[id];
        if (!p) return;
        current = p;
        qty = Math.max(p.min, Math.min(cart[id] || p.min, p.sisa));

        $('#sheetName').textContent = p.nama;
        $('#sheetDesc').textContent = p.desk;
        $('#statMin').textContent = p.min + ' porsi';
        $('#statSisa').textContent = p.sisa + '/' + p.total;

        var photo = $('#sheetPhoto');
        var img = $('#sheetImg');
        var thumb = $('.menu-item[data-id="' + id + '"] .photo');
        photo.dataset.emoji = p.emoji;
        photo.classList.toggle('no-img', !!(thumb && thumb.classList.contains('no-img')));
        img.onerror = function () { photo.classList.add('no-img'); };
        img.alt = p.nama;
        img.src = p.foto;

        $('#sheetRemove').hidden = !Object.prototype.hasOwnProperty.call(cart, id);
        updateQty();

        overlay.classList.add('open');
        sheet.classList.add('open');
        sheet.setAttribute('aria-hidden', 'false');
    }

    function closeSheet() {
        overlay.classList.remove('open');
        sheet.classList.remove('open');
        sheet.setAttribute('aria-hidden', 'true');
    }

    main.addEventListener('click', function (e) {
        var trigger = e.target.closest('.menu-photo, .menu-add');
        if (!trigger) return;
        var item = trigger.closest('.menu-item');
        if (item) openSheet(item.dataset.id);
    });

    overlay.addEventListener('click', closeSheet);
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') closeSheet();
    });

    $('#qtyMinus').addEventListener('click', function () {
        if (current && qty > current.min) { qty--; updateQty(); }
    });
    $('#qtyPlus').addEventListener('click', function () {
        if (current && qty < current.sisa) { qty++; updateQty(); }
    });

    $('#sheetOrder').addEventListener('click', function () {
        if (!current) return;
        cart[current.id] = qty;
        closeSheet();
        renderCart();
    });

    $('#sheetRemove').addEventListener('click', function () {
        if (!current) return;
        delete cart[current.id];
        closeSheet();
        renderCart();
    });

    /* ---------- Render keranjang ---------- */
    function renderCart() {
        var ids = Object.keys(cart);
        var count = 0;
        var total = 0;
        var rows = '';
        var saveList = [];

        ids.forEach(function (id) {
            var p = items[id];
            var q = cart[id];
            count += q;
            total += p.harga * q;
            saveList.push({ id: id, nama: p.nama, harga: p.harga, qty: q });
            rows += '<button type="button" class="cart-row" data-id="' + esc(id) + '">' +
                '<span><span class="cart-row-name">' + esc(p.nama) + '</span>' +
                '<span class="cart-row-sub">' + rupiah(p.harga) + ' / porsi &times; ' + q + '</span></span>' +
                '<span class="cart-row-sum">' + rupiah(p.harga * q) + '</span></button>';
        });

        $('#cartCount').textContent = count;
        $('#cartTotal').textContent = rupiah(total);
        $('#cartItems').innerHTML = rows;
        $('#cartPay').textContent = 'Bayar | ' + rupiah(total);

        cartEl.classList.toggle('show', count > 0);
        if (count === 0) {
            cartEl.classList.remove('open');
            $('#cartHead').setAttribute('aria-expanded', 'false');
        }

        try { sessionStorage.setItem(STORAGE_KEY, JSON.stringify(saveList)); } catch (err) { /* abaikan */ }
    }

    $('#cartHead').addEventListener('click', function () {
        var open = cartEl.classList.toggle('open');
        this.setAttribute('aria-expanded', open ? 'true' : 'false');
    });

    $('#cartItems').addEventListener('click', function (e) {
        var row = e.target.closest('.cart-row');
        if (row) openSheet(row.dataset.id);
    });

    /* ---------- Tombol Bayar → pembayaran.php ---------- */
    $('#cartPay').addEventListener('click', function (e) {
        e.preventDefault();

        var ids = Object.keys(cart);
        if (!ids.length) {
            alert('Keranjang masih kosong!');
            return;
        }

        var pax = 0;
        var total = 0;
        ids.forEach(function (id) {
            pax += cart[id];
            total += items[id].harga * cart[id];
        });

        var ringkasan = pax + ' pax & Rp ' + rupiah(total);
        try {
            localStorage.setItem('ringkasanPesanan', ringkasan);
            localStorage.setItem('detailPesanan', JSON.stringify(cart));
        } catch (err) { /* abaikan */ }

        window.location.href = 'pembayaran.php';
    });

    /* Scroll di luar area putih ikut menggulir menu */
    document.addEventListener('wheel', function (e) {
        if (sheet.classList.contains('open')) return;
        if (main.contains(e.target) || e.target.closest('.cart-items')) return;
        main.scrollTop += e.deltaY;
    }, { passive: true });

    renderCart();
    applyFilter();
})();