const $ = (s, r = document) => r.querySelector(s),
    $$ = (s, r = document) => [...r.querySelectorAll(s)];
const money = (n) => "Rp " + Number(n || 0).toLocaleString("id-ID");
const esc = (s) =>
    String(s ?? "").replace(
        /[&<>"']/g,
        (c) =>
            ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" })[
                c
            ],
    );
async function api(url, options = {}) {
    if (!options.method && $('#quota-date') && /^api\/(products|orders|dashboard)\.php/.test(url) && !/[?&]tgl_kirim=/.test(url))
        url += (url.includes('?') ? '&' : '?') + 'tgl_kirim=' + encodeURIComponent($('#quota-date').value);
    const response = await fetch(url, {
        cache: "no-store",
        headers: { "Content-Type": "application/json" },
        ...options,
        body: options.body ? JSON.stringify(options.body) : undefined,
    });
    const data = await response
        .json()
        .catch(() => ({ error: "Respons server tidak valid" }));
    if (response.status === 401) {
        location.href = "../login.php";
        throw Error("Sesi berakhir");
    }
    if (!response.ok || data?.error) throw Error(data?.error || "Terjadi kesalahan");
    return data;
}
async function uploadDispatchPhoto(id, photo) {
    const form = new FormData();
    form.append("action", "upload_dispatch");
    form.append("id", String(id));
    form.append("foto_kiriman[]", photo);
    const response = await fetch("api/orders.php", {
        method: "POST",
        body: form,
        cache: "no-store",
    });
    const text = await response.text();
    let data;
    try {
        data = JSON.parse(text);
    } catch {
        const jsonStart = text.lastIndexOf("{");
        if (jsonStart >= 0) {
            try {
                data = JSON.parse(text.slice(jsonStart));
            } catch {
                data = null;
            }
        }
        if (!data) {
            const uploadLimit = /post content-length|post_max_size/i.test(text);
            throw Error(
                uploadLimit
                    ? "Ukuran total upload melewati batas server. Pilih foto maksimal 2 MB per file."
                    : "Server mengirim respons yang tidak valid saat menyimpan foto. Coba lagi.",
            );
        }
    }
    if (!response.ok) throw Error(data.error || "Foto kiriman gagal disimpan");
}
function toast(message) {
    const el = $("#toast");
    if (!el) return;
    el.textContent = message;
    el.classList.add("show");
    setTimeout(() => el.classList.remove("show"), 2600);
}
if ($('#quota-date')) $('#quota-date').onchange = () => {
    const url = new URL(location.href);
    url.searchParams.set('tgl_kirim', $('#quota-date').value);
    location.href = url.href
};

// --- FUNGSI CUSTOM CONFIRM DIALOG (MODERN) ---
function confirmDialog(title, message, confirmLabel = "Ya, Lanjutkan", type = "primary") {
    return new Promise((resolve) => {
        const dialog = $("#app-confirm-dialog");
        if (!dialog) return resolve(confirm(message));

        const titleEl = $("#confirm-title");
        const msgEl = $("#confirm-message");
        const btnYes = $("#confirm-yes-btn");
        const iconEl = $("#confirm-icon");

        titleEl.textContent = title;
        msgEl.textContent = message;
        btnYes.textContent = confirmLabel;

        // Set styling (danger = merah, primary = hijau)
        btnYes.className = "button " + type;
        iconEl.className = "confirm-icon " + type;

        // Set Icon (Tanda Seru untuk danger, Centang untuk primary)
        iconEl.innerHTML = type === "danger" ? "&#9888;" : "&#10003;";

        dialog.showModal();

        const close = (result) => {
            dialog.close();
            cleanup();
            resolve(result);
        };

        const onCancel = () => close(false);
        const onConfirm = () => close(true);
        const closeBtns = $$("[data-close-confirm]", dialog);

        const cleanup = () => {
            closeBtns.forEach(b => b.onclick = null);
            btnYes.onclick = null;
            dialog.onclick = null;
        };

        closeBtns.forEach(b => b.onclick = onCancel);
        btnYes.onclick = onConfirm;

        dialog.onclick = (e) => {
            if (e.target === dialog) onCancel();
        };
    });
}
// ------------------------------------

