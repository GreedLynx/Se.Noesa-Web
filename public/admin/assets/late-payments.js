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

    async function loadEligible() {
        const select = $('#late-order');
        if (!select) return;
        try {
            const d = await api('api/late-payments.php?eligible=1');
            select.innerHTML = (d.items || []).length
                ? d.items.map(o => {
                    const label = `${o.kode} — ${o.nama_pemesan}`;
                    const detail = `${o.kode} — ${o.nama_pemesan} · ${money(o.total)} · ${fmt(o.created_at)}`;
                    return `<option value="${Number(o.id)}" title="${esc(detail)}">${esc(label)}</option>`;
                }).join('')
                : '<option value="">Tidak ada pesanan dibatalkan yang belum dicatat</option>';
        } catch (e) { toast(e.message); }
    }

    async function loadList() {
        const box = $('#late-list');
        if (!box) return;
        try {
            const d = await api('api/late-payments.php');
            const items = d.items || [];
            box.innerHTML = items.length
                ? '<table><thead><tr><th>ID</th><th>Pesanan</th><th>Pelanggan</th><th>Kontak</th><th>Tanggal pesanan</th><th>Kirim</th><th>Total</th><th>Status pesanan</th><th>Status rekonsiliasi</th><th>Waktu transaksi</th><th>Dicatat</th><th></th></tr></thead><tbody>'
                    + items.map(r => `<tr>
                        <td>${Number(r.id)}</td><td>${esc(r.kode)}</td>
                        <td>${esc(r.nama_pemesan)}</td><td>${esc(r.telepon_penerima || '-')}</td>
                        <td>${fmt(r.tanggal_pesanan)}</td><td>${fmt(r.tgl_kirim)}</td>
                        <td>${money(r.total)}</td><td>${esc(r.status_pesanan)}</td>
                        <td>${esc(r.status_rekonsiliasi)}</td><td>${fmt(r.waktu_transaksi)}</td>
                        <td>${fmt(r.dicatat_at)}</td>
                        <td><button class="button small secondary" data-late-detail="${Number(r.id)}">Detail</button></td>
                    </tr>`).join('') + '</tbody></table>'
                : '<p class="muted">Belum ada rekonsiliasi pembayaran terlambat.</p>';
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
                <label>Tindak lanjut<select name="tindak_lanjut"><option value="pesanan_baru">Pesanan baru (checkout normal)</option><option value="refund">Refund</option></select></label>
                <label>ID pesanan baru (jika pesanan baru)<input type="number" name="tindak_lanjut_order_id" min="1"></label>
                <label>Nominal refund<input type="number" name="refund_nominal" min="0" step="1"></label>
                <label>Tanggal refund<input type="datetime-local" name="refund_tanggal"></label>
                <label>Referensi refund<input type="text" name="refund_referensi" maxlength="100"></label>
                <label>Catatan<textarea name="tindak_lanjut_catatan" rows="2" maxlength="1000"></textarea></label>
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
                    'Catatan rekonsiliasi akan diperbarui. Status pesanan lama tidak akan diubah.', 'Ya, lanjutkan');
                if (!ok) return;
                try {
                    await post({ action: 'verify', id, status, hasil_catatan: note });
                    toast('Hasil verifikasi tersimpan');
                    await openDetail(id); await loadList();
                } catch (e) { toast(e.message); }
            });
            const follow = $('#late-follow-form');
            if (follow) follow.onsubmit = async e => {
                e.preventDefault();
                const data = Object.fromEntries(new FormData(follow));
                data.action = 'follow_up'; data.id = id;
                try {
                    await post(data);
                    toast('Tindak lanjut tersimpan');
                    await openDetail(id); await loadList();
                } catch (err) { toast(err.message); }
            };
        } catch (e) { box.innerHTML = `<p class="form-error">${esc(e.message)}</p>`; }
    }

    function init() {
        if (!$('#late-record-form')) return;
        loadEligible();
        loadList();
        $('#late-refresh').onclick = () => { loadList(); loadEligible(); };
        $('#late-close').onclick = () => { $('#late-detail-panel').hidden = true; };
        $('#late-record-form').onsubmit = async e => {
            e.preventDefault();
            const form = e.currentTarget;
            const data = new FormData(form);
            data.append('action', 'record');
            const error = $('#late-record-error');
            error.textContent = '';
            try {
                await post(data, true);
                form.reset();
                toast('Bukti terlambat tercatat');
                await loadEligible(); await loadList();
            } catch (err) { error.textContent = err.message; }
        };
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
    else init();
})();
