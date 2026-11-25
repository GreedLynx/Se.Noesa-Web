(() => {
    const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content || '';
    const post = (body, form = false) => api('api/late-payments.php', {
        method: 'POST',
        headers: { 'X-CSRF-Token': csrf() },
        body: form ? body : { ...body },
    });
    const esc = value => String(value ?? '').replace(/[&<>"']/g, c => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
    })[c]);
    const fmt = value => value ? new Date(value).toLocaleString('id-ID', {
        day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit', timeZone: 'Asia/Jakarta'
    }) : '-';
    const money = value => 'Rp ' + Number(value || 0).toLocaleString('id-ID');
    const reconciliationStatus = value => {
        const map = { menunggu_verifikasi: ['pending', 'Menunggu verifikasi'], terverifikasi: ['verified', 'Terverifikasi'], tidak_valid: ['invalid', 'Tidak valid'] };
        const [type, label] = map[value] || ['pending', String(value || '-')];
        return `<span class="late-status ${type}">${label}</span>`;
    };

    async function loadEligible() {
        const select = $('#late-order');
        const query = $('#late-order-code')?.value.trim() || '';
        const message = $('#late-order-search-result');
        const button = $('#late-order-search-button');
        if (!select || query.length < 3) {
            if (message) message.textContent = 'Masukkan minimal 3 karakter kode pesanan.';
            return;
        }
        if (button) button.disabled = true;
        if (message) message.textContent = 'Mencari pesanan...';
        select.disabled = true;
        select.innerHTML = '<option value="">Memuat hasil pencarian...</option>';
        try {
            const d = await api('api/late-payments.php?eligible=1&q=' + encodeURIComponent(query));
            if (d.feature_available === false) {
                select.innerHTML = '<option value="">Fitur belum siap: periksa koneksi database dan migrasi 028</option>';
                if (message) message.textContent = 'Fitur rekonsiliasi belum tersedia.';
            } else if ((d.items || []).length) {
                select.innerHTML = '<option value="">Pilih pesanan yang cocok</option>' + d.items.map(o => {
                    const label = `${o.kode} \u2014 ${o.nama_pemesan}`;
                    const detail = `${o.kode} \u2014 ${o.nama_pemesan} \u00b7 ${money(o.total)} \u00b7 ${fmt(o.created_at)} \u00b7 ${o.status}`;
                    return `<option value="${Number(o.id)}" title="${esc(detail)}">${esc(label)}</option>`;
                }).join('');
                select.disabled = false;
                if (d.items.length === 1) select.value = String(d.items[0].id);
                if (message) message.textContent = `${d.items.length} pesanan cocok. Pastikan kode dan nama pelanggan sesuai.`;
            } else {
                select.innerHTML = '<option value="">Tidak ada pesanan yang cocok</option>';
                if (message) message.textContent = 'Kode tidak ditemukan, atau pesanan sudah memiliki catatan rekonsiliasi.';
            }
        } catch (e) {
            select.innerHTML = '<option value="">Pencarian gagal</option>';
            if (message) message.textContent = e.message;
        } finally {
            if (button) button.disabled = false;
        }
    }

    async function loadList() {
        const box = $('#late-list');
        if (!box) return;
        try {
            const d = await api('api/late-payments.php');
            const items = d.items || [];
            box.innerHTML = d.feature_available === false
                ? '<div class="late-empty"><div><strong>Fitur rekonsiliasi belum tersedia</strong><small>Periksa koneksi database dan pastikan migrasi 028 sudah diterapkan.</small></div></div>'
                : items.length
                ? '<table><thead><tr><th>ID</th><th>Pesanan</th><th>Pelanggan</th><th>Kontak</th><th>Tanggal pesanan</th><th>Kirim</th><th>Total</th><th>Status pesanan</th><th>Status rekonsiliasi</th><th>Waktu transaksi</th><th>Dicatat</th><th></th></tr></thead><tbody>'
                    + items.map(r => `<tr>
                        <td>${Number(r.id)}</td><td>${esc(r.kode)}</td>
                        <td>${esc(r.nama_pemesan)}</td><td>${esc(r.telepon_penerima || '-')}</td>
                        <td>${fmt(r.tanggal_pesanan)}</td><td>${fmt(r.tgl_kirim)}</td>
                        <td>${money(r.total)}</td><td>${esc(r.status_pesanan)}</td>
                        <td>${reconciliationStatus(r.status_rekonsiliasi)}</td><td>${fmt(r.waktu_transaksi)}</td>
                        <td>${fmt(r.dicatat_at)}</td>
                        <td><button class="button small secondary" data-late-detail="${Number(r.id)}">Detail</button></td>
                    </tr>`).join('') + '</tbody></table>'
                : '<div class="late-empty"><div><span class="ui-icon icon-kosong" aria-hidden="true"></span><strong>Belum ada rekonsiliasi</strong><small>Catatan pembayaran terlambat akan muncul di sini.</small></div></div>';
            $$('[data-late-detail]').forEach(b => b.onclick = () => openDetail(Number(b.dataset.lateDetail)));
        } catch (e) { box.innerHTML = `<p class="form-error">${esc(e.message)}</p>`; }
    }

    async function openDetail(id) {
        const panel = $('#late-detail-panel'), box = $('#late-detail'), logBox = $('#late-log');
        if (!panel || !box) return;
        try {
            const d = await api('api/late-payments.php?id=' + Number(id));
            const r = d.item || {};
            const isPending = r.status_rekonsiliasi === 'menunggu_verifikasi';
            const isVerified = r.status_rekonsiliasi === 'terverifikasi';
            box.innerHTML = `<div class="late-grid">
                <div><b>ID rekonsiliasi</b><span>${Number(r.id)}</span></div>
                <div><b>ID pesanan</b><span>${Number(r.pesanan_id)} · ${esc(r.kode_pesanan)}</span></div>
                <div><b>Pelanggan</b><span>${esc(r.nama_pemesan)} · ${esc(r.telepon_penerima || '-')}</span></div>
                <div><b>Status pesanan lama</b><span>${esc(r.status_pesanan)}</span></div>
                <div><b>Status rekonsiliasi</b><span>${esc(r.status_rekonsiliasi)}</span></div>
                <div><b>Nominal dilaporkan</b><span>${money(r.nominal_dilaporkan)}</span></div>
                <div><b>Waktu transaksi</b><span>${fmt(r.waktu_transaksi)}</span></div>
                <div><b>Referensi</b><span>${esc(r.referensi_transaksi || '-')}</span></div>
                <div><b>Dicatat oleh</b><span>${esc(r.admin_pencatat || '-')} · ${fmt(r.dicatat_at)}</span></div>
                <div><b>Diverifikasi oleh</b><span>${esc(r.admin_verifikator || '-')} · ${fmt(r.diverifikasi_at)}</span></div>
                <div><b>Catatan Admin</b><span>${esc(r.catatan_admin || '-')}</span></div>
                <div><b>Hasil verifikasi</b><span>${esc(r.hasil_catatan || '-')}</span></div>
            </div>
            <p><a class="button small secondary" href="${esc(r.bukti_url || '')}" target="_blank" rel="noopener">Lihat bukti</a></p>
            ${isPending ? `<div class="late-actions">
                <button class="button primary" data-late-verify="terverifikasi">Terverifikasi</button>
                <button class="button danger-outline" data-late-verify="tidak_valid">Tidak valid</button>
            </div><form id="late-verify-note"><label>Catatan / alasan<textarea name="catatan" rows="2" maxlength="1000"></textarea></label></form>` : ''}
            ${isVerified && !r.tindak_lanjut ? `<form id="late-follow-form" class="late-form">
                <label>Tindak lanjut<select name="tindak_lanjut" id="late-follow-type"><option value="lanjutkan_pesanan">Lanjutkan pesanan lama menjadi diproses</option><option value="pesanan_baru">Pesanan baru (checkout normal)</option><option value="refund">Refund</option></select></label>
                <p class="muted">Konfirmasi dulu melalui WhatsApp. Untuk kuota penuh, tawarkan tanggal atau menu lain, atau minta pelanggan membuat pesanan baru lewat WhatsApp.</p>
                <label class="late-follow-new" hidden>ID pesanan baru<input type="number" name="tindak_lanjut_order_id" min="1"></label>
                <label class="late-follow-refund" hidden>Nominal refund<input type="number" name="refund_nominal" min="0" step="1"></label>
                <label class="late-follow-refund" hidden>Tanggal refund<input type="datetime-local" name="refund_tanggal"></label>
                <label class="late-follow-refund" hidden>Referensi refund<input type="text" name="refund_referensi" maxlength="100"></label>
                <label>Catatan<textarea name="tindak_lanjut_catatan" rows="2" maxlength="1000" placeholder="Tulis hasil konfirmasi WhatsApp atau kesepakatan tindak lanjut"></textarea></label>
                <label><input type="checkbox" name="konfirmasi_pelanggan" value="1" required> Pelanggan sudah mengonfirmasi tindak lanjut melalui WhatsApp</label>
                <button class="button primary" type="submit">Simpan tindak lanjut</button>
            </form>` : ''}
            ${r.tindak_lanjut ? `<p><b>Tindak lanjut:</b> ${esc(r.tindak_lanjut)} · ${esc(r.tindak_lanjut_catatan || '')}</p>` : ''}`;
            logBox.innerHTML = (d.logs || []).length
                ? '<h3>Riwayat tindakan</h3><table><thead><tr><th>Waktu</th><th>Aksi</th><th>Dari</th><th>Ke</th><th>Alasan</th><th>Oleh</th></tr></thead><tbody>'
                    + d.logs.map(l => `<tr><td>${fmt(l.created_at)}</td><td>${esc(l.aksi)}</td><td>${esc(l.status_sebelum || '-')}</td><td>${esc(l.status_sesudah || '-')}</td><td>${esc(l.alasan || '-')}</td><td>${esc(l.actor || '-')}</td></tr>`).join('') + '</tbody></table>'
                : '';
            panel.hidden = false;
            $$('[data-late-verify]').forEach(b => b.onclick = async () => {
                const status = b.dataset.lateVerify;
                const note = $('#late-verify-note textarea')?.value || '';
                if (status === 'tidak_valid' && !note.trim()) { toast('Alasan penolakan wajib diisi'); return; }
                const ok = await confirmDialog(status === 'terverifikasi' ? 'Verifikasi pembayaran' : 'Tolak pembayaran',
                    'Pembayaran akan diverifikasi. Status pesanan tetap sampai tindak lanjut dicatat.', 'Ya, lanjutkan');
                if (!ok) return;
                try {
                    await post({ action: 'verify', id, status, hasil_catatan: note });
                    toast('Hasil verifikasi tersimpan');
                    await openDetail(id); await loadList();
                } catch (e) { toast(e.message); }
            });
            const follow = $('#late-follow-form');
            if (follow) {
                const typeSelect = $('#late-follow-type');
                const updateFollowFields = () => {
                    const type = typeSelect.value;
                    $$('.late-follow-new', follow).forEach(field => {
                        field.hidden = type !== 'pesanan_baru';
                        const input = $('input', field);
                        input.required = type === 'pesanan_baru';
                        if (type !== 'pesanan_baru') input.value = '';
                    });
                    $$('.late-follow-refund', follow).forEach(field => {
                        field.hidden = type !== 'refund';
                        const input = $('input', field);
                        input.required = type === 'refund' && input.name !== 'refund_referensi';
                        if (type !== 'refund') input.value = '';
                    });
                };
                typeSelect.onchange = updateFollowFields;
                updateFollowFields();
                follow.onsubmit = async e => {
                e.preventDefault();
                const data = Object.fromEntries(new FormData(follow));
                data.action = 'follow_up'; data.id = id;
                try {
                    await post(data);
                    toast('Tindak lanjut tersimpan');
                    await openDetail(id); await loadList();
                } catch (err) { toast(err.message); }
            };
            }
        } catch (e) { box.innerHTML = `<p class="form-error">${esc(e.message)}</p>`; }
    }

    function init() {
        if (!$('#late-record-form')) return;
        loadList();
        $('#late-order-search-button').onclick = loadEligible;
        $('#late-order-code').onkeydown = e => {
            if (e.key === 'Enter') { e.preventDefault(); loadEligible(); }
        };
        $('#late-order-code').oninput = () => {
            const select = $('#late-order');
            select.disabled = true;
            select.innerHTML = '<option value="">Cari ulang setelah mengubah kode</option>';
            $('#late-order-search-result').textContent = '';
        };
        $('#late-refresh').onclick = () => { loadList(); if ($('#late-order-code').value.trim().length >= 3) loadEligible(); };
        $('#late-close').onclick = () => { $('#late-detail-panel').hidden = true; };
        $('#late-record-form').onsubmit = async e => {
            e.preventDefault();
            const form = e.currentTarget;
            const data = new FormData(form);
            const error = $('#late-record-error');
            error.textContent = '';
            if (!$('#late-order').value) {
                error.textContent = 'Cari dan pilih kode pesanan yang cocok terlebih dahulu.';
                return;
            }
            data.append('action', 'record');
            try {
                await post(data, true);
                form.reset();
                toast('Bukti terlambat tercatat');
                $('#late-order-code').value = '';
                $('#late-order').disabled = true;
                $('#late-order').innerHTML = '<option value="">Cari kode pesanan terlebih dahulu</option>';
                $('#late-order-search-result').textContent = '';
                await loadList();
            } catch (err) { error.textContent = err.message; }
        };
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
    else init();
})();
