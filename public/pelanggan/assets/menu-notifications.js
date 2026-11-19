/* Notifikasi perubahan menu — UI pelanggan (Stage 4). */
(() => {
    "use strict";

    const root = document.getElementById("mnc-page");
    if (!root) return;

    const apiUrl = root.dataset.api || "api/menu-notifications.php";
    const listEl = document.getElementById("mnc-list");
    const summaryEl = document.getElementById("mnc-summary");
    const moreBtn = document.getElementById("mnc-more");
    const refreshBtn = document.getElementById("mnc-refresh");
    const dialog = document.getElementById("mnc-detail-dialog");
    const detailEl = document.getElementById("mnc-detail");

    if (typeof wireLogout === "function") wireLogout();

    const num = (value) => {
        const parsed = Number(value);
        return Number.isFinite(parsed) ? parsed : 0;
    };

    const html = (value) =>
        String(value ?? "").replace(/[&<>"']/g, (char) => ({
            "&": "&amp;",
            "<": "&lt;",
            ">": "&gt;",
            '"': "&quot;",
            "'": "&#39;",
        })[char]);

    const rupiah = (value) => "Rp " + num(value).toLocaleString("id-ID");

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
        menunggu_validasi: "Menunggu verifikasi",
        diproses: "Diproses",
        selesai: "Selesai",
        ditolak: "Ditolak",
        dibatalkan: "Dibatalkan",
    };

    const responseLabels = {
        menunggu_respons: "Menunggu respons",
        menunggu_konfirmasi: "Menunggu konfirmasi Admin",
        menunggu_tindak_lanjut: "Menunggu tindak lanjut Admin",
        dikonfirmasi: "Dikonfirmasi Admin",
        selesai: "Selesai",
    };

    const optionHints = {
        menu_pengganti: "Pilih menu lain yang tersedia pada tanggal kirim.",
        hubungi_admin: "Admin akan menghubungi kamu untuk membahas solusinya.",
        tunggu_informasi: "Kamu akan dihubungi lagi saat informasi sudah tersedia.",
    };

    const closureLabels = {
        penolakan_pengganti: "Penggantian ditolak Admin",
        pembatalan_pesanan: "Notifikasi ditutup karena pesanan dibatalkan",
        kedaluwarsa_pesanan: "Notifikasi ditutup karena pembayaran kedaluwarsa",
    };

    let offset = 0;
    let total = 0;
    let unread = 0;
    let activeNoticeId = null;

    function syncBadge(count) {
        unread = Math.max(0, num(count));
        document.querySelectorAll("[data-notif-count]").forEach((badge) => {
            badge.textContent = String(unread);
            badge.hidden = unread < 1;
        });
        renderSummary();
    }

    function renderSummary() {
        if (!summaryEl) return;
        if (total < 1) {
            summaryEl.textContent = "Belum ada notifikasi perubahan menu.";
            return;
        }
        summaryEl.textContent =
            total + " notifikasi · " + unread + " belum dibaca";
    }

    function statusClass(status) {
        if (status === "menunggu_respons") return "wait";
        if (status === "dikonfirmasi" || status === "selesai") return "done";
        return "wait";
    }

    function responseChip(status) {
        return `<span class="mnc-status ${statusClass(status)}">${html(
            responseLabels[status] || status || "-",
        )}</span>`;
    }

    function progressChip(item) {
        const statuses = Array.isArray(item.status_respons) ? item.status_respons : [];
        const recipients = num(item.jumlah_penerima);
        const responded = num(item.sudah_merespons);

        if (statuses.includes("menunggu_respons")) {
            return `<span class="mnc-chip warn">${responded} dari ${recipients} sudah merespons</span>`;
        }
        if (statuses.includes("menunggu_konfirmasi")) {
            return '<span class="mnc-chip warn">Menunggu konfirmasi Admin</span>';
        }
        if (statuses.includes("menunggu_tindak_lanjut")) {
            return '<span class="mnc-chip warn">Menunggu tindak lanjut Admin</span>';
        }
        return '<span class="mnc-chip ok">Sudah diproses Admin</span>';
    }

    function cardHtml(item, index) {
        const product = item.produk || {};
        const isUnread = num(item.belum_dibaca) > 0;
        return `
            <button type="button" class="mnc-card${isUnread ? " unread" : ""}" data-notice="${num(item.id)}" style="--i:${index}">
                <span class="mnc-card-top">
                    <span>
                        <span class="eyebrow">PERUBAHAN MENU</span>
                        <b class="mnc-card-title">${html(product.nama || "-")}</b>
                        <span class="mnc-card-date">${html(product.kode || "-")} · ${html(dateText(item.created_at, true))}</span>
                    </span>
                    ${isUnread
                        ? '<span class="mnc-unread-chip">Belum dibaca</span>'
                        : '<span class="mnc-read-chip">Sudah dibaca</span>'}
                </span>
                <span class="mnc-note">${html(item.catatan_admin || "Admin belum menuliskan catatan.")}</span>
                <span class="mnc-chips">
                    <span class="mnc-chip">${num(item.jumlah_pesanan)} pesanan</span>
                    <span class="mnc-chip">${num(item.jumlah_penerima)} menu terdampak</span>
                    ${progressChip(item)}
                </span>
                <span class="mnc-open">Lihat detail &rarr;</span>
            </button>`;
    }

    async function loadList(reset = true) {
        if (!listEl) return;
        if (reset) {
            offset = 0;
            total = 0;
            listEl.innerHTML = '<p class="mnc-empty">Memuat notifikasi…</p>';
            if (moreBtn) moreBtn.hidden = true;
        }

        try {
            const data = await api(
                `${apiUrl}?action=list&limit=20&offset=${offset}`,
            );
            const items = Array.isArray(data.items) ? data.items : [];
            total = num(data.total);
            if (reset) listEl.innerHTML = "";

            if (!items.length && reset) {
                listEl.innerHTML =
                    '<p class="mnc-empty">Belum ada notifikasi perubahan menu untuk pesananmu.</p>';
            } else {
                listEl.insertAdjacentHTML(
                    "beforeend",
                    items.map((item, index) => cardHtml(item, offset + index)).join(""),
                );
            }

            offset += items.length;
            if (moreBtn) moreBtn.hidden = offset >= total || !items.length;
            syncBadge(data.belum_dibaca);
        } catch (error) {
            if (reset) {
                listEl.innerHTML = `<p class="mnc-empty">${html(error.message)}</p>`;
            }
            if (moreBtn) moreBtn.hidden = true;
        }
    }

    function requirementHtml(recipient) {
        const order = recipient.pesanan || {};
        if (order.tanggal_kirim) {
            return `Dikirim ${html(dateText(order.tanggal_kirim))}`;
        }
        return html(order.tgl_kirim ? dateText(order.tgl_kirim) : "-");
    }

    function answeredHtml(recipient) {
        const replacement = recipient.menu_pengganti;
        const status = recipient.status_respons;
        const differenceHandled = recipient.status_penyelesaian_selisih === "selesai";
        const difference = recipient.selisih_total === null
            ? ""
            : num(recipient.selisih_total) > 0
              ? `Selisih ${rupiah(recipient.selisih_total)} menjadi tagihan pelanggan. ${differenceHandled ? "Penyelesaian manual sudah dicatat." : "Admin akan menindaklanjuti secara manual."}`
              : num(recipient.selisih_total) < 0
                ? `Selisih ${rupiah(Math.abs(num(recipient.selisih_total)))} menjadi pengembalian. ${differenceHandled ? "Penyelesaian manual sudah dicatat." : "Admin akan menindaklanjuti secara manual."}`
                : "Tidak ada selisih harga.";
        let lockMessage =
            "Pilihanmu sudah dikirim dan menunggu Admin. Perubahan menu belum berlaku sampai Admin mengonfirmasi.";
        if (status === "menunggu_tindak_lanjut") {
            lockMessage =
                "Pilihanmu sudah dicatat. Admin akan menindaklanjuti dan menghubungimu. Perubahan menu belum berlaku.";
        } else if (status === "dikonfirmasi") {
            lockMessage =
                "Admin sudah mengonfirmasi perubahan ini. Terima kasih sudah merespons.";
        } else if (status === "selesai") {
            lockMessage =
                recipient.jenis_penutupan === "penolakan_pengganti"
                    ? "Admin menolak pilihan menu pengganti. Pesanan, total, dan pembayaran tidak berubah. Hubungi Admin bila perlu bantuan."
                    : recipient.jenis_penutupan === "pembatalan_pesanan"
                      ? "Pesanan dibatalkan sehingga notifikasi ini ditutup."
                      : recipient.jenis_penutupan === "kedaluwarsa_pesanan"
                        ? "Pembayaran kedaluwarsa sehingga pesanan dibatalkan dan notifikasi ditutup."
                        : "Perubahan menu untuk pesanan ini sudah selesai diproses.";
        } else if (status === "menunggu_respons") {
            lockMessage =
                "Pesanan ini sudah tidak aktif sehingga pilihan tidak dapat dikirim. Hubungi Admin bila perlu bantuan.";
        }

        return `
            <div class="mnc-answered">
                <div class="mnc-answer-row">
                    <span>Pilihanmu</span>
                    <b>${html(recipient.opsi?.label || "Belum memilih tindakan")}</b>
                </div>
                ${replacement
                    ? `<div class="mnc-answer-row"><span>Menu pengganti</span><b>${html(
                          replacement.nama || "-",
                      )} · ${num(replacement.qty)} × ${rupiah(replacement.harga)}</b></div>`
                    : ""}
                ${recipient.catatan_respons
                    ? `<div class="mnc-answer-row"><span>Catatanmu</span><b>${html(
                          recipient.catatan_respons,
                      )}</b></div>`
                    : ""}
                ${recipient.total_pesanan_lama !== null && recipient.total_pesanan_baru !== null
                    ? `<div class="mnc-answer-row"><span>Total pesanan</span><b>${rupiah(
                          recipient.total_pesanan_lama,
                      )} → ${rupiah(recipient.total_pesanan_baru)}</b></div>`
                    : ""}
                ${difference
                    ? `<div class="mnc-answer-row"><span>Selisih</span><b>${html(difference)}</b></div>`
                    : ""}
                ${recipient.jenis_penutupan
                    ? `<div class="mnc-answer-row"><span>Status</span><b>${html(
                          closureLabels[recipient.jenis_penutupan] || "Notifikasi ditutup",
                      )}${recipient.alasan_penutupan ? " — " + html(recipient.alasan_penutupan) : ""}</b></div>`
                    : ""}
                <div class="mnc-answer-row">
                    <span>Dikirim</span>
                    <b>${recipient.responded_at ? html(dateText(recipient.responded_at, true)) : "Belum dikirim"}</b>
                </div>
                <p class="mnc-locked">${html(lockMessage)}</p>
            </div>`;
    }

    function responseFormHtml(recipient, options) {
        const products = Array.isArray(recipient.pilihan_menu)
            ? recipient.pilihan_menu
            : [];
        const id = num(recipient.id);
        const replacementUnavailable = products.length === 0;

        return `
            <form class="mnc-form" data-respond="${id}">
                <p class="mnc-form-label">Pilih tindakan</p>
                <p class="mnc-guidance" data-mnc-guidance>Pilih salah satu tindakan. Menu pengganti baru berlaku setelah disetujui Admin.</p>
                <div class="mnc-options">
                    ${options
                        .map(
                            (option) => `
                        <label class="mnc-option">
                            <input type="radio" name="opsi-${id}" value="${html(option.kode_opsi)}" data-mnc-option="${html(option.kode_opsi)}" ${option.kode_opsi === "menu_pengganti" && replacementUnavailable ? "disabled" : ""} required>
                            <span>
                                <b>${html(option.label)}</b>
                                ${optionHints[option.kode_opsi] ? `<small>${html(optionHints[option.kode_opsi])}</small>` : ""}
                            </span>
                        </label>`,
                        )
                        .join("")}
                </div>
                <div class="mnc-replacement" data-replacement hidden>
                    <label for="mnc-product-${id}">Menu pengganti</label>
                    <select id="mnc-product-${id}" name="produk_pengganti_id">
                        <option value="">Pilih menu pengganti</option>
                        ${products
                            .map(
                                (product) =>
                                    `<option value="${num(product.id)}">${html(product.kode)} — ${html(product.nama)} · ${rupiah(product.harga)} · sisa ${num(product.kuota_efektif)}</option>`,
                            )
                            .join("")}
                    </select>
                    <small class="field-hint">${products.length
                        ? `Jumlah mengikuti ${num(recipient.menu_lama?.qty)} porsi menu lama. Hanya menu dengan sisa kuota cukup dan minimum porsi yang sesuai yang ditampilkan. Harga yang terlihat menjadi snapshot pilihanmu; Admin tetap perlu menyetujui.`
                        : "Belum ada menu pengganti yang tersedia pada tanggal kirim. Pilih tindakan lain atau hubungi Admin."}</small>
                </div>
                <label class="mnc-field" for="mnc-note-${id}">
                    Catatan untuk Admin (opsional)
                    <textarea id="mnc-note-${id}" name="catatan_respons" rows="2" maxlength="1000" placeholder="Contoh: ganti dengan menu yang tidak pedas"></textarea>
                    <small class="field-hint">Maksimal 1000 karakter.</small>
                </label>
                <p class="form-error" data-error role="alert"></p>
                <button type="submit" class="customer-button primary">Kirim pilihan ke Admin</button>
            </form>`;
    }

    function orderHtml(recipient, options) {
        const order = recipient.pesanan || {};
        const oldMenu = recipient.menu_lama || {};
        const replacement = recipient.menu_pengganti;
        const canRespond = recipient.bisa_merespons === true;

        return `
            <article class="mnc-order">
                <div class="mnc-order-head">
                    <b>${html(order.kode || "-")}</b>
                    <span class="status-label ${html(order.status || "")}">${html(orderLabels[order.status] || order.status || "-")}</span>
                    <span class="mnc-spacer"></span>
                    ${responseChip(recipient.status_respons)}
                </div>
                <div class="mnc-meta-grid">
                    <div class="mnc-kv">
                        <small>Menu lama</small>
                        <span>${html(oldMenu.nama || "-")} · ${num(oldMenu.qty)} × ${rupiah(oldMenu.harga)} = <b>${rupiah(oldMenu.total)}</b></span>
                    </div>
                    <div class="mnc-kv">
                        <small>Tanggal kirim</small>
                        <span>${requirementHtml(recipient)}</span>
                    </div>
                    ${replacement
                        ? `<div class="mnc-kv">
                            <small>Menu pengganti dipilih</small>
                            <span>${html(replacement.nama || "-")} · ${num(replacement.qty)} × ${rupiah(replacement.harga)} = <b>${rupiah(replacement.total)}</b></span>
                        </div>`
                        : ""}
                </div>
                ${canRespond
                    ? responseFormHtml(recipient, options)
                    : answeredHtml(recipient)}
            </article>`;
    }

    function detailHtml(data) {
        const product = data.produk || {};
        const recipients = Array.isArray(data.penerima) ? data.penerima : [];
        const options = Array.isArray(data.opsi) ? data.opsi : [];
        const summary = data.ringkasan || {};

        return `
            <div class="mnc-detail-head">
                <p class="eyebrow">PERUBAHAN MENU</p>
                <h2>${html(product.nama || "-")}</h2>
                <p class="mnc-card-date">${html(product.kode || "-")} · ${html(dateText(data.created_at, true))}</p>
            </div>
            <p class="mnc-detail-note">${html(data.catatan_admin || "Admin belum menuliskan catatan.")}</p>
            <div class="mnc-banner">
                Perubahan menu <b>belum berlaku</b> sampai Admin mengonfirmasi pilihanmu.
                Status pesanan dan pembayaran tidak berubah saat ini.
                ${num(summary.belum_merespons) > 0
                    ? `Masih ada ${num(summary.belum_merespons)} pesanan yang menunggu pilihanmu.`
                    : ""}
            </div>
            <div class="mnc-orders">
                ${recipients.length
                    ? recipients.map((recipient) => orderHtml(recipient, options)).join("")
                    : '<p class="mnc-empty">Tidak ada pesanan aktif yang perlu ditindaklanjuti.</p>'}
            </div>
            <p class="field-hint" style="margin-top:14px">
                Pilihan yang sudah dikirim tidak dapat diubah dari halaman ini.
                Hubungi Admin bila perlu mengubahnya.
            </p>`;
    }

    function markCardRead(id) {
        const card = listEl?.querySelector(`[data-notice="${num(id)}"]`);
        if (!card) return;
        card.classList.remove("unread");
        const chip = card.querySelector(".mnc-unread-chip");
        if (chip) {
            chip.className = "mnc-read-chip";
            chip.textContent = "Sudah dibaca";
        }
    }

    async function openDetail(id) {
        if (!dialog || !detailEl) return;
        activeNoticeId = num(id);
        detailEl.innerHTML = '<p class="mnc-empty">Memuat detail…</p>';
        if (!dialog.open) dialog.showModal();

        try {
            const data = await api(
                `${apiUrl}?action=detail&id=${encodeURIComponent(activeNoticeId)}`,
            );
            detailEl.innerHTML = detailHtml(data);

            if (num(data.belum_dibaca) > 0) {
                try {
                    const result = await api(apiUrl, {
                        method: "POST",
                        body: { action: "read", id: activeNoticeId },
                    });
                    syncBadge(result.belum_dibaca);
                    markCardRead(activeNoticeId);
                } catch (error) {
                    /* Menandai dibaca tidak boleh menghalangi pembacaan detail. */
                }
            }
        } catch (error) {
            detailEl.innerHTML = `<p class="mnc-empty">${html(error.message)}</p>`;
        }
    }

    async function refreshDetail() {
        if (!activeNoticeId) return;
        const data = await api(
            `${apiUrl}?action=detail&id=${encodeURIComponent(activeNoticeId)}`,
        );
        detailEl.innerHTML = detailHtml(data);
    }

    listEl?.addEventListener("click", (event) => {
        const card = event.target.closest("[data-notice]");
        if (card) openDetail(card.dataset.notice);
    });

    detailEl?.addEventListener("change", (event) => {
        const input = event.target.closest("input[data-mnc-option]");
        if (!input) return;
        const form = input.closest("[data-respond]");
        const box = form?.querySelector("[data-replacement]");
        const select = box?.querySelector("select");
        const isReplacement = input.value === "menu_pengganti";
        if (box) box.hidden = !isReplacement;
        if (select) {
            select.required = isReplacement;
            if (!isReplacement) select.value = "";
        }
        const guidance = form?.querySelector("[data-mnc-guidance]");
        if (guidance) {
            guidance.textContent = isReplacement
                ? "Pilih menu pengganti yang tersedia. Pilihanmu akan ditinjau Admin sebelum diterapkan."
                : input.value === "hubungi_admin"
                  ? "Admin akan menghubungimu. Tambahkan catatan bila ada kebutuhan khusus."
                  : "Admin akan memberi informasi berikutnya. Catatan tambahan bersifat opsional.";
        }
    });

    detailEl?.addEventListener("submit", async (event) => {
        const form = event.target.closest("[data-respond]");
        if (!form) return;
        event.preventDefault();

        const id = num(form.dataset.respond);
        const optionInput = form.querySelector("input[data-mnc-option]:checked");
        const errorEl = form.querySelector("[data-error]");
        if (errorEl) errorEl.textContent = "";

        if (!optionInput) {
            if (errorEl) errorEl.textContent = "Pilih tindakan terlebih dahulu.";
            return;
        }

        const body = {
            action: "respond",
            id,
            opsi: optionInput.value,
            catatan_respons:
                form.querySelector('textarea[name="catatan_respons"]')?.value || "",
        };

        if (optionInput.value === "menu_pengganti") {
            const select = form.querySelector('select[name="produk_pengganti_id"]');
            if (!select || !select.value) {
                if (errorEl) {
                    errorEl.textContent = "Pilih menu pengganti terlebih dahulu.";
                }
                select?.focus();
                return;
            }
            body.produk_pengganti_id = num(select.value);
        }

        const button = form.querySelector('button[type="submit"]');
        if (button) {
            button.disabled = true;
            button.textContent = "Mengirim…";
        }

        try {
            const result = await api(apiUrl, { method: "POST", body });
            toast("Pilihan berhasil dikirim ke Admin");
            syncBadge(result.belum_dibaca);
            await refreshDetail();
            loadList(true).catch(() => {});
        } catch (error) {
            if (errorEl) errorEl.textContent = error.message;
            if (button) {
                button.disabled = false;
                button.textContent = "Kirim pilihan";
            }
        }
    });

    refreshBtn?.addEventListener("click", () => loadList(true));
    moreBtn?.addEventListener("click", () => loadList(false));

    if (dialog) {
        dialog.querySelectorAll("[data-mnc-close]").forEach((button) => {
            button.addEventListener("click", () => dialog.close());
        });
        dialog.addEventListener("click", (event) => {
            if (event.target === dialog) dialog.close();
        });
    }

    const requestedNoticeId = Number(new URLSearchParams(location.search).get("notice_id"));
    loadList(true).then(() => {
        if (Number.isInteger(requestedNoticeId) && requestedNoticeId > 0) {
            openDetail(requestedNoticeId);
        }
    });
})();