function badge(status) {
    const labels = {
        menunggu_pembayaran: "Menunggu pembayaran",
        menunggu_validasi: "Menunggu validasi",
        diproses: "Sedang diproses",
        selesai: "Selesai",
        ditolak: "Ditolak",
        dibatalkan: "Dibatalkan",
    };
    return (
        '<span class="status-badge ' +
        esc(status) +
        '">' +
        (labels[status] || esc(status)) +
        "</span>"
    );
}
function dateLabel(value, withTime = false) {
    if (!value) return "-";
    const date = new Date(value);
    return date.toLocaleString(
        "id-ID",
        withTime
            ? { day: "2-digit", month: "short", hour: "2-digit", minute: "2-digit", timeZone: "Asia/Jakarta" }
            : { day: "2-digit", month: "short", year: "numeric", timeZone: "Asia/Jakarta" },
    );
}
function cancellationSourceLabel(source) {
    return source === "pelanggan" ? "Dibatalkan oleh pelanggan"
        : source === "otomatis" ? "Otomatis · 1 jam tanpa bukti bayar" : "Sumber belum tercatat (data lama)";
}
function cancellationTime(value) {
    if (!value) return "Belum tercatat";
    return new Date(value).toLocaleString("id-ID", {
        day: "2-digit", month: "short", year: "numeric", hour: "2-digit", minute: "2-digit", timeZone: "Asia/Jakarta",
    }) + " WIB";
}
function cancellationAttention(order) {
    if (!order.status_before_cancel) return "Riwayat lama belum mencatat status asal. Periksa detail dan bukti sebelum menentukan tindak lanjut.";
    if (order.production_stop_required) return "Prioritas: hentikan persiapan/pengiriman, lalu periksa status pembayaran.";
    if (order.needs_payment_review) return "Periksa mutasi rekening. Bukti unggahan belum memastikan pembayaran benar-benar masuk.";
    return "Periksa detail pesanan untuk memastikan tindak lanjut yang diperlukan.";
}
function cancellationSummary(order) {
    if (order.status !== "dibatalkan") return "";
    return '<div class="cancellation-summary"><small>' + esc(cancellationSourceLabel(order.cancellation_source)) +
        '</small><small>' + (order.cancellation_source ? "Dibatalkan: " : "Waktu status tercatat: ") +
        esc(cancellationTime(order.cancelled_at)) + '</small><small>' +
        (order.reviewed_at ? "Sudah ditinjau" : "Belum ditinjau") + '</small></div>';
}
function countUp(el, target) {
    const start = performance.now(),
        duration = 750;
    function frame(now) {
        const p = Math.min((now - start) / duration, 1),
            ease = 1 - Math.pow(1 - p, 3);
        el.textContent = Math.round(target * ease).toLocaleString("id-ID");
        if (p < 1) requestAnimationFrame(frame);
    }
    requestAnimationFrame(frame);
}
async function loadHolidays() {
    const d = await api("api/holidays.php");
    const rows = d.items.map(holiday =>
        `<tr>
      <td><b>${esc(holiday.tanggal_mulai)} s/d ${esc(holiday.tanggal_selesai)}</b></td>
      <td><button class="button small secondary" data-edit-holiday="${holiday.id}">Edit</button>
          <button class="button small danger-outline" data-delete-holiday="${holiday.id}">Hapus</button></td>
    </tr>`
    ).join("");
    $("#holiday-rows").innerHTML = rows || '<tr><td colspan="2" class="empty-state">Belum ada tanggal libur.</td></tr>';
    $$('[data-edit-holiday]').forEach(btn => {
        btn.onclick = () => editHoliday(d.items.find(holiday => Number(holiday.id) === Number(btn.dataset.editHoliday)));
    });
    $$('[data-delete-holiday]').forEach(btn => {
        btn.onclick = async () => {
            const confirmed = await confirmDialog("Hapus tanggal libur", "Tanggal yang tidak tercakup rentang libur lain akan kembali dapat dipesan.", "Ya, Hapus", "danger");
            if (!confirmed) return;
            try {
                await api("api/holidays.php", { method: "POST", body: { action: "delete", id: Number(btn.dataset.deleteHoliday) } });
                toast("Tanggal libur dihapus");
                loadHolidays();
            } catch (e) { toast(e.message); }
        };
    });
}
function editHoliday(holiday = null) {
    const form = $("#holiday-form");
    form.reset();
    form.elements.id.value = holiday?.id || "";
    form.elements.tanggal_mulai.value = holiday?.tanggal_mulai || "";
    form.elements.tanggal_selesai.value = holiday?.tanggal_selesai || "";
    $("#holiday-form-title").textContent = holiday ? "Edit tanggal libur" : "Tambah tanggal libur";
    $("#holiday-error").textContent = "";
    $("#holiday-dialog").showModal();
}
async function schedulesPage() {
    const form = $("#holiday-form");
    $("#new-holiday").onclick = () => editHoliday();
    $$('[data-close-dialog]').forEach(b => b.onclick = () => $("#holiday-dialog").close());
    form.onsubmit = async e => {
        e.preventDefault();
        const body = {
            action: "save",
            ...(form.elements.id.value ? { id: Number(form.elements.id.value) } : {}),
            tanggal_mulai: form.elements.tanggal_mulai.value,
            tanggal_selesai: form.elements.tanggal_selesai.value,
        };
        try {
            await api("api/holidays.php", { method: "POST", body });
            $("#holiday-dialog").close();
            toast("Tanggal libur tersimpan");
            loadHolidays();
        } catch (err) {
            $("#holiday-error").textContent = err.message;
        }
    };
    loadHolidays();
}
function shell() {
    const toggle = $(".menu-toggle");
    toggle?.addEventListener("click", () =>
        $(".sidebar")?.classList.toggle("open"),
    );

    // LOGOUT CONFIRMATION
    $$("[data-logout]").forEach((button) =>
        button.addEventListener("click", async () => {
            const confirmed = await confirmDialog(
                "Keluar Akun",
                "Apakah Anda yakin ingin keluar dari panel admin Se.Noesa?",
                "Ya, Keluar",
                "primary"
            );
            if (!confirmed) return;

            button.disabled = true;
            try {
                await api("../api/auth.php?a=logout");
                location.href = "../login.php";
            } catch (e) {
                button.disabled = false;
                toast(e.message);
            }
        }),
    );

    const label = $("#today-label");
    if (label)
        label.textContent = new Date().toLocaleDateString("id-ID", {
            weekday: "long",
            day: "numeric",
            month: "long",
            year: "numeric",
        });
}
async function dashboard() {
    const d = await api("api/dashboard.php");
    $("#production-date").textContent = dateLabel(d.production_date || $('#quota-date')?.value);
    const productionValid = Array.isArray(d.production) && d.production.every(item => item && Array.isArray(item.pesanan));
    $("#production-list").innerHTML = !productionValid
        ? '<div class="empty-state" role="alert">Rekap produksi belum dapat dimuat. Muat ulang halaman atau hubungi pengelola aplikasi.</div>'
        : d.production.length
        ? '<div class="production-list">' + d.production.map(item =>
            '<article class="production-item"><div><h3>' + esc(item.menu) +
            '</h3><strong>' + Number(item.total_porsi).toLocaleString("id-ID") +
            ' porsi</strong></div><details><summary>Lihat ' + item.pesanan.length +
            ' pesanan</summary><ul>' + item.pesanan.map(order =>
                '<li><a href="order-detail.php?id=' + Number(order.id) + '">' +
                esc(order.kode) + '</a> ' + badge(order.status) + ' · ' +
                Number(order.porsi).toLocaleString("id-ID") + ' porsi</li>'
            ).join("") + '</ul></details></article>'
        ).join("") + '</div>'
        : '<div class="empty-state">Belum ada pesanan untuk tanggal kirim ini.</div>';
    const cancellations = d.cancellations;
    const cancellationList = $("#cancellation-list");
    if (!cancellations || !Array.isArray(cancellations.items) || !cancellations.items.every(order => order && typeof order === "object")) {
        $("#cancellation-count").textContent = "--";
        $("#cancellation-urgent-count").textContent = "";
        cancellationList.innerHTML = '<div class="empty-state" role="alert">Data pembatalan belum dapat dimuat. Muat ulang halaman.</div>';
    } else {
        $("#cancellation-count").textContent = (Number(cancellations.unreviewed_count) || 0).toLocaleString("id-ID");
        $("#cancellation-urgent-count").textContent = (Number(cancellations.urgent_count) || 0).toLocaleString("id-ID") + " perlu pemeriksaan pembayaran atau penghentian persiapan/pengiriman.";
        cancellationList.innerHTML = cancellations.items.length
            ? cancellations.items.map(order => '<a class="cancellation-alert" href="order-detail.php?id=' + Number(order.id) +
                '"><span><b>' + esc(order.kode) + ' · ' + esc(order.nama_pemesan) + '</b><small>' +
                esc(cancellationSourceLabel(order.cancellation_source)) + ' · ' + esc(cancellationTime(order.cancelled_at)) +
                '</small></span><p>' + esc(cancellationAttention(order)) + '</p><span class="cancellation-open">Buka detail pesanan &rarr;</span></a>').join("")
            : '<div class="empty-state">Tidak ada pembatalan yang belum ditinjau.</div>';
    }
    for (const [name, value] of Object.entries({
        orders: d.orders,
        pending: d.pending,
        critical: d.critical,
    })) {
        const el = $('[data-stat="' + name + '"]');
        if (el) countUp(el, value);
    }
    const ratio = d.capacity ? Math.round((d.used / d.capacity) * 100) : 0;
    $('[data-stat="capacity"]').textContent = d.tanggal_libur ? "Libur" : ratio + "%";
    $('[data-stat="capacity-hint"]').textContent = d.tanggal_libur
        ? "Kuota ditutup untuk tanggal ini"
        : d.used.toLocaleString("id-ID") +
          " / " +
          d.capacity.toLocaleString("id-ID") +
          " porsi";
    $("[data-meter]").style.width = Math.min(ratio, 100) + "%";
    const pending = $("#pending-badge");
    if (pending) pending.textContent = d.pending || "";
    const max = Math.max(1, ...d.chart.map((x) => Number(x.jumlah)));
    $("#orders-chart").innerHTML = d.chart
        .map(
            (x, i) =>
                '<div class="chart-column"><span class="chart-count">' +
                esc(x.jumlah) +
                '</span><i style="--bar:' +
                Math.max(5, (Number(x.jumlah) / max) * 100) +
                "%;--delay:" +
                i * 55 +
                'ms"></i><small>' +
                esc(x.label) +
                "</small></div>",
        )
        .join("");
    const priority = $("#priority-list");
    priority.innerHTML = d.priority.length
        ? d.priority
                .map(
                    (o) =>
                        '<a class="priority-item" href="order-detail.php?id=' +
                        Number(o.id) +
                        '"><div class="priority-main"><b>' +
                        esc(o.kode) +
                        " · " +
                        esc(o.nama_pemesan) +
                        '</b><span class="tag warning">Menunggu validasi</span></div><p>' +
                        esc(o.keterangan || o.pax + " porsi") +
                        " · Kirim " +
                        dateLabel(o.tgl_kirim) +
                        "</p><strong>" +
                        money(o.total) +
                        "</strong></a>",
                )
                .join("")
        : '<div class="empty-state">Tidak ada pesanan yang menunggu validasi.</div>';
    $("#activity-list").innerHTML = d.recent.length
        ? d.recent
                .map(
                    (o) =>
                        '<a class="activity-row" href="order-detail.php?id=' +
                        Number(o.id) +
                        '"><span><b>' +
                        esc(o.kode) +
                        "</b>" +
                        (o.sumber === "offline" ? '<span class="tag soft order-source">Offline</span>' : "") +
                        "<small>" +
                        esc(o.nama_pemesan) +
                        " · " +
                        dateLabel(o.created_at, true) +
                        "</small></span>" +
                        badge(o.status) +
                        "<strong>" +
                        money(o.total) +
                        "</strong></a>",
                )
                .join("")
        : '<div class="empty-state">Belum ada aktivitas pesanan.</div>';
}
let orderTab = "semua",
    orderTimer,
    cancellationReview = "";
