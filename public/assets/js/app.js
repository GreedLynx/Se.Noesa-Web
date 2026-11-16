const $ = (s, r = document) => r.querySelector(s),
    $$ = (s, r = document) => [...r.querySelectorAll(s)];
const rp = (n) => "Rp " + Number(n).toLocaleString("id-ID"),
    short = (n) => "Rp " + (n / 1000).toFixed(1).replace(".0", "") + "k";
const esc = (s) => String(s ?? "").replace(/[&<>"']/g, (c) =>
    ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" })[c],
);
const productImage = (value) => {
    const raw = String(value || "");
    if (/^uploads\/products\/[a-f0-9]+\.(jpg|png|webp)$/i.test(raw)) return "../" + raw;
    const file = raw.replace(/[^a-zA-Z0-9_.-]/g, "");
    return "../assets/img/produk/" + (file || "NK-01.png");
};
const page = document.body.dataset.page;
const appPrefix = /\/(owner|pelanggan)\//.test(location.pathname) ? "../" : "";
async function api(url, opt = {}) {
    if (!opt.method && $('#quota-date') && /^api\/(products|orders)\.php/.test(url))
        url += (url.includes('?') ? '&' : '?') + 'tgl_kirim=' + encodeURIComponent($('#quota-date').value);
    const multipart = opt.body instanceof FormData;
    const r = await fetch(url, {
        headers: multipart ? {} : { "Content-Type": "application/json" },
        ...opt,
        body: opt.body ? (multipart ? opt.body : JSON.stringify(opt.body)) : undefined,
    });
    const d = await r
        .json()
        .catch (() => ({ error: "Respon server tidak valid" }));
    if (r.status === 401 && page !== "login") {
        location.href = appPrefix + "login.php";
        return Promise.reject();
    }
    if (!r.ok) throw Object.assign(new Error(d.error || "Terjadi kesalahan"), d);
    return d;
}
function toast(m) {
    const t = $("#toast");
    t.textContent = m;
    t.classList.add("on");
    setTimeout(() => t.classList.remove("on"), 2200);
}
if ($('#quota-date')) $('#quota-date').onchange = () => {
    const url = new URL(location.href);
    url.searchParams.set('tgl_kirim', $('#quota-date').value);
    location.href = url.href
};

// --- FUNGSI CUSTOM CONFIRM DIALOG ---
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

        if (type === "danger") {
            btnYes.style.background = "#a5463b";
            btnYes.style.color = "#fff";
            iconEl.style.background = "#f8e4e1";
            iconEl.style.color = "#a5463b";
            iconEl.innerHTML = "&#9888;";
        } else {
            btnYes.style.background = ""; // default (var(--g))
            btnYes.style.color = "#fff";
            iconEl.style.background = "var(--gl)";
            iconEl.style.color = "var(--gd)";
            iconEl.innerHTML = "&#10003;";
        }

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
        dialog.onclick = (e) => { if (e.target === dialog) onCancel(); };
    });
}
// ------------------------------------

function countUp(el, to, ms = 1100) {
    const f = +el.dataset.from || 0,
        t0 = performance.now();
    el.dataset.from = to;
    (function s(t) {
        const p = Math.min((t - t0) / ms, 1),
            e = 1 - Math.pow(1 - p, 4);
        el.textContent = Math.round(f + (to - f) * e).toLocaleString("id-ID");
        if (p < 1) requestAnimationFrame(s);
    })(t0);
}
const stagger = (r) =>
    $$(".rise", r).forEach((e, i) => e.style.setProperty("--i", i));
stagger();
/* transisi antar halaman */
document.addEventListener("click", (e) => {
    const a = e.target.closest("a[href]");
    if (!a || a.target || a.href.includes("#")) return;
    e.preventDefault();
    document.body.classList.add("leave");
    setTimeout(() => (location.href = a.href), 230);
});
/* pill navigasi */
const on = $(".nav a.on");
if (on) {
    const mv = () => {
        const pill = $("#pill");
        if (matchMedia("(min-width: 900px)").matches) {
            pill.style.left = "12px";
            pill.style.top = on.offsetTop + "px";
            pill.style.width = "calc(100% - 24px)";
        } else {
            pill.style.top = "8px";
            pill.style.left = on.offsetLeft + on.offsetWidth / 2 - 35 + "px";
            pill.style.width = "70px";
        }
    };
    mv();
    addEventListener("resize", mv);
}
/* toggle password */
$$(".eye").forEach(
    (b) =>
        (b.onclick = () => {
            const i = b.previousElementSibling;
            i.type = i.type === "password" ? "text" : "password";
        }),
);

