/* Halaman detail pesanan + pembayaran (data asli dari api/orders.php). */
(function () {
    if (document.body.dataset.page !== 'detail') return;

    const root = $('#od-root');
    if (!root) return;

    const stepperLabels = ['Pesanan', 'Bayar', 'Verifikasi', 'Diproses', 'Selesai'];
    const statusStep = {
        menunggu_pembayaran: 1,
        menunggu_validasi: 3,
        diproses: 4,
        selesai: 5
    };

    let countdownTimer = null;
    let currentOrder = null;

    const contactToggle = $('#od-contact-toggle');
    if (contactToggle) {
        contactToggle.addEventListener('click', () => {
            const body = $('#od-contact-body');
            const open = contactToggle.getAttribute('aria-expanded') !== 'true';
            contactToggle.setAttribute('aria-expanded', String(open));
            if (body) body.hidden = !open;
            const icon = $('#od-contact-icon');
            if (icon) icon.textContent = open ? 'expand_less' : 'expand_more';
        });
    }

    function set(id, text) {
        const el = $('#' + id);
        if (el) el.textContent = text;
    }
    function show(id, on) {
        const el = $('#' + id);
        if (el) el.hidden = !on;
    }
    function setImg(id, src, alt) {
        const el = $('#' + id);
        if (!el) return;
        el.src = src;
        el.alt = alt || '';
        el.onerror = () => { el.onerror = null; el.src = '../assets/img/produk/NK-01.png'; };
    }

    let previewUrl = null;
    function resetPreview() {
        if (previewUrl) { URL.revokeObjectURL(previewUrl); previewUrl = null; }
        const box = $('#od-proof-preview'), img = $('#od-proof-preview-img');
        if (box) box.hidden = true;
        if (img) img.removeAttribute('src');
    }
    function previewFile(fileInput) {
        const file = fileInput && fileInput.files && fileInput.files[0];
        const err = $('#od-proof-error');
        if (err) err.textContent = '';
        if (!file) { resetPreview(); return; }
        if (!/^image\/(jpeg|png|webp)$/.test(file.type)) {
            if (err) err.textContent = 'Gunakan gambar JPG, PNG, atau WebP.';
            resetPreview();
            return;
        }
        if (file.size > 2 * 1024 * 1024) {
            if (err) err.textContent = 'Ukuran maksimal 2MB.';
            resetPreview();
            return;
        }
        if (previewUrl) URL.revokeObjectURL(previewUrl);
        previewUrl = URL.createObjectURL(file);
        const img = $('#od-proof-preview-img'), box = $('#od-proof-preview');
        if (img) { img.src = previewUrl; img.alt = 'Pratinjau bukti pembayaran'; }
        if (box) box.hidden = false;
    }

    function renderStepper(status) {
        const wrap = $('#od-stepper');
        if (!wrap) return;
        const complete = statusStep[status] || 1;
        wrap.innerHTML = stepperLabels.map((s, i) => {
            const done = i < complete;
            return '<div class="od-step' + (done ? ' is-done' : '') + '">'
                + '<span class="od-step-bar"></span>'
                + '<span class="od-step-label">' + s + '</span></div>';
        }).join('');
    }

    function startCountdown(expiresAt) {
        if (countdownTimer) { clearInterval(countdownTimer); countdownTimer = null; }
        const target = new Date(expiresAt).getTime();
        if (!target || isNaN(target) || target <= Date.now()) {
            show('od-countdown-wrap', false);
            return;
        }
        show('od-countdown-wrap', true);
        const tick = () => {
            const remain = Math.max(0, Math.floor((target - Date.now()) / 1000));
            const m = Math.floor(remain / 60);
            const s = remain % 60;
            set('od-countdown', m + ' Menit : ' + (s < 10 ? '0' + s : s) + ' Detik');
            if (remain <= 0) {
                clearInterval(countdownTimer);
                countdownTimer = null;
                load();
            }
        };
        tick();
        countdownTimer = setInterval(tick, 1000);
    }

    function render(d) {
        currentOrder = d;
        set('od-code', d.kode);
        const statusEl = $('#od-status');
        if (statusEl) {
            statusEl.textContent = statusLabels[d.status] || d.status;
            statusEl.className = 'od-badge od-badge-' + d.status;
        }
        renderStepper(d.status);

        if (d.can_upload_proof && d.payment_expires_at) startCountdown(d.payment_expires_at);
        else show('od-countdown-wrap', false);

        set('od-recipient', d.nama_pemesan || '-');
        set('od-recipient-phone', d.telepon_penerima ? '· ' + d.telepon_penerima : '');
        set('od-address', d.alamat_pengiriman || 'Alamat belum tercatat');
        set('od-date', dateLabel(d.tgl_kirim));
        show('od-address-card', true);

        const items = Array.isArray(d.items) ? d.items : [];
        const totalPorsi = items.reduce((n, i) => n + Number(i.qty || 0), 0);
        set('od-menu-count', items.length + ' Menu (' + totalPorsi + ' Porsi)');
        $('#od-items').innerHTML = items.map(i =>
            '<div class="od-item">'
                + (i.gambar ? '<img class="od-item-img" src="' + esc(imagePath(i.gambar)) + '" alt="' + esc(i.nama) + '">' : '')
                + '<div class="od-item-info">'
                    + '<h3 class="od-item-name">' + esc(i.nama) + '</h3>'
                    + '<div class="od-item-meta"><span>' + Number(i.qty) + ' porsi × ' + money(i.harga) + '</span>'
                    + '<b>' + money(i.subtotal) + '</b></div>'
                + '</div>'
            + '</div>'
        ).join('');
        const note = (d.keterangan || '').trim();
        show('od-order-note', !!note);
        if (note) set('od-order-note-text', note);
        show('od-menu-card', true);

        set('od-subtotal-label', 'Subtotal Menu' + (totalPorsi ? ' (' + totalPorsi + ' porsi)' : ''));
        set('od-subtotal', money(d.total));
        set('od-total', money(d.total));
        show('od-billing-card', true);

        renderReschedule(d);
        renderDelivery(d);
        renderNotices(d);

        const policyLimit = $('#od-policy-limit');
        if (policyLimit) {
            policyLimit.textContent = d.cancel_before
                ? 'Batas akhir pembatalan: ' + dateLabel(d.cancel_before) + ', akhir hari WIB.'
                : '';
        }
        show('od-policy-card', true);

        const canCancel = d.can_cancel === true && ['menunggu_pembayaran', 'menunggu_validasi', 'diproses'].includes(d.status) && !d.can_respond_reschedule;
        show('od-cancel', canCancel);

        show('od-status-card', true);
    }

    function renderReschedule(d) {
        const wrap = $('#od-reschedule');
        if (!wrap) return;
        const offer = d.reschedule_offer;
        if (!offer) { wrap.innerHTML = ''; return; }
        const pending = offer.status_tawaran === 'menunggu' && d.can_respond_reschedule;
        wrap.innerHTML = '<section class="od-card od-offer" role="status">'
            + '<div class="od-title"><span class="material-symbols-outlined text-primary text-[20px]">event_repeat</span>'
            + '<h2>' + (pending ? 'Tawaran jadwal pengganti' : 'Riwayat tawaran jadwal') + '</h2></div>'
            + '<p class="od-muted od-small"><b>Alasan:</b> ' + esc(offer.alasan) + '</p>'
            + '<div class="od-offer-dates">'
                + '<span>Tanggal sebelumnya<b>' + dateLabel(offer.tanggal_asal) + '</b></span>'
                + '<span>Tanggal usulan<b>' + dateLabel(offer.tanggal_usulan) + '</b></span>'
            + '</div>'
            + (pending
                ? '<p class="od-muted od-small">Tanggapi sebelum ' + dateLabel(offer.berlaku_sampai, true) + ' WIB.</p>'
                    + '<div class="od-offer-actions"><button type="button" class="od-btn od-btn-primary" id="od-accept-reschedule">Terima jadwal</button>'
                    + '<button type="button" class="od-btn od-btn-ghost" id="od-reject-reschedule">Tolak jadwal</button></div>'
                    + '<p id="od-reschedule-error" class="od-error" role="alert"></p>'
                : '<p class="od-muted od-small">Status tawaran: ' + esc(offer.status_tawaran) + '</p>')
            + '</section>';
        if (!pending) return;
        const accept = $('#od-accept-reschedule');
        const reject = $('#od-reject-reschedule');
        const respond = async (decision) => {
            if (!confirm(decision === 'accept'
                ? 'Terima jadwal pengganti ini? Ketersediaan akan diperiksa ulang.'
                : 'Tolak jadwal ini dan batalkan pesanan?')) return;
            accept.disabled = reject.disabled = true;
            try {
                await api('api/orders.php', {
                    method: 'POST',
                    body: {action: 'respond_reschedule', id: d.id, decision, offer_version: offer.dibuat_at}
                });
                toast(decision === 'accept' ? 'Jadwal pengiriman diperbarui' : 'Pesanan dibatalkan');
                load();
            } catch (err) {
                const e = $('#od-reschedule-error');
                if (e) e.textContent = err.message;
                accept.disabled = reject.disabled = false;
            }
        };
        if (accept) accept.onclick = () => respond('accept');
        if (reject) reject.onclick = () => respond('reject');
    }

    function renderDelivery(d) {
        const wrap = $('#od-delivery');
        if (!wrap) return;
        const photos = Array.isArray(d.foto_kiriman_list) ? d.foto_kiriman_list : [];
        if (!photos.length) { wrap.innerHTML = ''; return; }
        wrap.innerHTML = '<section class="od-card"><div class="od-title"><span class="material-symbols-outlined text-primary text-[20px]">photo_camera</span>'
            + '<h2>Foto Siap Kirim</h2></div><div class="od-photos">'
            + photos.map(p => {
                const url = /^https?:\/\//.test(p) || p.startsWith('/') ? p : '../' + p.replace(/^\.\//, '');
                return '<a class="od-photo" href="' + esc(url) + '" target="_blank" rel="noopener"><img src="' + esc(url) + '" alt="Foto pesanan"></a>';
            }).join('') + '</div></section>';
    }

    function renderNotices(d) {
        const wrap = $('#od-notices');
        if (!wrap) return;
        const notices = Array.isArray(d.menu_notifications) ? d.menu_notifications : [];
        if (!notices.length) { wrap.innerHTML = ''; return; }
        const labels = {
            menunggu_respons: 'Menunggu responsmu',
            menunggu_konfirmasi: 'Menunggu konfirmasi Admin',
            menunggu_tindak_lanjut: 'Menunggu tindak lanjut Admin',
            dikonfirmasi: 'Perubahan dikonfirmasi Admin',
            selesai: 'Tindak lanjut selesai'
        };
        wrap.innerHTML = '<section class="od-card"><div class="od-title"><span class="material-symbols-outlined text-primary text-[20px]">notifications</span>'
            + '<h2>Notifikasi perubahan menu</h2></div>'
            + notices.map(n => '<div class="od-item od-notice"><div class="od-item-info">'
                + '<h3 class="od-item-name">' + esc(n.nama_produk_snapshot || n.nama_produk_lama_snapshot || 'Menu') + '</h3>'
                + '<small class="od-muted od-small">' + esc(labels[n.status_respons] || n.status_respons || '')
                + (n.catatan_admin ? ' · ' + esc(n.catatan_admin) : '') + '</small>'
                + '</div><a class="od-btn od-btn-ghost" href="menu-notifications.php?notice_id=' + Number(n.id) + '">Tindak lanjuti</a></div>').join('')
            + '</section>';
    }

    async function renderQris() {
        let data;
        try {
            data = await api('api/payment-methods.php');
        } catch (e) {
            data = {items: []};
        }
        const items = (data && data.items) || [];
        if (!items.length) { show('od-qris-card', false); return; }
        const method = items[0];
        show('od-qris-card', true);
        show('od-qris-box', true);
        setImg('od-qris-img', imagePath(method.gambar), method.nama || 'QRIS');
        const saveBtn = $('#od-save-qr');
        if (saveBtn) saveBtn.onclick = () => {
            const a = document.createElement('a');
            a.href = $('#od-qris-img').src;
            a.download = 'qris-se.noesa.png';
            document.body.appendChild(a);
            a.click();
            a.remove();
        };
    }

    function renderProof(d) {
        const hasProof = !!d.bukti_bayar;
        const canUpload = d.can_upload_proof === true;
        show('od-proof-card', canUpload || hasProof);
        if (!canUpload && !hasProof) return;
        const form = $('#od-proof-form');
        const existing = $('#od-proof-existing');
        const payer = $('#od-payer-name');
        const badge = $('#od-proof-badge');
        if (badge) badge.textContent = canUpload ? 'Wajib' : 'Terkunci';
        if (payer) payer.value = d.nama_pembayar || d.nama_pemesan || '';
        const idInput = $('#od-proof-id');
        if (idInput) idInput.value = d.id;
        resetPreview();
        if (hasProof) {
            setImg('od-proof-img', '../' + String(d.bukti_bayar).replace(/^\/+/, ''), 'Bukti pembayaran');
            if (existing) existing.hidden = false;
            // Form tersembunyi; hanya terbuka lewat tombol "Ganti Bukti" selama jendela pembayaran masih aktif.
            if (form) form.hidden = true;
        } else {
            if (form) form.hidden = false;
            if (existing) existing.hidden = true;
        }
        const replace = $('#od-proof-replace');
        if (replace) {
            replace.hidden = !canUpload;
            replace.onclick = () => {
                if (existing) existing.hidden = true;
                if (form) form.hidden = false;
            };
        }
        const submit = $('#od-proof-submit');
        if (submit) submit.onclick = (e) => { e.preventDefault(); sendProof(); };
        if (form) form.onsubmit = (e) => { e.preventDefault(); sendProof(); };
        const fileInput = $('#od-proof-file');
        if (fileInput) fileInput.onchange = () => previewFile(fileInput);
        const clearBtn = $('#od-proof-preview-clear');
        if (clearBtn) clearBtn.onclick = () => {
            if (fileInput) fileInput.value = '';
            resetPreview()
        };
        if (!hasProof && d.status === 'menunggu_pembayaran' && !canUpload) {
            const exp = $('#od-proof-expired');
            if (exp) {
                exp.hidden = false;
                exp.textContent = 'Tenggat unggah bukti telah berakhir. Hubungi Admin dengan kode ' + d.kode + ' bila sudah membayar.';
            }
        }
    }

    async function sendProof() {
        const form = $('#od-proof-form');
        if (!form) return;
        const file = $('#od-proof-file').files[0];
        const err = $('#od-proof-error');
        if (err) err.textContent = '';
        if (!file) { if (err) err.textContent = 'Pilih berkas bukti pembayaran.'; return; }
        if (!/^image\/(jpeg|png|webp)$/.test(file.type)) { if (err) err.textContent = 'Gunakan gambar JPG, PNG, atau WebP.'; return; }
        if (file.size > 2 * 1024 * 1024) { if (err) err.textContent = 'Ukuran maksimal 2MB.'; return; }
        const payer = $('#od-payer-name');
        const payerName = payer ? payer.value.trim() : '';
        if (!payerName) { if (err) err.textContent = 'Nama pembayar wajib diisi.'; if (payer) payer.focus(); return; }
        const orderId = (currentOrder && currentOrder.id) || $('#od-proof-id')?.value || '';
        if (!orderId) { if (err) err.textContent = 'ID pesanan tidak ditemukan, muat ulang halaman.'; return; }
        const payload = new FormData();
        payload.append('action', 'upload_proof');
        payload.append('id', String(orderId));
        payload.append('nama_pembayar', payerName);
        payload.append('proof', file);
        const btn = $('#od-proof-submit');
        if (btn) btn.disabled = true;
        try {
            await api('api/orders.php', {method: 'POST', body: payload});
            toast('Bukti pembayaran berhasil dikirim');
            load();
        } catch (e) {
            if (err) err.textContent = e.message;
        } finally {
            if (btn) btn.disabled = false;
        }
    }

    function openCancel() {
        if (!currentOrder) return;
        set('od-cancel-desc', 'Slot katering pesanan ' + currentOrder.kode + ' untuk ' + dateLabel(currentOrder.tgl_kirim) + ' akan dibatalkan.');
        show('od-cancel-modal', true);
    }
    function closeCancel() { show('od-cancel-modal', false); }

    async function confirmCancel() {
        if (!currentOrder) return;
        const btn = $('#od-cancel-confirm');
        if (btn) btn.disabled = true;
        try {
            await api('api/orders.php', {method: 'POST', body: {action: 'cancel_order', id: currentOrder.id}});
            toast('Pesanan berhasil dibatalkan');
            closeCancel();
            load();
        } catch (e) {
            toast(e.message);
        } finally {
            if (btn) btn.disabled = false;
        }
    }

    function bindActions() {
        const copy = $('#od-copy');
        if (copy) copy.onclick = () => {
            const code = (currentOrder && currentOrder.kode) || '';
            navigator.clipboard?.writeText(code).catch(() => {});
            toast('Kode pesanan disalin');
        };
        const check = $('#od-check');
        if (check) check.onclick = async () => {
            check.disabled = true;
            try {
                await load();
                toast('Status pembayaran diperbarui');
            } catch (e) {
                toast(e.message);
            } finally {
                check.disabled = false;
            }
        };
        const cancel = $('#od-cancel');
        if (cancel) cancel.onclick = openCancel;
        const dismiss = $('#od-cancel-dismiss');
        if (dismiss) dismiss.onclick = closeCancel;
        const confirm = $('#od-cancel-confirm');
        if (confirm) confirm.onclick = confirmCancel;
        const modal = $('#od-cancel-modal');
        if (modal) modal.onclick = (e) => { if (e.target === modal) closeCancel(); };
        const policyToggle = $('#od-policy-toggle');
        if (policyToggle) policyToggle.onclick = () => {
            const body = $('#od-policy-body');
            const icon = $('#od-policy-icon');
            if (!body) return;
            body.hidden = !body.hidden;
            if (icon) icon.style.transform = body.hidden ? 'rotate(0deg)' : 'rotate(180deg)';
        };
    }

    async function load() {
        const id = Number(new URLSearchParams(location.search).get('order'));
        if (!id) {
            $('#od-loading').textContent = 'Parameter pesanan tidak valid.';
            return;
        }
        try {
            const d = await api('api/orders.php?id=' + id);
            render(d);
            renderProof(d);
            await renderQris();
            $('#od-loading').hidden = true;
            root.hidden = false;
        } catch (e) {
            $('#od-loading').textContent = 'Gagal memuat pesanan: ' + (e && e.message ? e.message : 'kesalahan');
        }
    }

    bindActions();
    load();
})();