let offlineProducts = [];
async function loadOrders() {
    const query = encodeURIComponent($("#order-search")?.value || "");
    const reviewQuery = orderTab === "dibatalkan" && cancellationReview ? "&review=" + cancellationReview : "";
    const d = await api("api/orders.php?tab=" + orderTab + "&q=" + query + reviewQuery);
    $("#all-count").textContent = d.counts.total;
    $("#payment-count").textContent = d.counts.payment;
    $("#pending-count").textContent = d.counts.pending;
    $("#processing-count").textContent = d.counts.processing;
    document.querySelector("#completed-count").textContent = d.counts.completed;
    $("#rejected-count").textContent = d.counts.rejected;
    $("#cancelled-count").textContent = d.counts.cancelled;
    $("#cancellation-unreviewed-count").textContent = d.counts.cancellation_unreviewed;
    $("#orders-capacity").textContent =
        (Number(d.capacity.pakai) || 0).toLocaleString("id-ID") +
        " / " +
        (Number(d.capacity.kapasitas) || 0).toLocaleString("id-ID") +
        " porsi";
    const rows = d.items
        .map(
            (o) =>
                "<tr><td><b>" +
                esc(o.kode) +
                "</b>" +
                (o.sumber === "offline" ? '<span class="tag soft order-source">Offline</span>' : "") +
                "<small>" +
                (o.status_updated_at ? "Status sejak " + dateLabel(o.status_updated_at, true) : "Dibuat " + dateLabel(o.created_at, true)) +
                "</small></td><td><b>" +
                esc(o.nama_pemesan) +
                "</b><small>" +
                esc(o.keterangan || "") +
                "</small></td><td>" +
                Number(o.pax || 0).toLocaleString("id-ID") +
                " porsi</td><td><b>" +
                money(o.total) +
                "</b></td><td>" +
                badge(o.status) + cancellationSummary(o) +
                '</td><td><a class="button small secondary" href="order-detail.php?id=' +
                Number(o.id) +
                '">Lihat detail</a></td></tr>',
        )
        .join("");
    $("#order-rows").innerHTML =
        rows ||
        '<tr><td colspan="6" class="empty-state">Tidak ada pesanan untuk filter ini.</td></tr>';
    $("#mobile-orders").innerHTML =
        d.items
            .map(
                (o) =>
                    '<a class="mobile-order" href="order-detail.php?id=' +
                    Number(o.id) +
                    '"><span class="row-between"><b>' +
                    esc(o.kode) +
                    "</b>" +
                    (o.sumber === "offline" ? '<span class="tag soft order-source">Offline</span>' : "") +
                    badge(o.status) +
                    "</span><b>" +
                    esc(o.nama_pemesan) +
                    "</b><small>" +
                    esc(o.keterangan || "") +
                    " · " +
                    (o.status_updated_at ? "Status sejak " + dateLabel(o.status_updated_at, true) : "Dibuat " + dateLabel(o.created_at, true)) +
                    '</small>' + cancellationSummary(o) + '<span class="row-between"><small>' +
                    Number(o.pax || 0) +
                    " porsi</small><strong>" +
                    money(o.total) +
                    "</strong></span></a>",
            )
            .join("") || '<div class="empty-state">Tidak ada pesanan.</div>';
}
function ordersPage() {
    const params = new URLSearchParams(location.search);
    const tabs = ["semua", "pembayaran", "validasi", "proses", "selesai", "ditolak", "dibatalkan"];
    orderTab = tabs.includes(params.get("tab")) ? params.get("tab") : "semua";
    cancellationReview = ["unreviewed", "reviewed"].includes(params.get("review")) ? params.get("review") : "";
    const reviewSelect = $("#cancellation-review");
    reviewSelect.value = cancellationReview;
    const syncOrderFilters = () => {
        $$("#order-tabs button").forEach(button => button.classList.toggle("active", button.dataset.tab === orderTab));
        $("#cancellation-review-filter").hidden = orderTab !== "dibatalkan";
    };
    syncOrderFilters();
    reviewSelect.onchange = () => {
        cancellationReview = reviewSelect.value;
        loadOrders();
    };
    $$("#order-tabs button").forEach(
        (b) =>
            (b.onclick = () => {
                orderTab = b.dataset.tab;
                syncOrderFilters();
                loadOrders();
            }),
    );
    $("#order-search").oninput = () => {
        clearTimeout(orderTimer);
        orderTimer = setTimeout(loadOrders, 220);
    };
    const dialog = $("#offline-order-dialog");
    const form = $("#offline-order-form");
    const productList = $("#offline-menu-list");
    const deliveryDate = $("#offline-delivery-date");
    const today = new Intl.DateTimeFormat("en-CA", {
        timeZone: "Asia/Jakarta",
        year: "numeric",
        month: "2-digit",
        day: "2-digit",
    }).formatToParts(new Date()).reduce((value, part) => {
        if (part.type === "year") value.year = part.value;
        if (part.type === "month") value.month = part.value;
        if (part.type === "day") value.day = part.value;
        return value;
    }, {});
    const todayValue = today.year + "-" + today.month + "-" + today.day;
    const updateOfflineTotal = () => {
        let total = 0;
        $$("[data-offline-qty]", productList).forEach((input) => {
            const quantity = Number(input.value) || 0;
            total += quantity * Number(input.dataset.price);
        });
        $("#offline-order-total").textContent = money(total);
    };
    const loadOfflineProducts = async () => {
        productList.textContent = "Memuat menu dan kuota...";
        $("#offline-menu-capacity").textContent = "";
        try {
            const data = await api("api/products.php?tgl_kirim=" + encodeURIComponent(deliveryDate.value));
            offlineProducts = data.items;
            const kitchenMaximum = Math.floor(
                offlineProducts.reduce((sum, product) => sum + Number(product.kuota), 0) * 0.8,
            );
            const kitchenUsed = offlineProducts.reduce((sum, product) => sum + Number(product.terpakai), 0);
            $("#offline-menu-capacity").textContent = "Sisa dapur " +
                Math.max(0, kitchenMaximum - kitchenUsed).toLocaleString("id-ID") + " porsi";
            productList.innerHTML = offlineProducts.length
                ? offlineProducts.map((product) =>
                    '<label class="offline-menu-item"><span><b>' + esc(product.nama) + '</b><small>' +
                    money(product.harga) + ' · sisa ' + Number(product.sisa).toLocaleString("id-ID") +
                    ' · minimal ' + Number(product.min_pesan) + ' porsi</small></span><input type="number" min="0" max="' +
                    Number(product.sisa) + '" value="0" data-offline-qty data-product-id="' +
                    Number(product.id) + '" data-price="' + Number(product.harga) + '" aria-label="Jumlah ' +
                    esc(product.nama) + '"' + (Number(product.sisa) <= 0 ? " disabled" : "") + '></label>',
                ).join("")
                : '<p class="muted">Belum ada menu yang tersedia.</p>';
            updateOfflineTotal();
        } catch (error) {
            productList.textContent = error.message;
        }
    };
    $("#new-offline-order").onclick = () => {
        form.reset();
        deliveryDate.min = todayValue;
        deliveryDate.value = todayValue;
        form.elements.waktu_kirim.value = "12:00";
        $("#offline-order-error").textContent = "";
        dialog.showModal();
        loadOfflineProducts();
    };
    $$("[data-close-offline-order]").forEach((button) => {
        button.onclick = () => dialog.close();
    });
    deliveryDate.onchange = loadOfflineProducts;
    productList.oninput = updateOfflineTotal;
    form.onsubmit = async (event) => {
        event.preventDefault();
        const button = $("#save-offline-order");
        const items = $$("[data-offline-qty]", productList)
            .map((input) => ({ id: Number(input.dataset.productId), qty: Number(input.value) || 0 }))
            .filter((item) => item.qty > 0);
        if (!items.length) {
            $("#offline-order-error").textContent = "Pilih jumlah untuk setidaknya satu menu.";
            return;
        }
        button.disabled = true;
        $("#offline-order-error").textContent = "";
        try {
            const values = Object.fromEntries(new FormData(form));
            const result = await api("api/orders.php", {
                method: "POST",
                body: { ...values, action: "create_offline", items },
            });
            location.href = "order-detail.php?id=" + Number(result.id);
        } catch (error) {
            $("#offline-order-error").textContent = error.message;
            button.disabled = false;
        }
    };
    loadOrders();
}
let productCategory = "",
    productTimer;