// UPDATE LOGOUT MENGGUNAKAN CONFIRM DIALOG
$$("[data-logout]").forEach(
    (b) =>
        (b.onclick = async () => {
            const confirmed = await confirmDialog(
                "Keluar Akun",
                "Apakah Anda yakin ingin keluar dari panel owner Se.Noesa?",
                "Ya, Keluar",
                "primary"
            );
            if (!confirmed) return;

            b.disabled = true;
            try {
                await api("api/auth.php?a=logout");
                location.href = appPrefix + "login.php";
            } catch (e) {
                b.disabled = false;
                toast("Gagal keluar: " + e.message);
            }
        }),
);
const PAGES = {
    splash() {
        setTimeout(() => {
            document.body.classList.add("leave");
            const next = document.body.dataset.next;
            const destinations = ["login.php", "owner/dashboard.php", "admin/dashboard.php", "pelanggan/index.php"];
            setTimeout(
                () =>
                    (location.href =
                        destinations.includes(next) ? next : "login.php"),
                230,
            );
        }, 2600);
    },
    async form() {
        const f = $("#f");
        f.onsubmit = async (e) => {
            e.preventDefault();
            const b = $(".btn-main", f),
                er = $("#err");
            er.textContent = "";
            er.classList.remove("on");
            b.disabled = true;
            b.textContent = "Memproses…";
            try {
                const d = await api(f.dataset.api, {
                    method: "POST",
                    body: Object.fromEntries(new FormData(f)),
                });
                toast("Berhasil");
                setTimeout(() => {
                    document.body.classList.add("leave");
                    setTimeout(() => (location.href = d.redirect), 230);
                }, 300);
            } catch (x) {
                er.textContent = x.message;
                void er.offsetWidth;
                er.classList.add("on");
                b.disabled = false;
                b.textContent = page === "login" ? "Login" : "Sign Up";
            }
        };
    },
    async "forgot-password"() {
        const emailForm = $("#forgot-password-form");
        const resetForm = $("#reset-password-form");
        const emailInput = emailForm.elements.email;
        const requestButton = $("#request-otp-button");
        const resendButton = $("#resend-otp-button");
        const changeEmailButton = $("#change-reset-email");
        const requestError = $("#forgot-password-error");
        const notice = $("#forgot-password-notice");
        const resetError = $("#reset-password-error");
        const otpInput = resetForm.elements.otp;
        let cooldownTimer;

        const showError = (element, message) => {
            element.textContent = message;
            element.classList.add("on");
        };
        const clearError = (element) => {
            element.textContent = "";
            element.classList.remove("on");
        };
        const startCooldown = () => {
            clearInterval(cooldownTimer);
            let remaining = 60;
            resendButton.disabled = true;
            resendButton.textContent = `Kirim ulang kode (${remaining})`;
            cooldownTimer = setInterval(() => {
                remaining -= 1;
                if (remaining <= 0) {
                    clearInterval(cooldownTimer);
                    resendButton.disabled = false;
                    resendButton.textContent = "Kirim ulang kode OTP";
                } else {
                    resendButton.textContent = `Kirim ulang kode (${remaining})`;
                }
            }, 1000);
        };
        const requestOtp = async () => {
            clearError(requestError);
            requestButton.disabled = true;
            resendButton.disabled = true;
            try {
                const result = await api("api/password-reset.php", {
                    method: "POST",
                    body: { action: "request", email: emailInput.value },
                });
                notice.textContent = result.message;
                notice.classList.add("on");
                resetForm.hidden = false;
                emailInput.disabled = true;
                requestButton.hidden = true;
                otpInput.focus();
                startCooldown();
            } catch (error) {
                showError(requestError, error.message);
                requestButton.disabled = false;
                resendButton.disabled = false;
                resendButton.textContent = "Kirim ulang kode OTP";
            }
        };

        emailForm.onsubmit = async (event) => {
            event.preventDefault();
            await requestOtp();
        };
        otpInput.addEventListener("input", () => {
            otpInput.value = otpInput.value.replace(/\D/g, "").slice(0, 6);
        });
        resendButton.onclick = requestOtp;
        changeEmailButton.onclick = () => {
            clearInterval(cooldownTimer);
            resetForm.reset();
            resetForm.hidden = true;
            emailInput.disabled = false;
            requestButton.hidden = false;
            requestButton.disabled = false;
            resendButton.disabled = true;
            resendButton.textContent = "Kirim ulang kode (60)";
            notice.textContent = "";
            notice.classList.remove("on");
            clearError(requestError);
            clearError(resetError);
            emailInput.focus();
        };
        resetForm.onsubmit = async (event) => {
            event.preventDefault();
            clearError(resetError);
            const saveButton = resetForm.querySelector('button[type="submit"]');
            saveButton.disabled = true;
            saveButton.textContent = "Menyimpan…";
            try {
                const result = await api("api/password-reset.php", {
                    method: "POST",
                    body: {
                        action: "reset",
                        email: emailInput.value,
                        ...Object.fromEntries(new FormData(resetForm)),
                    },
                });
                toast(result.message);
                setTimeout(() => {
                    document.body.classList.add("leave");
                    setTimeout(() => (location.href = "login.php"), 230);
                }, 900);
            } catch (error) {
                showError(resetError, error.message);
                saveButton.disabled = false;
                saveButton.textContent = "Simpan Password Baru";
            }
        };
    },
    async dashboard() {
        const L = {
            today: [
                "OMZET TERVERIFIKASI HARI INI",
                "Total Hari Ini",
                "KUANTITAS PAX",
            ],
            week: [
                "OMZET TERVERIFIKASI 7 HARI TERAKHIR",
                "Total 7 Hari",
                "TOTAL TERJUAL",
            ],
            month: [
                "OMZET TERVERIFIKASI BULAN INI",
                "Total Bulan Ini",
                "KUANTITAS PAX",
            ],
        };
        const pageParams = new URLSearchParams(location.search);
        const initialPeriod = ["today", "week", "month"].includes(pageParams.get("period")) ? pageParams.get("period") : "today";
        const initialSelection = pageParams.get("selected");
        let selected = null, requestId = 0;
        function selectedLabel(period, key) {
            if (period === "week") {
                const [y, m, day] = key.split("-").map(Number);
                return new Date(y, m - 1, day, 12).toLocaleDateString("id-ID", {
                    weekday: "long", day: "numeric", month: "long",
                });
            }
            const [y, m] = key.split("-").map(Number);
            return new Date(y, m - 1, 1, 12).toLocaleDateString("id-ID", {
                month: "long", year: "numeric",
            });
        }
        async function load(p, chosen = null) {
            const request = ++requestId;
            const d = await api("api/dashboard.php?period=" + p + (chosen ? "&selected=" + encodeURIComponent(chosen) : ""));
            if (request !== requestId) return;
            selected = d.selected;
            const pickedLabel = selected ? selectedLabel(p, selected) : null;
            $("#hLabel").textContent = pickedLabel
                ? "OMZET TERVERIFIKASI " + (p === "week" ? "HARI " : "BULAN ") + pickedLabel.toLocaleUpperCase("id-ID")
                : L[p][0];
            $("#tot").textContent = pickedLabel ? "Total " + pickedLabel : L[p][1];
            $("#l2").textContent = L[p][2];
            countUp($("#omzet"), d.omzet);
            $("#tiket").textContent = short(d.tiket);
            $("#items").textContent = d.items.toLocaleString("id-ID") + " Item";
            $("#growth").textContent =
                (d.growth >= 0 ? "↗ +" : "↘ ") + d.growth + "%";
            $("#orders").textContent = d.orders + " Pesanan";
            const max = Math.max(...d.chart.map((c) => Number(c.v)), 1),
                hi = d.selected ? d.chart.findIndex((c) => c.k === d.selected) : p === "month" ? new Date().getMonth() : d.chart.length - 1;
            const chartPeriod = p === "month" ? "month" : "week";
            $("#bars").innerHTML = d.chart
                .map(
                    (c, i) => {
                        const label = chartPeriod === "month"
                            ? new Date(Number(c.k.slice(0, 4)), Number(c.k.slice(5, 7)) - 1, 1, 12).toLocaleDateString("id-ID", { month: "short" }).replace(/\.$/, "")
                            : selectedLabel("week", c.k).split(" ")[0].slice(0, 3);
                        return `<a class="bar${i === hi ? " hi" : ""}" href="dashboard.php?period=${chartPeriod}&amp;selected=${encodeURIComponent(c.k)}" title="${selectedLabel(chartPeriod, c.k)}: ${rp(c.v)}"><i style="--n:${i}"></i>${label}</a>`;
                    },
                )
                .join("");
            requestAnimationFrame(() =>
                requestAnimationFrame(() =>
                    $$("#bars .bar i").forEach(
                        (el, i) =>
                            (el.style.height =
                                Math.max((d.chart[i].v / max) * 100, 10) + "%"),
                    ),
                ),
            );
            const kn = { makanan: "Makanan", minuman: "Minuman", snack: "Snack" };
            $("#kat").innerHTML =
                d.kategori
                    .map(
                        (k) =>
                            `<div class="krow"><span>${kn[k.k]} (${short(k.h).replace("Rp ", "Rp ")})</span><b>${k.n} ${k.k === "minuman" ? "Botol" : "Porsi"}</b></div>`,
                    )
                    .join("") || '<p class="empty">Belum ada data</p>';
            $("#top").innerHTML = d.top
                .map(
                    (t, i) =>
                        `<div class="prod rise" style="--i:${i}"><span class="rk">${i + 1}</span><img src="${productImage(t.gambar)}" alt=""><div><b>${t.nama}</b>${t.n} Terjual • ${rp(t.harga)}<span class="om"><span>Kontribusi Omzet</span><strong>${rp(t.om)}</strong></span></div></div>`,
                )
                .join("");
        }
        $$("#period button").forEach(
            (b) =>
                (b.onclick = () => {
                    $$("#period button").forEach((x) =>
                        x.classList.toggle("on", x === b),
                    );
                    selected = null;
                    load(b.dataset.p);
                }),
        );
        $$("#period button").forEach((b) => b.classList.toggle("on", b.dataset.p === initialPeriod));
        load(initialPeriod, initialSelection);
    },
    async orders() {
        let tab = "semua",
            timer,
            offlineProducts = [];
        const search = $("#order-search");
        const list = $("#order-list");
        const money = (value) => rp(Number(value) || 0);
        const dateLabel = (value, withTime = false) => {
            if (!value) return "-";
            const date = new Date(value);
            return date.toLocaleString("id-ID", withTime
                ? { day: "2-digit", month: "short", hour: "2-digit", minute: "2-digit", timeZone: "Asia/Jakarta" }
                : { day: "2-digit", month: "short", year: "numeric", timeZone: "Asia/Jakarta" });
        };
        const labels = {
            menunggu_pembayaran: "Menunggu pembayaran",
            menunggu_validasi: "Menunggu validasi",
            diproses: "Sedang diproses",
            selesai: "Selesai",
            ditolak: "Ditolak",
            dibatalkan: "Dibatalkan",
        };
        const statusBadge = (status) => `<span class="owner-status ${esc(status)}">${labels[status] || esc(status)}</span>`;
        async function load() {
            try {
                const d = await api(`api/orders.php?tab=${tab}&q=${encodeURIComponent(search.value)}`);
                $("#all-count").textContent = d.counts.total;
                $("#payment-count").textContent = d.counts.payment;
                $("#pending-count").textContent = d.counts.pending;
                $("#processing-count").textContent = d.counts.processing;
                $("#completed-count").textContent = d.counts.completed;
                $("#rejected-count").textContent = d.counts.rejected;
                const capacity = d.capacity || { kapasitas: 0, pakai: 0 };
                $("#orders-capacity").textContent =
                    `${Number(capacity.pakai).toLocaleString("id-ID")} / ${Number(capacity.kapasitas).toLocaleString("id-ID")} porsi · sisa ${Math.max(0, capacity.kapasitas - capacity.pakai).toLocaleString("id-ID")} porsi`;
                list.innerHTML = d.items.map((order, index) =>
                    `<a class="ord owner-order" style="--i:${index}" href="order-detail.php?id=${Number(order.id)}">
                        <div class="h"><span>${esc(order.kode)}${order.sumber === "offline" ? ' <i class="owner-source">Offline</i>' : ""}</span>${statusBadge(order.status)}</div>
                        <div class="b"><div><b>${esc(order.nama_pemesan)}</b><small>${esc(order.keterangan || "Tanpa catatan")} · Kirim ${esc(dateLabel(order.tgl_kirim))}</small></div><strong>${money(order.total)}</strong></div>
                        <div class="owner-order-meta"><span>${order.status_updated_at ? "Status sejak " + esc(dateLabel(order.status_updated_at, true)) : "Dibuat " + esc(dateLabel(order.created_at, true))}</span><span>Lihat detail &rarr;</span></div>
                    </a>`
                ).join("") || '<p class="empty">Tidak ada pesanan untuk filter ini.</p>';
            } catch (error) {
                list.innerHTML = `<p class="owner-error" role="alert">${esc(error.message)}</p>`;
            }
        }
        $$("#order-tabs button").forEach((button) => {
            button.onclick = () => {
                tab = button.dataset.tab;
                $$("#order-tabs button").forEach((item) => item.classList.toggle("on", item === button));
                load();
            };
        });
        search.oninput = () => {
            clearTimeout(timer);
            timer = setTimeout(load, 250);
        };

        const dialog = $("#offline-order-dialog");
        const form = $("#offline-order-form");
        const dateInput = $("#offline-delivery-date");
        const menuList = $("#offline-menu-list");
        const updateTotal = () => {
            const total = $$("[data-offline-qty]", menuList).reduce((sum, input) =>
                sum + (Number(input.value) || 0) * Number(input.dataset.price), 0);
            $("#offline-order-total").textContent = money(total);
        };
        const loadOfflineProducts = async () => {
            menuList.textContent = "Memuat menu dan kuota...";
            $("#offline-menu-capacity").textContent = "";
            try {
                const data = await api("api/products.php?tgl_kirim=" + encodeURIComponent(dateInput.value));
                offlineProducts = data.items;
                $("#offline-menu-capacity").textContent = `Sisa dapur ${Number(data.kitchenRemaining).toLocaleString("id-ID")} porsi`;
                menuList.innerHTML = offlineProducts.length ? offlineProducts.map((product) =>
                    `<label class="owner-menu-item"><span><b>${esc(product.nama)}</b><small>${money(product.harga)} · sisa ${Number(product.sisa).toLocaleString("id-ID")} · minimal ${Number(product.min_pesan)} porsi</small></span>
                    <input type="number" min="0" max="${Number(product.sisa)}" value="0" data-offline-qty data-product-id="${Number(product.id)}" data-price="${Number(product.harga)}" aria-label="Jumlah ${esc(product.nama)}"${Number(product.sisa) <= 0 ? " disabled" : ""}></label>`
                ).join("") : '<p class="owner-muted">Belum ada menu yang tersedia.</p>';
                updateTotal();
            } catch (error) {
                menuList.textContent = error.message;
            }
        };
        $("#new-offline-order").onclick = () => {
            form.reset();
            const today = new Intl.DateTimeFormat("en-CA", { timeZone: "Asia/Jakarta", year: "numeric", month: "2-digit", day: "2-digit" }).format(new Date());
            dateInput.min = today;
            dateInput.value = today;
            form.elements.waktu_kirim.value = "12:00";
            $("#offline-order-error").textContent = "";
            dialog.showModal();
            loadOfflineProducts();
        };
        $$("[data-close-offline-order]").forEach((button) => button.onclick = () => dialog.close());
        dateInput.onchange = loadOfflineProducts;
        menuList.oninput = updateTotal;
        form.onsubmit = async (event) => {
            event.preventDefault();
            const items = $$("[data-offline-qty]", menuList)
                .map((input) => ({ id: Number(input.dataset.productId), qty: Number(input.value) || 0 }))
                .filter((item) => item.qty > 0);
            if (!items.length) {
                $("#offline-order-error").textContent = "Pilih jumlah untuk setidaknya satu menu.";
                return;
            }
            const button = $("#save-offline-order");
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
        load();
    },
    async detail() {
        const container = $("#order-detail");
        const id = Number(container.dataset.id);
        const actions = $("#detail-actions");
        const money = (value) => rp(Number(value) || 0);
        const dateLabel = (value, withTime = false) => {
            if (!value) return "-";
            return new Date(value).toLocaleString("id-ID", withTime
                ? { day: "2-digit", month: "short", year: "numeric", hour: "2-digit", minute: "2-digit", timeZone: "Asia/Jakarta" }
                : { day: "2-digit", month: "short", year: "numeric", timeZone: "Asia/Jakarta" });
        };
        const labels = {
            menunggu_pembayaran: "Menunggu pembayaran",
            menunggu_validasi: "Menunggu validasi",
            diproses: "Sedang diproses",
            selesai: "Selesai",
            ditolak: "Ditolak",
            dibatalkan: "Dibatalkan",
        };
        const statusBadge = (status) => `<span class="owner-status ${esc(status)}">${labels[status] || esc(status)}</span>`;
        if (!id) {
            container.innerHTML = '<p class="owner-error">Kode pesanan tidak valid.</p>';
            return;
        }
        const imageUrl = (value) => /^(https?:\/\/|\/)/i.test(value) ? value : "../" + String(value).replace(/^\.\//, "");
        const proofUrl = (path) => path ? imageUrl(path) : "";
        try {
            const order = await api("api/orders.php?id=" + id);
            $("#detail-status").outerHTML = statusBadge(order.status);
            const rawProof = order.bukti_bayar || "";
            const proof = rawProof
                ? `<a class="proof" href="${esc(proofUrl(rawProof))}" target="_blank" rel="noopener"><img src="${esc(proofUrl(rawProof))}" alt="Bukti transfer pelanggan"></a>`
                : '<div class="owner-proof-empty">Belum ada bukti pembayaran</div>';
            const receipt = `<div class="dbox"><b>Data pembayaran</b>
                <div class="jd"><span>Nama pengirim</span><b>${esc(order.nama_pembayar || "-")}</b></div>
                <div class="jd"><span>Nama pemesan</span><b>${esc(order.nama_pemesan || "-")}</b></div>
                <div class="jd"><span>Nominal tagihan</span><b>${money(order.total)}</b></div>
                <div class="jd"><span>Jadwal pengiriman</span><b>${esc(dateLabel(order.tgl_kirim, true))}</b></div></div>`;
            const validation = order.status === "menunggu_validasi";
            const summary = `<div class="dbox">
                <div class="row between"><div><small>${validation ? "VALIDASI PEMBAYARAN" : "DETAIL PESANAN"}</small><b class="owner-order-code">${esc(order.kode)}</b></div>${statusBadge(order.status)}</div>
                <hr><div class="jd"><span>Dibuat</span><b>${esc(dateLabel(order.created_at, true))}</b></div>
                <div class="jd"><span>Waktu status</span><b>${esc(dateLabel(order.status_updated_at, true))}</b></div>
                <div class="jd"><span>Sumber pesanan</span><b>${order.sumber === "offline" ? "Offline (dicatat owner)" : "Online"}</b></div>
                <div class="jd"><span>Nama penerima</span><b>${esc(order.nama_pemesan || "-")}</b></div>
                <div class="jd"><span>Kontak penerima</span><b>${esc(order.telepon_penerima || order.phone || "-")}</b></div>
                <div class="jd"><span>Alamat pengiriman</span><b>${esc(order.alamat_pengiriman || "-")}</b></div>
                <div class="jd"><span>Jadwal pengiriman</span><b>${esc(dateLabel(order.tgl_kirim, true))}</b></div>
                <div class="jd"><span>Catatan pesanan</span><b>${esc(order.keterangan || "-")}</b></div>
                <div class="jd owner-total"><span>Total pesanan</span><b>${money(order.total)}</b></div>
            </div>`;
            const paymentProof = `<div class="dbox"><b>Bukti transfer pelanggan</b>${proof}</div>${receipt}`;
            const rejection = order.status === "ditolak" && order.alasan_penolakan
                ? `<div class="dbox owner-rejection"><b>Alasan penolakan</b><p>${esc(order.alasan_penolakan)}</p></div>` : "";
            const menuItems = Array.isArray(order.menu) ? order.menu : [];
            const menu = `<div class="dbox"><div class="row between"><b>Daftar menu</b><span class="tag">${menuItems.length} jenis</span></div>
                ${order.status === "diproses" ? '<p class="owner-muted">Centang setiap menu setelah jumlah dan jenis makanan dipastikan sesuai.</p>' : ""}
                ${menuItems.map((item) => `<label class="owner-menu-row"><input type="checkbox" data-item-check="${Number(item.item_id)}" ${item.sudah_dicek ? "checked" : ""} ${order.status === "diproses" ? "" : "disabled"}>
                    <span><b>${esc(item.nama)}</b><small>${Number(item.qty).toLocaleString("id-ID")} porsi · ${money(item.harga)} / porsi</small></span><strong>${money(item.subtotal)}</strong></label>`).join("") || '<p class="owner-muted">Daftar menu tidak tersedia.</p>'}
            </div>`;
            const photos = Array.isArray(order.foto_kiriman_list) ? order.foto_kiriman_list : (order.foto_kiriman ? [order.foto_kiriman] : []);
            const dispatchPhotos = photos.length
                ? `<div class="owner-dispatch-photos">${photos.map((photo) => `<a href="${esc(imageUrl(photo))}" target="_blank" rel="noopener"><img src="${esc(imageUrl(photo))}" alt="Foto makanan yang akan dikirim"></a>`).join("")}</div>`
                : '<p class="owner-muted">Belum ada foto makanan yang diunggah.</p>';
            const dispatch = ["diproses", "selesai"].includes(order.status)
                ? `<div class="dbox"><b>Foto makanan siap kirim</b>${dispatchPhotos}
                    ${order.status === "diproses" ? '<form id="dispatch-photo-form" class="owner-upload"><label for="dispatch-photo">Tambah foto makanan yang akan dikirim</label><input id="dispatch-photo" name="foto_kiriman[]" type="file" accept="image/jpeg,image/png,image/webp" multiple required><small>Pilih satu atau beberapa foto JPG, PNG, atau WebP. Maksimal 2 MB per foto.</small><button class="owner-secondary">Simpan foto</button><span id="dispatch-photo-error" class="owner-error"></span></form>' : ""}
                </div>` : "";
            container.innerHTML = summary + (validation ? paymentProof : rejection) + menu + dispatch;

            const rejectionDialog = $("#reject-order-dialog");
            const rejectionForm = $("#reject-order-form");
            const rejectionReason = $("#rejection-reason");
            const rejectionError = $("#rejection-reason-error");
            $$("[data-cancel-rejection]").forEach((button) => button.onclick = () => rejectionDialog.close());
            const availableActions = {
                menunggu_validasi: ["reject", "validate"],
                diproses: ["complete"],
            }[order.status] || [];
            actions.hidden = !availableActions.length;
            $$("[data-order-action]").forEach((button) => {
                const action = button.dataset.orderAction;
                button.hidden = !availableActions.includes(action);
                button.onclick = async () => {
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
                    } else {
                        const confirmed = await confirmDialog(
                            action === "validate" ? "Validasi Pembayaran" : "Selesaikan Pesanan",
                            action === "validate"
                                ? "Validasi pembayaran pesanan ini? Status akan berubah menjadi Diproses."
                                : "Pesanan akan ditandai selesai dan siap dikirim. Pastikan semua menu sudah dicek dan foto makanan sudah diunggah.",
                            action === "validate" ? "Ya, Validasi" : "Ya, Selesaikan",
                        );
                        if (!confirmed) return;
                    }
                    button.disabled = true;
                    try {
                        await api("api/orders.php", { method: "POST", body: { id, action, ...(action === "reject" ? { alasan_penolakan: rejectionNote } : {}) } });
                        toast("Status pesanan diperbarui");
                        setTimeout(() => location.reload(), 500);
                    } catch (error) {
                        toast(error.message);
                        button.disabled = false;
                    }
                };
            });
            $$("[data-item-check]").forEach((checkbox) => {
                checkbox.onchange = async () => {
                    checkbox.disabled = true;
                    try {
                        await api("api/orders.php", {
                            method: "POST",
                            body: { action: "check_item", id, item_id: Number(checkbox.dataset.itemCheck), checked: checkbox.checked },
                        });
                    } catch (error) {
                        checkbox.checked = !checkbox.checked;
                        toast(error.message);
                    } finally {
                        checkbox.disabled = false;
                    }
                };
            });
            const upload = $("#dispatch-photo-form");
            upload?.addEventListener("submit", async (event) => {
                event.preventDefault();
                const input = $("#dispatch-photo");
                const photosToUpload = Array.from(input.files || []);
                const errorEl = $("#dispatch-photo-error");
                const button = $("button", upload);
                if (!photosToUpload.length) {
                    errorEl.textContent = "Pilih setidaknya satu foto.";
                    return;
                }
                if (photosToUpload.some((photo) => photo.size > 2 * 1024 * 1024)) {
                    errorEl.textContent = "Ukuran setiap foto maksimal 2 MB. Kecilkan foto lalu pilih kembali.";
                    return;
                }
                button.disabled = true;
                let uploadedCount = 0;
                try {
                    for (const photo of photosToUpload) {
                        const body = new FormData();
                        body.append("action", "upload_dispatch");
                        body.append("id", String(id));
                        body.append("foto_kiriman[]", photo);
                        await api("api/orders.php", { method: "POST", body });
                        uploadedCount++;
                    }
                    toast("Foto makanan tersimpan");
                    setTimeout(() => location.reload(), 450);
                } catch (error) {
                    errorEl.textContent = uploadedCount
                        ? `${uploadedCount} foto tersimpan, tetapi foto berikutnya gagal: ${error.message}`
                        : error.message;
                    button.disabled = false;
                    if (uploadedCount) setTimeout(() => location.reload(), 1800);
                }
            });
        } catch (error) {
            container.innerHTML = `<p class="owner-error" role="alert">${esc(error.message)}</p>`;
        }
    },
    async products() {
        let k = "",
            t;
        async function load() {
            const d = await api(
                `api/products.php?kategori=${k}&q=${encodeURIComponent($("#q").value)}`,
            );
            $("#cs").textContent = d.count.semua;
            if ($('#quota-summary'))$('#quota-summary').textContent='Kapasitas dapur '+d.maxKitchen+' porsi · Terpakai '+d.kitchenUsed+' porsi · Sisa '+d.kitchenRemaining+' porsi';
            $("#ck").textContent = d.count.kritis;
            $("#plist").innerHTML =
                d.items
                    .map((p, i) => {
                        const pct = p.kuota>0?(p.sisa / p.kuota) * 100:0;
                        const img = p.gambar ? `<img src="../${p.gambar}" alt="${p.nama}" onerror="this.src='../assets/img/produk/NK-01.png'">` : '<img src="../assets/img/produk/NK-01.png" alt="placeholder">';
                        const unavailable = d.tanggal_libur ? "libur" : p.sisa <= 0 ? "habis" : "";
                        return `<div class="pr" style="--i:${i}">${img}<span class="kode">${p.kode}</span><div><b>${p.nama}</b><small>${rp(p.harga)} · ${p.sisa}/${p.kuota} porsi${unavailable ? " · " + unavailable : ""}</small><div class="meter${!d.tanggal_libur && pct <= 20 ? " low" : ""}"><i data-w="${pct}"></i></div></div></div>`;
                    })
                    .join("") || '<p class="empty">Produk tidak ditemukan</p>';
            requestAnimationFrame(() =>
                requestAnimationFrame(() =>
                    $$(".meter i").forEach((i) => (i.style.width = i.dataset.w + "%")),
                ),
            );
        }
        $$("#chips button").forEach(
            (b) =>
                (b.onclick = () => {
                    k = b.dataset.k;
                    $$("#chips button").forEach((x) => x.classList.toggle("on", x === b));
                    load();
                }),
        );
        $("#q").oninput = () => {
            clearTimeout(t);
            t = setTimeout(load, 300);
        };
        load();
    },
};
(page === "login" || page === "register"
    ? PAGES.form
    : PAGES[page] || (() => {}))();
