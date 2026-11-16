/* Notifikasi perubahan menu — UI bersama Admin dan Owner (Stage 3). */
(() => {
    "use strict";

    const root = document.getElementById("mn-page");
    if (!root) return;

    const mode = root.dataset.mode === "owner" ? "owner" : "admin";
    const isOwner = mode === "owner";
    const apiUrl = root.dataset.api;
    const productsUrl = root.dataset.products || "";
    const today = root.dataset.today || "";

    const listEl = document.getElementById("mn-list");
    const statsEl = document.getElementById("mn-stats");
    const moreBtn = document.getElementById("mn-more");
    const refreshBtn = document.getElementById("mn-refresh");
    const detailDialog = document.getElementById("mn-detail-dialog");
    const detailBody = detailDialog?.querySelector("[data-mn-detail-body]");

    const html = (value) =>
        String(value ?? "").replace(/[&<>"']/g, (char) => ({
            "&": "&amp;",
            "<": "&lt;",
            ">": "&gt;",
            '"': "&quot;",
            "'": "&#39;",
        })[char]);

    const num = (value) => {
        const parsed = Number(value);
        return Number.isFinite(parsed) ? parsed : 0;
    };

    const money = (value) => "Rp " + num(value).toLocaleString("id-ID");

    const dateText = (value, withTime = false) => {
        if (!value) return "-";
        const date = new Date(value);
        if (Number.isNaN(date.getTime())) return "-";
        return date.toLocaleString(
            "id-ID",
            withTime
                ? {
                      day: "2-digit",
                      month: "short",
                      year: "numeric",
                      hour: "2-digit",
                      minute: "2-digit",
                      timeZone: "Asia/Jakarta",
                  }
                : {
                      day: "2-digit",
                      month: "short",
                      year: "numeric",
                      timeZone: "Asia/Jakarta",
                  },
        );
    };

    const orderLabels = {
        menunggu_pembayaran: "Menunggu pembayaran",
        menunggu_validasi: "Menunggu validasi",
        diproses: "Diproses",
        selesai: "Selesai",
        ditolak: "Ditolak",
        dibatalkan: "Dibatalkan",
    };

    const responseLabels = {
        menunggu_respons: "Menunggu respons",
        menunggu_konfirmasi: "Menunggu konfirmasi",
        menunggu_tindak_lanjut: "Menunggu tindak lanjut",
        dikonfirmasi: "Dikonfirmasi",
        selesai: "Selesai",
    };

    const responseTone = {
        menunggu_respons: "menunggu_validasi",
        menunggu_konfirmasi: "diproses",
        menunggu_tindak_lanjut: "diproses",
        dikonfirmasi: "selesai",
        selesai: "selesai",
    };

    const closureLabels = {
        penolakan_pengganti: "Penggantian ditolak Admin",
        pembatalan_pesanan: "Notifikasi ditutup karena pesanan dibatalkan",
        kedaluwarsa_pesanan: "Notifikasi ditutup karena pembayaran kedaluwarsa",
    };

    const badge = (label, tone = "") =>
        `<span class="${isOwner ? "owner-status" : "status-badge"} ${html(tone)}">${html(label)}</span>`;

    const orderBadge = (status) =>
        badge(
            orderLabels[status] || status || "-",
            isOwner || ["diproses", "selesai", "ditolak"].includes(status) ? status : "",
        );

    const responseBadge = (status) =>
        badge(responseLabels[status] || status || "-", responseTone[status] || "");

    const secondaryButton = (label, attributes) =>
        isOwner
            ? `<button type="button" class="owner-secondary" ${attributes}>${html(label)}</button>`
            : `<button type="button" class="button secondary small" ${attributes}>${html(label)}</button>`;

    let offset = 0;
    let total = 0;
    let activeNoticeId = 0;
    let stats = { notices: 0, targets: 0, orders: 0, pending: 0 };

    function renderStats() {
        if (!statsEl) return;
        const scope = isOwner ? "Dari riwayat yang termuat" : "Dari daftar yang termuat";
        statsEl.innerHTML = `
            <article class="mn-stat">
                <small>Notifikasi</small>
                <b>${stats.notices.toLocaleString("id-ID")}</b>
                <span>${html(scope)}</span>
            </article>
            <article class="mn-stat">
                <small>Pelanggan terdampak</small>
                <b>${stats.targets.toLocaleString("id-ID")}</b>
                <span>${stats.orders.toLocaleString("id-ID")} pesanan</span>
            </article>
            <article class="mn-stat">
                <small>Menunggu respons</small>
                <b>${stats.pending.toLocaleString("id-ID")}</b>
                <span>Pelanggan belum merespons</span>
            </article>`;
    }

    function historyItem(item) {
        const pending = num(item.belum_merespons);
        const offline = num(item.offline);
        return `
            <article class="mn-item">
                <div>
                    <div class="mn-item-title">
                        <b>${html(item.nama_produk_snapshot)}</b>
                        <small>${html(item.kode_produk_snapshot)} · ${html(dateText(item.created_at, true))} · oleh ${html(item.created_by_username)}</small>
                    </div>
                    <p class="mn-item-note">${html(item.catatan_admin)}</p>
                    <div class="mn-chips">
                        <span class="mn-chip strong">${num(item.jumlah_target)} penerima</span>
                        <span class="mn-chip">${num(item.jumlah_pesanan)} pesanan</span>
                        ${offline > 0 ? `<span class="mn-chip offline">${offline} offline</span>` : ""}
                        <span class="mn-chip${pending > 0 ? " warn" : ""}">${pending > 0 ? pending + " menunggu respons" : "Semua merespons"}</span>
                    </div>
                </div>
                ${secondaryButton("Lihat detail", `data-mn-detail="${num(item.id)}"`)}
            </article>`;
    }

    async function loadList(reset = true) {
        if (!listEl) return;
        if (reset) {
            offset = 0;
            total = 0;
            stats = { notices: 0, targets: 0, orders: 0, pending: 0 };
            listEl.innerHTML = '<p class="mn-list-empty">Memuat notifikasi…</p>';
            if (moreBtn) moreBtn.hidden = true;
        }

        try {
            const data = await api(`${apiUrl}?action=list&limit=20&offset=${offset}`);
            const items = Array.isArray(data.items) ? data.items : [];
            total = num(data.total);
            if (reset) listEl.innerHTML = "";
            if (!items.length && reset) {
                listEl.innerHTML =
                    '<p class="mn-list-empty">Belum ada notifikasi perubahan menu.</p>';
            } else {
                listEl.insertAdjacentHTML("beforeend", items.map(historyItem).join(""));
            }
            items.forEach((item) => {
                stats.notices += 1;
                stats.targets += num(item.jumlah_target);
                stats.orders += num(item.jumlah_pesanan);
                stats.pending += num(item.belum_merespons);
            });
            offset += items.length;
            renderStats();
            if (moreBtn) moreBtn.hidden = offset >= total || !items.length;
        } catch (error) {
            listEl.innerHTML = `<p class="mn-list-empty">${html(error.message)}</p>`;
            if (moreBtn) moreBtn.hidden = true;
        }
    }

    function recipientCard(recipient) {
        const customer = recipient.pelanggan || {};
        const order = recipient.pesanan || {};
        const oldMenu = recipient.menu_lama || {};
        const replacement = recipient.menu_pengganti;
        const offline = customer.kanal === "offline";
        const differenceHandled = recipient.status_penyelesaian_selisih === "selesai";
        const paymentInfo = recipient.pembayaran || null;
        const priceInfo = recipient.info_harga || null;
        const priceNote = priceInfo && priceInfo.perbedaan_harga_katalog !== null
            && priceInfo.perbedaan_harga_katalog !== undefined
            && priceInfo.perbedaan_harga_katalog !== 0
            ? `Harga katalog saat konfirmasi ${money(priceInfo.harga_katalog_sekarang)} berbeda ${priceInfo.perbedaan_harga_katalog > 0 ? "lebih" : "lebih rendah"} ${money(Math.abs(priceInfo.perbedaan_harga_katalog))} dari harga saat pelanggan memilih (${money(priceInfo.harga_snapshot)}). Total pesanan memakai harga saat pelanggan memilih.`
            : "";
        let difference = "";
        let actions = "";

        if (replacement && recipient.selisih_total !== null) {
            const value = num(recipient.selisih_total);
            if (value > 0) {
                difference = `<div class="mn-selisih pay">Selisih ${money(value)} menjadi tanggungan pelanggan. ${differenceHandled ? "Penyelesaian manual sudah dicatat." : "Penagihan dilakukan manual oleh Admin."}</div>`;
            } else if (value < 0) {
                difference = `<div class="mn-selisih refund">Pelanggan menerima pengembalian ${money(Math.abs(value))}. ${differenceHandled ? "Penyelesaian manual sudah dicatat." : "Pengembalian dilakukan manual oleh Admin."}</div>`;
            } else {
                difference = `<div class="mn-selisih">Tidak ada selisih harga.</div>`;
            }
        }
        if (!isOwner && recipient.status_respons === "menunggu_konfirmasi" && replacement) {
            actions += secondaryButton(
                "Setujui dan ganti menu",
                `data-mn-action="confirm" data-recipient="${num(recipient.id)}"`,
            );
            actions += `
                <label class="mn-followup">
                    Alasan penolakan penggantian
                    <textarea data-reject-reason="${num(recipient.id)}" rows="2" maxlength="1000"
                        placeholder="Jelaskan alasan penggantian ditolak"></textarea>
                </label>
                ${secondaryButton(
                    "Tolak penggantian",
                    `data-mn-action="reject_replacement" data-recipient="${num(recipient.id)}"`,
                )}`;
        }
        if (
            !isOwner &&
            recipient.status_respons === "dikonfirmasi" &&
            ["menunggu_tagihan", "menunggu_pengembalian"].includes(
                recipient.status_penyelesaian_selisih,
            )
        ) {
            actions += secondaryButton(
                "Tandai selisih selesai",
                `data-mn-action="settle_difference" data-recipient="${num(recipient.id)}"`,
            );
        }
        if (
            !isOwner &&
            (recipient.status_respons === "menunggu_tindak_lanjut" ||
                (offline && recipient.status_respons === "menunggu_respons"))
        ) {
            actions += `
                <label class="mn-followup">
                    Catatan tindak lanjut manual
                    <textarea data-followup-note="${num(recipient.id)}" rows="2" maxlength="1000"
                        placeholder="Catat hasil menghubungi pelanggan"></textarea>
                </label>
                ${secondaryButton(
                    "Tandai tindak lanjut selesai",
                    `data-mn-action="complete_follow_up" data-recipient="${num(recipient.id)}"`,
                )}`;
        }

        return `
            <article class="mn-recipient">
                <div class="mn-recipient-head">
                    <b>${html(customer.nama || order.nama_pemesan || "-")}</b>
                    <span class="mn-chip${offline ? " offline" : ""}">${offline ? "Offline" : "Online"}</span>
                    ${responseBadge(recipient.status_respons)}
                    ${recipient.opsi ? `<span class="mn-chip strong">${html(recipient.opsi.label)}</span>` : '<span class="mn-chip">Belum memilih tindakan</span>'}
                </div>
                <div class="mn-recipient-grid">
                    <div class="mn-kv">
                        <small>Pesanan</small>
                        <span>${html(order.kode || "-")} · ${html(orderLabels[order.status] || order.status || "-")}</span>
                    </div>
                    <div class="mn-kv">
                        <small>Tanggal kirim</small>
                        <span>${html(order.tanggal_kirim ? dateText(order.tanggal_kirim) : "-")}</span>
                    </div>
                    <div class="mn-kv">
                        <small>Kontak</small>
                        <span>${offline ? "Pesanan offline — hubungi manual" : html(customer.telepon || customer.email || "-")}</span>
                    </div>
                    <div class="mn-kv">
                        <small>Menu lama</small>
                        <span>${html(oldMenu.nama || "-")} · ${num(oldMenu.qty)} × ${money(oldMenu.harga)} = ${money(oldMenu.total)}</span>
                    </div>
                    ${replacement ? `
                    <div class="mn-kv">
                        <small>Menu pengganti</small>
                        <span>${html(replacement.nama || "-")} · ${num(replacement.qty)} × ${money(replacement.harga)} = ${money(replacement.total)}</span>
                    </div>` : `
                    <div class="mn-kv">
                        <small>Menu pengganti</small>
                        <span>Belum dipilih pelanggan</span>
                    </div>`}
                    <div class="mn-kv">
                        <small>Respons</small>
                        <span>${recipient.responded_at ? html(dateText(recipient.responded_at, true)) : "Belum merespons"}</span>
                    </div>
                    ${recipient.total_pesanan_lama !== null && recipient.total_pesanan_baru !== null ? `
                    <div class="mn-kv">
                        <small>Total pesanan setelah perubahan</small>
                        <span>${money(recipient.total_pesanan_lama)} → ${money(recipient.total_pesanan_baru)}</span>
                    </div>` : ""}
                    ${paymentInfo ? `
                    <div class="mn-kv">
                        <small>Pembayaran</small>
                        <span>${html(paymentInfo.tahap_pembayaran || paymentInfo.status_pembayaran || "-")}</span>
                    </div>
                    <div class="mn-kv">
                        <small>Tagihan</small>
                        <span>${paymentInfo.nominal_tagihan !== null && paymentInfo.nominal_tagihan !== undefined ? money(paymentInfo.nominal_tagihan) : "-"}</span>
                    </div>
                    <div class="mn-kv">
                        <small>Bukti bayar</small>
                        <span>${paymentInfo.bukti_bayar ? `Tersimpan (${html(paymentInfo.bukti_bayar)})` : "Belum ada bukti"}</span>
                    </div>` : ""}
                </div>
                ${priceNote ? `<div class="mn-selisih">${html(priceNote)}</div>` : ""}
                ${recipient.catatan_respons ? `<div class="mn-selisih">Catatan pelanggan: ${html(recipient.catatan_respons)}</div>` : ""}
                ${difference}
                ${recipient.jenis_penutupan ? `<div class="mn-selisih">${html(closureLabels[recipient.jenis_penutupan] || "Notifikasi ditutup")}${recipient.alasan_penutupan ? ": " + html(recipient.alasan_penutupan) : ""}</div>` : ""}
                ${recipient.catatan_tindak_lanjut ? `<div class="mn-selisih">Catatan tindak lanjut: ${html(recipient.catatan_tindak_lanjut)}</div>` : ""}
                ${actions ? `<div class="mn-actions">${actions}</div>` : ""}
                <p class="form-error" data-mn-action-error="${num(recipient.id)}" role="alert"></p>
            </article>`;
    }

    function detailHtml(data) {
        const product = data.produk || {};
        const summary = data.ringkasan || {};
        const recipients = Array.isArray(data.penerima) ? data.penerima : [];
        const options = Array.isArray(data.opsi) ? data.opsi : [];
        const created = data.created_by || {};

        return `
            <div class="mn-detail-meta">
                <span class="mn-chip strong">${html(product.kode || "-")}</span>
                <small class="mn-hint">Dibuat ${html(dateText(data.created_at, true))} oleh ${html(created.username || "-")}</small>
            </div>
            <p class="mn-detail-note">${html(data.catatan_admin)}</p>
            <div class="mn-chips" style="margin-bottom:12px">
                <span class="mn-chip strong">${num(summary.jumlah_target)} penerima</span>
                <span class="mn-chip">${num(summary.jumlah_pesanan)} pesanan</span>
                <span class="mn-chip">${num(summary.online)} online</span>
                <span class="mn-chip offline">${num(summary.offline)} offline</span>
                <span class="mn-chip${num(summary.belum_merespons) > 0 ? " warn" : ""}">${num(summary.sudah_merespons)} sudah merespons · ${num(summary.belum_merespons)} belum</span>
            </div>
            <div class="mn-chips" style="margin-bottom:14px">
                ${options.map((option) => `<span class="mn-chip">${html(option.label)}</span>`).join("")}
            </div>
            <div class="mn-recipients">
                ${recipients.length ? recipients.map(recipientCard).join("") : '<p class="mn-list-empty">Belum ada penerima.</p>'}
            </div>
            <p class="mn-hint" style="margin-top:14px">Pilihan menu baru berlaku setelah disetujui Admin. Penagihan atau pengembalian selisih tetap dilakukan manual; pesanan offline juga perlu dihubungi manual.</p>`;
    }

    async function openDetail(id) {
        if (!detailDialog || !detailBody) return;
        activeNoticeId = num(id);
        detailBody.innerHTML = '<p class="mn-list-empty">Memuat detail…</p>';
        if (!detailDialog.open) detailDialog.showModal();
        try {
            const data = await api(`${apiUrl}?action=detail&id=${encodeURIComponent(id)}`);
            detailBody.innerHTML = detailHtml(data);
        } catch (error) {
            detailBody.innerHTML = `<p class="mn-list-empty">${html(error.message)}</p>`;
        }
    }

    listEl?.addEventListener("click", (event) => {
        const button = event.target.closest("[data-mn-detail]");
        if (button) openDetail(button.dataset.mnDetail);
    });

    detailBody?.addEventListener("click", async (event) => {
        const button = event.target.closest("[data-mn-action]");
        if (!button || isOwner) return;

        const action = button.dataset.mnAction;
        const recipientId = num(button.dataset.recipient);
        const requestBody = { action, id: recipientId };
        let title = "Konfirmasi tindakan";
        let message = "Lanjutkan tindakan ini?";
        let label = "Ya, lanjutkan";

        if (action === "confirm") {
            title = "Setujui penggantian menu?";
            message = "Menu dan total pesanan akan diperbarui sesuai pilihan pelanggan. Status pesanan dan pembayaran tidak berubah.";
            label = "Ya, setujui";
        } else if (action === "reject_replacement") {
            const reason =
                detailBody
                    .querySelector(`[data-reject-reason="${recipientId}"]`)
                    ?.value.trim() || "";
            if (!reason) {
                const error = detailBody.querySelector(`[data-mn-action-error="${recipientId}"]`);
                if (error) error.textContent = "Alasan penolakan penggantian wajib diisi.";
                return;
            }
            requestBody.alasan_penolakan = reason;
            title = "Tolak penggantian menu?";
            message =
                "Pesanan, total, dan pembayaran tidak berubah. Pelanggan akan melihat alasan penolakan ini.";
            label = "Ya, tolak";
        } else if (action === "settle_difference") {
            title = "Tandai selisih selesai?";
            message = "Pastikan penagihan atau pengembalian sudah dilakukan secara manual di luar aplikasi.";
            label = "Tandai selesai";
        } else if (action === "complete_follow_up") {
            const note = detailBody.querySelector(`[data-followup-note="${recipientId}"]`)?.value.trim() || "";
            if (!note) {
                const error = detailBody.querySelector(`[data-mn-action-error="${recipientId}"]`);
                if (error) error.textContent = "Catatan hasil tindak lanjut wajib diisi.";
                return;
            }
            requestBody.catatan_tindak_lanjut = note;
            title = "Selesaikan tindak lanjut?";
            message = "Pastikan pelanggan sudah dihubungi dan catatan hasilnya sudah benar.";
            label = "Ya, selesai";
        } else {
            return;
        }

        const confirmed = await confirmDialog(title, message, label, "primary");
        if (!confirmed) return;

        const originalLabel = button.textContent;
        button.disabled = true;
        button.textContent = "Memproses…";
        const error = detailBody.querySelector(`[data-mn-action-error="${recipientId}"]`);
        if (error) error.textContent = "";
        try {
            await api(apiUrl, { method: "POST", body: requestBody });
            toast(
                action === "confirm"
                    ? "Penggantian menu disetujui; status pesanan dan pembayaran tidak berubah."
                    : action === "reject_replacement"
                      ? "Penggantian menu ditolak; pesanan, total, dan pembayaran tidak berubah."
                      : action === "settle_difference"
                        ? "Penyelesaian selisih dicatat."
                        : "Tindak lanjut manual dicatat.",
            );
            await loadList(true);
            await openDetail(activeNoticeId);
        } catch (requestError) {
            if (error) error.textContent = requestError.message;
            button.disabled = false;
            button.textContent = originalLabel;
        }
    });

    refreshBtn?.addEventListener("click", () => loadList(true));
    moreBtn?.addEventListener("click", () => loadList(false));

    [detailDialog].forEach((dialog) => {
        if (!dialog) return;
        dialog.querySelectorAll("[data-mn-close]").forEach((button) => {
            button.addEventListener("click", () => dialog.close());
        });
        dialog.addEventListener("click", (event) => {
            if (event.target === dialog) dialog.close();
        });
    });

    if (!isOwner) initCreateForm();

    function initCreateForm() {
        const createDialog = document.getElementById("mn-create-dialog");
        const form = document.getElementById("mn-create-form");
        const newBtn = document.getElementById("mn-new");
        if (!createDialog || !form || !newBtn) return;

        const productSelect = document.getElementById("mn-product");
        const dateInput = document.getElementById("mn-date");
        const noteInput = document.getElementById("mn-note");
        const hint = document.getElementById("mn-product-hint");
        const previewBtn = document.getElementById("mn-preview");
        const previewBox = document.getElementById("mn-preview-result");
        const errorEl = document.getElementById("mn-create-error");
        const submitBtn = document.getElementById("mn-submit");

        let previewProductId = null;
        let previewReady = 0;

        const resetPreview = () => {
            previewProductId = null;
            previewReady = 0;
            if (previewBox) {
                previewBox.hidden = true;
                previewBox.innerHTML = "";
            }
            if (submitBtn) submitBtn.disabled = true;
        };

        const selectedProduct = () => {
            const id = Number(productSelect?.value);
            return Number.isFinite(id) && id > 0 ? id : 0;
        };

        const showHint = () => {
            if (!hint) return;
            const option = productSelect?.selectedOptions?.[0];
            const sisa = option ? num(option.dataset.sisa) : null;
            hint.textContent = option
                ? sisa > 0
                    ? `Sisa ${sisa} porsi pada tanggal acuan. Target notifikasi tetap dihitung dari tanggal kirim tiap pesanan.`
                    : "Menu ini habis pada tanggal acuan. Target notifikasi dihitung dari tanggal kirim tiap pesanan."
                : "Pilih menu yang tidak tersedia.";
        };

        async function loadProducts() {
            if (!productSelect) return;
            productSelect.disabled = true;
            productSelect.innerHTML = '<option value="">Memuat menu…</option>';
            resetPreview();
            try {
                const query = dateInput?.value ? `?tgl_kirim=${encodeURIComponent(dateInput.value)}` : "";
                const data = await api(`${productsUrl}${query}`);
                const items = Array.isArray(data.items) ? data.items : [];
                productSelect.innerHTML =
                    '<option value="">Pilih menu</option>' +
                    items
                        .map(
                            (product) =>
                                `<option value="${num(product.id)}" data-sisa="${num(product.sisa)}">${html(product.kode)} — ${html(product.nama)}</option>`,
                        )
                        .join("");
                showHint();
            } catch (error) {
                productSelect.innerHTML = '<option value="">Menu gagal dimuat</option>';
                if (errorEl) errorEl.textContent = error.message;
            } finally {
                productSelect.disabled = false;
            }
        }

        function previewHtml(data) {
            const summary = data.ringkasan || {};
            const targets = Array.isArray(data.items) ? data.items : [];
            const product = data.produk || {};
            return `
                <div class="mn-preview-head">
                    <b>${html(product.nama || "-")}</b>
                    <span class="mn-chip strong">${num(summary.jumlah_target)} target</span>
                    <span class="mn-chip">${num(summary.jumlah_pesanan)} pesanan</span>
                    <span class="mn-chip">${num(summary.online)} online</span>
                    <span class="mn-chip offline">${num(summary.offline)} offline</span>
                </div>
                ${num(summary.sudah_dinotifikasi) > 0 ? `<div class="mn-warning">${num(summary.sudah_dinotifikasi)} pesanan sudah punya notifikasi berjalan dan akan dilewati.</div>` : ""}
                ${targets.length
                    ? `<ul class="mn-target-list">${targets
                          .map((target) => {
                              const order = target.pesanan || {};
                              const customer = target.pelanggan || {};
                              const offline = customer.kanal === "offline";
                              return `
                                <li class="mn-target">
                                    <div class="mn-target-head">
                                        <b>${html(order.kode || "-")}</b>
                                        ${orderBadge(order.status)}
                                        <span class="mn-chip${offline ? " offline" : ""}">${offline ? "Offline" : "Online"}</span>
                                    </div>
                                    <span class="mn-target-line">${html(customer.nama || "-")} · ${offline ? "hubungi manual" : html(customer.telepon || "-")} · kirim ${html(order.tanggal_kirim ? dateText(order.tanggal_kirim) : "-")}</span>
                                    <span class="mn-target-line">${num(target.qty)} × ${money(target.harga)} = <b>${money(target.subtotal)}</b></span>
                                    ${target.sudah_dinotifikasi ? '<span class="mn-warning">Sudah dinotifikasi — akan dilewati.</span>' : ""}
                                </li>`;
                          })
                          .join("")}</ul>`
                    : '<p class="mn-list-empty">Tidak ada pesanan aktif yang terdampak pada menu ini.</p>'}`;
        }

        async function runPreview() {
            const productId = selectedProduct();
            if (!productId) {
                if (errorEl) errorEl.textContent = "Pilih menu terlebih dahulu.";
                return;
            }
            if (errorEl) errorEl.textContent = "";
            previewBtn.disabled = true;
            previewBtn.textContent = "Menghitung target…";
            try {
                const data = await api(`${apiUrl}?action=preview&produk_id=${encodeURIComponent(productId)}`);
                previewProductId = productId;
                previewReady = num(data.ringkasan?.siap_dinotifikasi);
                if (previewBox) {
                    previewBox.hidden = false;
                    previewBox.innerHTML = previewHtml(data);
                }
                if (submitBtn) {
                    submitBtn.disabled = previewReady < 1;
                    submitBtn.textContent =
                        previewReady > 0 ? `Kirim ke ${previewReady} pesanan` : "Tidak ada target baru";
                }
                if (previewReady < 1) {
                    if (errorEl)
                        errorEl.textContent =
                            "Tidak ada pesanan aktif yang belum dinotifikasi untuk menu ini.";
                }
            } catch (error) {
                resetPreview();
                if (previewBox) previewBox.hidden = true;
                if (errorEl) errorEl.textContent = error.message;
            } finally {
                previewBtn.disabled = false;
                previewBtn.textContent = "Preview target";
            }
        }

        newBtn.addEventListener("click", () => {
            if (errorEl) errorEl.textContent = "";
            resetPreview();
            if (submitBtn) submitBtn.textContent = "Kirim notifikasi";
            if (dateInput && !dateInput.value) dateInput.value = today;
            if (!createDialog.open) createDialog.showModal();
            loadProducts();
        });

        createDialog.querySelectorAll("[data-mn-close]").forEach((button) => {
            button.addEventListener("click", () => createDialog.close());
        });
        createDialog.addEventListener("click", (event) => {
            if (event.target === createDialog) createDialog.close();
        });

        productSelect?.addEventListener("change", () => {
            resetPreview();
            showHint();
            if (errorEl) errorEl.textContent = "";
        });
        dateInput?.addEventListener("change", () => {
            resetPreview();
            loadProducts();
        });
        previewBtn?.addEventListener("click", runPreview);

        form.addEventListener("submit", async (event) => {
            event.preventDefault();
            const productId = selectedProduct();
            if (!productId) {
                if (errorEl) errorEl.textContent = "Pilih menu terlebih dahulu.";
                return;
            }
            if (previewProductId !== productId) {
                if (errorEl) errorEl.textContent = "Jalankan preview target untuk menu ini terlebih dahulu.";
                return;
            }
            if (previewReady < 1) {
                if (errorEl) errorEl.textContent = "Tidak ada pesanan aktif yang dapat dinotifikasi.";
                return;
            }
            const note = String(noteInput?.value || "").trim();
            if (!note) {
                if (errorEl) errorEl.textContent = "Catatan Admin wajib diisi.";
                noteInput?.focus();
                return;
            }
            const options = [...form.querySelectorAll('input[name="opsi"]:checked')].map(
                (input) => input.value,
            );
            if (!options.length) {
                if (errorEl) errorEl.textContent = "Pilih minimal satu tindakan pelanggan.";
                return;
            }

            const confirmed = await confirmDialog(
                "Kirim notifikasi?",
                `Notifikasi akan dikirim ke ${previewReady} pesanan yang terdampak. Pesanan offline tetap perlu dihubungi manual.`,
                "Ya, Kirim",
                "primary",
            );
            if (!confirmed) return;

            if (errorEl) errorEl.textContent = "";
            submitBtn.disabled = true;
            submitBtn.textContent = "Mengirim…";
            try {
                const result = await api(apiUrl, {
                    method: "POST",
                    body: {
                        action: "create",
                        produk_id: productId,
                        catatan_admin: note,
                        opsi: options,
                    },
                });
                toast(
                    `Notifikasi terkirim ke ${num(result.jumlah_target)} penerima` +
                        (num(result.dilewati_sudah_dinotifikasi) > 0
                            ? `, ${num(result.dilewati_sudah_dinotifikasi)} dilewati.`
                            : "."),
                );
                createDialog.close();
                form.reset();
                if (dateInput) dateInput.value = today;
                resetPreview();
                if (submitBtn) submitBtn.textContent = "Kirim notifikasi";
                if (hint) hint.textContent = "Pilih menu yang tidak tersedia.";
                loadList(true);
            } catch (error) {
                if (errorEl) errorEl.textContent = error.message;
                submitBtn.disabled = false;
                submitBtn.textContent = `Kirim ke ${previewReady} pesanan`;
            }
        });
    }

    loadList(true);
})();