function productImagePath(value) {
    const raw = String(value || "");
    if (/^uploads\/products\/[a-f0-9]+\.(jpg|png|webp)$/i.test(raw))
        return "../" + raw;
    const file = raw.replace(/[^a-zA-Z0-9_.-]/g, "");
    return file
        ? "../assets/img/produk/" + file
        : "../assets/img/produk/NK-01.png";
}
async function loadProducts() {
    const q = encodeURIComponent($("#product-search")?.value || "");
    const d = await api(
        "api/products.php?kategori=" +
            encodeURIComponent(productCategory) +
            "&q=" +
            q,
    );
    $("#all-count").textContent = d.count.semua;
    $("#critical-count").textContent = d.count.kritis;
    const grid = $("#product-grid");
    grid.innerHTML = d.items.length
        ? d.items
                .map((p) => {
                    const percent = p.kuota
                        ? Math.round((p.terpakai / p.kuota) * 100)
                        : 0;
                    return (
                        '<article class="product-card"><img class="product-card-image" src="' +
                        esc(productImagePath(p.gambar)) +
                        '" alt="" onerror="this.src=\'../assets/img/produk/NK-01.png\'"><div class="product-info"><div class="row-between"><span class="category-label">' +
                        esc(p.kategori) +
                        "</span>" +
                        (!d.tanggal_libur && (Number(p.kuota) === 0 || Number(p.sisa) <= Number(p.kuota) * 0.2)
                            ? '<span class="tag warning">Kritis</span>'
                            : "") +
                        "</div><h3>" +
                        esc(p.nama) +
                        "</h3><p>" +
                        money(p.harga) +
                        " · " +
                        Number(p.sisa).toLocaleString("id-ID") +
                        "/" +
                        Number(p.kuota).toLocaleString("id-ID") +
                        ' tersisa</p><div class="stock-meter"><i style="width:' +
                        Math.max(0, Math.min(percent, 100)) +
                        '%"></i></div><div class="product-actions"><button class="button small secondary" data-edit="' +
                        Number(p.id) +
                        '">Edit</button><button class="button small danger-outline" data-delete="' +
                        Number(p.id) +
                        '">Hapus</button></div></div></article>'
                    );
                })
                .join("")
        : '<div class="empty-state">Produk tidak ditemukan.</div>';
    $$("[data-edit]").forEach(
        (b) =>
            (b.onclick = () =>
                editProduct(
                    d.items.find((p) => Number(p.id) === Number(b.dataset.edit)),
                )),
    );
    $$("[data-delete]").forEach(
        (b) =>
            (b.onclick = async () => {
                // HAPUS PRODUK CONFIRMATION
                const confirmed = await confirmDialog(
                    "Hapus Produk",
                    "Apakah Anda yakin ingin menghapus produk ini? Data yang dihapus tidak dapat dikembalikan.",
                    "Ya, Hapus",
                    "danger"
                );
                if (!confirmed) return;

                try {
                    await api("api/products.php", {
                        method: "POST",
                        body: { action: "delete", id: Number(b.dataset.delete) },
                    });
                    toast("Produk dihapus");
                    loadProducts();
                } catch (e) {
                    toast(e.message);
                }
            }),
    );
    return d.items;
}
function refreshProducts() {
    loadProducts().catch ((e) => {
        const grid = $("#product-grid");
        if (grid)
            grid.innerHTML =
                '<div class="empty-state">Gagal memuat produk: ' +
                esc(e.message) +
                "</div>";
        toast(e.message);
    });
}
async function loadKuotaHarian(produkId) {
    const tbody = $("#kuota-list");
    if (!tbody) return;
    try {
        const d = await api("api/kuota-harian.php?produk_id=" + produkId);
        const items = d.items || [];
        tbody.innerHTML = items.length
            ? items
                    .map(
                        (row) =>
                            "<tr><td>" +
                            esc(row.tanggal) +
                            "</td><td>" +
                            esc(row.kuota) +
                            ' porsi</td><td><button type="button" class="button small" data-delete-kuota="' +
                            row.id +
                            '">Kembali ke default</button></td></tr>',
                    )
                    .join("")
            : '<tr><td colspan="3">Semua tanggal memakai kuota default</td></tr>';
        $$("[data-delete-kuota]").forEach((btn) => {
            btn.onclick = async () => {
                try {
                    await api("api/kuota-harian.php", {
                        method: "POST",
                        body: { action: "delete", id: Number(btn.dataset.deleteKuota) },
                    });
                    await loadKuotaHarian(produkId);
                    refreshProducts();
                } catch (e) {
                    toast(e.message);
                }
            };
        });
    } catch (e) {
        tbody.innerHTML = '<tr><td colspan="3">Gagal memuat kuota</td></tr>';
    }
}
function editProduct(p) {
    const form = $("#product-form"),
        preview = $("#product-image-preview"),
        kuotaSection = $("#kuota-harian-section"),
        kuotaList = $("#kuota-list");
    form.reset();
    if (kuotaSection) kuotaSection.hidden = !p?.id;
    if (kuotaList) kuotaList.innerHTML = "";
    form.elements.id.value = p?.id || "";
    form.elements.kode.value = p?.kode || "";
    form.elements.nama.value = p?.nama || "";
    form.elements.kategori.value = p?.kategori || "makanan";
    form.elements.harga.value = p?.harga || "";
    $('#kuota-tanggal').value = $('#quota-date')?.value || '';
    $('#kuota-tanggal-selesai').value = $('#kuota-tanggal').value;
    form.elements.kuota_default.value = p?.kuota_default ?? 50;
    form.elements.min_pesan.value = p?.min_pesan || 4;
    form.elements.gambar.value = p?.gambar || "";
    form.elements.gambar_file.required = !p;
    $("#image-help").textContent = p
        ? "Kosongkan jika gambar tidak ingin diganti. JPG, PNG, atau WebP · maksimal 2 MB"
        : "Wajib untuk produk baru. JPG, PNG, atau WebP · maksimal 2 MB. Kuota tersedia akan tampil ke pelanggan.";
    preview.hidden = !p?.gambar;
    preview.src = p?.gambar ? productImagePath(p.gambar) : "";
    $("#product-form-title").textContent = p ? "Edit produk" : "Tambah produk";
    $("#product-error").textContent = "";
    if (p?.id) loadKuotaHarian(p.id);
    $("#product-dialog").showModal();
}
function productsPage() {
    $$("#product-filters button").forEach(
        (b) =>
            (b.onclick = () => {
                $$("#product-filters button").forEach((x) =>
                    x.classList.toggle("active", x === b),
                );
                productCategory = b.dataset.category;
                refreshProducts();
            }),
    );
    $("#product-search").oninput = () => {
        clearTimeout(productTimer);
        productTimer = setTimeout(refreshProducts, 220);
    };
    $("#new-product").onclick = () => editProduct(null);
    $$("[data-close-dialog]").forEach(
        (b) => (b.onclick = () => {
            const section = $("#kuota-harian-section");
            if (section) section.hidden = true;
            const list = $("#kuota-list");
            if (list) list.innerHTML = "";
            $("#product-dialog").close();
        }),
    );
    const kuotaAddBtn = $("#kuota-add");
    $('#kuota-tanggal').onchange = () => {
        $('#kuota-tanggal-selesai').value = $('#kuota-tanggal').value;
    };
    if (kuotaAddBtn) {
        const saveRange = async (action) => {
            const tanggal_mulai = $("#kuota-tanggal").value;
            const tanggal_selesai = $("#kuota-tanggal-selesai").value;
            const rawKuota = $("#kuota-nilai").value;
            const kuota = rawKuota === '' ? NaN : Number(rawKuota);
            const produkId = $("#product-form").elements.id.value;
            if (!tanggal_mulai || !tanggal_selesai || !produkId ||
                (action === 'save' && (!Number.isInteger(kuota) || kuota < 0 || kuota > 2147483647))) {
                $('#product-error').textContent = 'Isi rentang tanggal dan kuota dengan benar.';
                return;
            }
            try {
                await api("api/kuota-harian.php", {
                    method: "POST",
                    body: { action, produk_id: Number(produkId), tanggal_mulai, tanggal_selesai, kuota },
                });
                $("#kuota-nilai").value = "";
                await loadKuotaHarian(produkId);
                refreshProducts();
                $('#product-error').textContent = '';
                toast(action === 'reset' ? 'Rentang kembali ke kuota default.' : 'Kuota rentang tersimpan.');
            } catch (e) {
                $('#product-error').textContent = e.message;
            }
        };
        kuotaAddBtn.onclick = () => saveRange('save');
        $('#kuota-reset').onclick = () => saveRange('reset');
    }
    $("#product-form").elements.gambar_file.onchange = (e) => {
        const file = e.target.files[0],
            preview = $("#product-image-preview");
        if (file) {
            preview.src = URL.createObjectURL(file);
            preview.hidden = false;
        }
    };
    $("#product-form").onsubmit = async (e) => {
        e.preventDefault();
        const f = e.currentTarget,
            body = new FormData(f);
        body.set("action", "save");
        try {
            const response = await fetch("api/products.php", {
                    method: "POST",
                    body,
                }),
                data = await response
                    .json()
                    .catch (() => ({ error: "Respons server tidak valid" }));
            if (response.status === 401) {
                location.href = "../login.php";
                return;
            }
            if (!response.ok) throw Error(data.error || "Terjadi kesalahan");
            const savedCode = f.elements.kode.value.trim().toUpperCase();
            productCategory = "";
            $("#product-search").value = "";
            $$("#product-filters button").forEach((x) =>
                x.classList.toggle("active", x.dataset.category === ""),
            );
            const loadedProducts = await loadProducts();
            if (
                !loadedProducts.some(
                    (p) => String(p.kode).trim().toUpperCase() === savedCode,
                )
            )
                throw Error(
                    "API menjawab " +
                        JSON.stringify(data) +
                        " tetapi produk belum muncul pada daftar.",
                );
            const savedId = data.id || f.elements.id.value;
            if (savedId) {
                const section = $("#kuota-harian-section");
                if (section) section.hidden = false;
                loadKuotaHarian(savedId);
            }
            $("#product-dialog").close();
            toast("Produk dan gambar tersimpan. Daftar admin diperbarui.");
        } catch (err) {
            $("#product-error").textContent = err.message;
        }
    };
    refreshProducts();
}
async function orderDetail() {
    const id = Number(new URLSearchParams(location.search).get("id"));
    if (!id) {
        $("#order-detail").innerHTML =
            '<article class="panel empty-state">Kode pesanan tidak valid.</article>';
        return;
    }
    const o = await api("api/orders.php?id=" + id);
    $("#detail-status").outerHTML = badge(o.status);
    const raw = o.bukti_bayar || "",
        proofUrl = raw
            ? /^https?:\/\//.test(raw) || raw.startsWith("/")
                ? raw
                : "../" + raw.replace(/^\.\//, "")
            : "";
    const delivery = o.tgl_kirim ? dateLabel(o.tgl_kirim, true) : "-";
    const proof = raw
        ? '<a class="proof-image" href="' +
            esc(proofUrl) +
            '" target="_blank" rel="noopener"><img src="' +
            esc(proofUrl) +
            '" alt="Bukti transfer pelanggan"></a>'
        : '<div class="proof-empty"><span>?</span><b>Belum ada bukti pembayaran</b></div>';
    const proofPanel = '<article class="panel proof-panel"><div class="panel-heading"><h2>Bukti transfer pelanggan</h2><span class="tag soft">QRIS</span></div>' + proof + '</article>';
    const receipt =
        '<article class="panel"><div class="panel-heading"><h2>Data pada pembayaran</h2><span class="tag soft">QRIS</span></div><div class="detail-pair"><span>Nama pengirim</span><b>' +
        esc(o.nama_pembayar || "-") +
        '</b></div><div class="detail-pair"><span>Nama pemesan</span><b>' +
        esc(o.nama_pemesan || "-") +
        '</b></div><div class="detail-pair"><span>Nominal tagihan</span><b>' +
        money(o.total) +
        '</b></div><div class="detail-pair"><span>Total pesanan</span><b>' +
        money(o.total) +
        '</b></div><div class="detail-pair"><span>Jadwal pengiriman</span><b>' +
        delivery +
        "</b></div></article>";
    const menuItems = Array.isArray(o.menu) ? o.menu : [];
    const menuCount = Array.isArray(o.menu) ? menuItems.length + " jenis" : "Tidak tersedia";
    const menu = menuItems
        .map(
            (m) =>
                '<label class="dispatch-item"><input type="checkbox" data-item-check="' +
                Number(m.item_id) +
                '" ' +
                (m.sudah_dicek ? "checked" : "") +
                " " +
                (o.status === "diproses" ? "" : "disabled") +
                "><span><b>" +
                esc(m.nama) +
                "</b><small>" +
                Number(m.qty).toLocaleString("id-ID") +
                " porsi ? " +
                money(m.harga) +
                "</small></span><strong>" +
                money(m.subtotal) +
                "</strong></label>",
        )
        .join("");
    const sentPhotos = Array.isArray(o.foto_kiriman_list)
        ? o.foto_kiriman_list
        : o.foto_kiriman
          ? [o.foto_kiriman]
          : [];
    const sentPhotoGallery = sentPhotos.length
        ? '<div class="dispatch-photos">' +
          sentPhotos
              .map((photo) => {
                  const photoUrl = /^https?:\/\//.test(photo) || photo.startsWith("/")
                      ? photo
                      : "../" + photo.replace(/^\.\//, "");
                  return '<a class="dispatch-photo-link" href="' +
                      esc(photoUrl) +
                      '" target="_blank" rel="noopener"><img class="dispatch-photo" src="' +
                      esc(photoUrl) +
                      '" alt="Foto makanan yang akan dikirim"></a>';
              })
              .join("") +
          "</div>"
        : '<p class="muted">Belum ada foto makanan yang diunggah.</p>';
    const dispatch =
        o.status === "diproses" || o.status === "selesai" || (o.status === "dibatalkan" && sentPhotos.length)
            ? '<article class="panel"><div class="panel-heading"><h2>' + (o.status === "dibatalkan" ? 'Foto pesanan tersimpan' : 'Foto makanan siap kirim') + '</h2><span class="tag soft">Foto kiriman</span></div>' +
                sentPhotoGallery +
                (o.status === "diproses"
                    ? '<form id="dispatch-photo-form" class="dispatch-upload"><label for="dispatch-photo">Tambah foto makanan yang akan dikirim</label><input id="dispatch-photo" name="foto_kiriman[]" type="file" accept="image/jpeg,image/png,image/webp" multiple required><small>Pilih satu atau beberapa foto JPG, PNG, atau WebP. Maksimal 2 MB per foto.</small><button class="button secondary">Simpan foto</button><span id="dispatch-photo-error" class="form-error"></span></form>'
                    : "") +
                  "</article>"
            : "";
    const checklist =
        '<article class="panel"><div class="panel-heading"><h2>Daftar menu</h2><span class="tag soft">' +
        menuCount +
        '</span></div>' +
        (Array.isArray(o.menu)
            ? '<p class="muted">' + (o.status === "dibatalkan" ? 'Daftar menu disimpan sebagai riwayat pesanan.' : 'Centang setiap menu setelah jumlah dan jenis makanan dipastikan sesuai.') + '</p>'
            : '<p class="form-error" role="alert">Data daftar menu tidak tersedia. Muat ulang atau hubungi administrator.</p>') +
        menu +
        "</article>";
    const isValidation = o.status === "menunggu_validasi";
    const validationView =
        '<article class="panel order-summary"><div class="panel-heading"><div><p class="eyebrow">VALIDASI QRIS</p><h2>' +
        esc(o.kode) +
        '</h2></div><span class="tag soft">' +
        dateLabel(o.created_at, true) +
        '</span></div><div class="detail-pair"><span>Waktu status</span><b>' +
        (o.status_updated_at ? dateLabel(o.status_updated_at, true) : "Belum tercatat") +
        '</b></div><div class="detail-pair"><span>Nama penerima</span><b>' +
        esc(o.nama_pemesan) +
        '</b></div><div class="detail-pair"><span>Nomor HP penerima</span><b>' +
        esc(o.telepon_penerima || o.phone || "-") +
        '</b></div><div class="detail-pair"><span>Alamat pengiriman</span><b>' +
        esc(o.alamat_pengiriman || "-") +
        '</b></div><div class="detail-pair"><span>Catatan pesanan</span><b>' +
        esc(o.keterangan || "-") +
        '</b></div><div class="detail-pair total-row"><span>Total tagihan</span><b>' +
        money(o.total) +
        '</b></div></article>' + proofPanel +
        receipt;
    const dispatchView =
        '<article class="panel order-summary"><div class="panel-heading"><div><p class="eyebrow">DETAIL PESANAN</p><h2>' +
        esc(o.kode) +
        "</h2></div>" +
        (o.status === "dibatalkan" ? "" : badge(o.status)) +
        '</div><div class="detail-pair"><span>Waktu status</span><b>' +
        (o.status_updated_at ? dateLabel(o.status_updated_at, true) : "Belum tercatat") +
        '</b></div><div class="detail-pair"><span>Sumber pesanan</span><b>' +
        (o.sumber === "offline" ? "Offline (dicatat Admin)" : "Online") +
        '</b></div><div class="detail-pair"><span>Nama penerima</span><b>' +
        esc(o.nama_pemesan) +
        '</b></div><div class="detail-pair"><span>Kontak penerima</span><b>' +
        esc(o.telepon_penerima || o.phone || "-") +
        '</b></div><div class="detail-pair"><span>Alamat pengiriman</span><b>' +
        esc(o.alamat_pengiriman || "-") +
        '</b></div><div class="detail-pair"><span>Jadwal pengiriman</span><b>' +
        delivery +
        '</b></div><div class="detail-pair total-row"><span>Total pesanan</span><b>' +
        money(o.total) +
        "</b></div></article>" +
        (o.status === "ditolak" && o.alasan_penolakan
            ? '<article class="panel rejection-reason"><b>Alasan penolakan</b><p>' +
                esc(o.alasan_penolakan) + '</p></article>'
            : "") +
        checklist +
        dispatch;
    const cancellationInfo = o.status === "dibatalkan"
        ? '<article class="panel cancellation-detail"><div class="panel-heading"><div><p class="eyebrow">RIWAYAT & TINDAK LANJUT</p><h2>Pembatalan pesanan</h2></div>' +
            '</div><div class="cancellation-meta"><div class="detail-pair"><span>Sumber pembatalan</span><b>' + esc(cancellationSourceLabel(o.cancellation_source)) + '</b></div>' +
            '<div class="detail-pair"><span>' + (o.cancellation_source ? 'Waktu dibatalkan' : 'Waktu status tercatat') + '</span><b>' +
            esc(cancellationTime(o.cancelled_at)) + '</b></div>' +
            '<div class="detail-pair"><span>Status sebelumnya</span><b>' + (o.status_before_cancel ? badge(o.status_before_cancel) : 'Belum tercatat (data lama)') + '</b></div></div>' +
            '<div class="cancellation-next-step"><b>Yang perlu diperiksa</b><p>' + esc(cancellationAttention(o)) + '</p></div>' +
            '<p class="muted">Bukti bayar yang diunggah belum memastikan dana diterima. Jika pembayaran sudah diterima, refund penuh ditangani Admin di luar aplikasi.</p>' +
            (o.reviewed_at ? '<p class="cancellation-reviewed">Sudah ditinjau oleh <b>' + esc(o.reviewed_by_name || 'Admin') + '</b> pada ' +
                esc(cancellationTime(o.reviewed_at)) + '.</p>' : '<p class="cancellation-unreviewed">Belum ditinjau.</p>') +
            '<p class="muted">Sudah ditinjau berarti pembatalan telah diperiksa, bukan konfirmasi refund selesai.</p></article>'
        : '';
    $("#order-detail").innerHTML = isValidation ? validationView : cancellationInfo + dispatchView +
        (o.status === "dibatalkan" ? proofPanel + receipt : '');
    const actions = $("#detail-actions");
    const rejectionDialog = $("#reject-order-dialog");
    const rejectionForm = $("#reject-order-form");
    const rejectionReason = $("#rejection-reason");
    const rejectionError = $("#rejection-reason-error");
    $$("[data-cancel-rejection]").forEach((button) => {
        button.onclick = () => rejectionDialog.close();
    });
    actions.hidden = false;
    const actionButtons = $$("[data-order-action]");
    actionButtons.forEach((btn) => {
        const action = btn.dataset.orderAction;
        btn.hidden = !(
            (o.status === "menunggu_validasi" &&
                (action === "validate" || action === "reject")) ||
            (o.status === "diproses" && action === "complete") ||
            (o.status === "dibatalkan" && !o.reviewed_at && action === "review_cancellation")
        );
        btn.onclick = async () => {
            // KONFIRMASI UNTUK SEMUA TOMBOL KEPUTUSAN
            if (action === "review_cancellation") {
                const confirmed = await confirmDialog(
                    "Tinjau pembatalan",
                    "Tandai pembatalan ini sudah diperiksa? Penanda ini bukan konfirmasi bahwa refund telah selesai.",
                    "Sudah ditinjau",
                    "primary"
                );
                if (!confirmed) return;
                $("#review-cancellation-error").textContent = "";
            }
            let rejectionNote = "";
            if (action === "reject") {
                rejectionReason.value = "";
                rejectionError.textContent = "";
                rejectionDialog.showModal();
                const submitted = await new Promise((resolve) => {
                    const onSubmit = (event) => {
                        event.preventDefault();
                        const value = rejectionReason.value.trim();
                        if (!value) {
                            rejectionError.textContent = "Alasan penolakan wajib diisi.";
                            rejectionReason.focus();
                            return;
                        }
                        rejectionForm.removeEventListener("submit", onSubmit);
                        rejectionDialog.removeEventListener("close", onClose);
                        rejectionDialog.close();
                        resolve(value);
                    };
                    const onClose = () => {
                        rejectionForm.removeEventListener("submit", onSubmit);
                        resolve(null);
                    };
                    rejectionForm.addEventListener("submit", onSubmit);
                    rejectionDialog.addEventListener("close", onClose, { once: true });
                });
                if (submitted === null) return;
                rejectionNote = submitted;
            }
            if (action === "validate") {
                const confirmed = await confirmDialog(
                    "Validasi Pembayaran",
                    "Validasi pembayaran pesanan ini? Status akan berubah menjadi Diproses.",
                    "Ya, Validasi",
                    "primary"
                );
                if (!confirmed) return;
            }
            if (action === "complete") {
                const confirmed = await confirmDialog(
                    "Selesaikan Pesanan",
                    "Pesanan akan ditandai selesai dan siap dikirim. Pastikan semua menu sudah dicek dan foto makanan sudah diunggah.",
                    "Ya, Selesaikan",
                    "primary"
                );
                if (!confirmed) return;
            }

            btn.disabled = true;
            try {
                await api("api/orders.php", {
                    method: "POST",
                    body: { id, action, ...(action === "reject" ? { alasan_penolakan: rejectionNote } : {}) },
                });
                toast(action === "review_cancellation" ? "Pembatalan ditandai sudah ditinjau" : "Status pesanan diperbarui");
                setTimeout(() => location.reload(), 500);
            } catch (e) {
                toast(e.message);
                if (action === "review_cancellation") $("#review-cancellation-error").textContent = e.message;
                btn.disabled = false;
            }
        };
    });
    actions.hidden = !actionButtons.some(btn => !btn.hidden);
    $$("[data-item-check]").forEach(
        (box) =>
            (box.onchange = async () => {
                box.disabled = true;
                try {
                    await api("api/orders.php", {
                        method: "POST",
                        body: {
                            action: "check_item",
                            id,
                            item_id: Number(box.dataset.itemCheck),
                            checked: box.checked,
                        },
                    });
                } catch (e) {
                    box.checked = !box.checked;
                    toast(e.message);
                } finally {
                    box.disabled = false;
                }
            }),
    );
    const upload = $("#dispatch-photo-form");
    upload?.addEventListener("submit", async (e) => {
        e.preventDefault();
        const button = $("button", upload),
            input = $('input[type="file"]', upload),
            photos = Array.from(input.files || []);
        if (!photos.length) {
            $("#dispatch-photo-error").textContent = "Pilih setidaknya satu foto.";
            return;
        }
        if (photos.some((photo) => photo.size > 2 * 1024 * 1024)) {
            $("#dispatch-photo-error").textContent =
                "Ukuran setiap foto maksimal 2 MB. Kecilkan foto lalu pilih kembali.";
            return;
        }
        button.disabled = true;
        let uploadedCount = 0;
        try {
            for (const photo of photos) {
                await uploadDispatchPhoto(id, photo);
                uploadedCount++;
            }
            toast("Foto makanan tersimpan");
            setTimeout(() => location.reload(), 400);
        } catch (error) {
            $("#dispatch-photo-error").textContent = uploadedCount
                ? `${uploadedCount} foto tersimpan, tetapi foto berikutnya gagal: ${error.message}`
                : error.message;
            button.disabled = false;
            if (uploadedCount) setTimeout(() => location.reload(), 1800);
        }
    });
}
shell();
const page = document.body.dataset.page;
const run = {
    dashboard,
    orders: ordersPage,
    "order-detail": orderDetail,
    products: productsPage,
    schedules: schedulesPage,
}[page];
if (run)
    Promise.resolve().then(run).catch((e) => {
        toast(e.message);
        if (page === "dashboard") {
            $("#production-list").innerHTML = '<div class="empty-state" role="alert">Dashboard belum dapat dimuat: ' + esc(e.message) + '</div>';
            $("#cancellation-list").innerHTML = '<div class="empty-state" role="alert">Pembatalan belum dapat dimuat: ' + esc(e.message) + '</div>';
            $("#cancellation-count").textContent = "--";
            $("#cancellation-urgent-count").textContent = "";
        }
        if (page === "order-detail") {
            const detail = $("#order-detail");
            if (detail)
                detail.innerHTML =
                    '<article class="panel empty-state">Gagal memuat detail pesanan: ' +
                    esc(e.message) +
                    '<br><a class="button small secondary" href="orders.php">Kembali ke daftar pesanan</a></article>';
        }
    });
