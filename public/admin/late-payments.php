<?php
require __DIR__ . '/partials/layout.php';
admin_head('Pembayaran Terlambat', 'late-payments');
?>
<section class="page-head late-page-head">
    <div class="late-page-copy">
        <p class="eyebrow">REKONSILIASI PEMBAYARAN</p>
        <h2>Pembayaran Terlambat</h2>
        <p class="muted">Tinjau pembayaran yang masuk setelah pesanan kedaluwarsa, lalu tentukan tindak lanjut yang paling tepat.</p>
    </div>
    <aside class="late-process" aria-label="Alur rekonsiliasi pembayaran">
        <span class="late-process-icon" aria-hidden="true"><span class="ui-icon icon-validasi"></span></span>
        <div><b>3 langkah sederhana</b><small>Catat bukti &middot; Verifikasi mutasi &middot; Tindak lanjuti</small></div>
    </aside>
</section>

<section class="panel late-record-panel">
    <div class="panel-heading late-panel-heading"><div><span class="late-section-kicker">LANGKAH 1 DARI 3</span><h2>Catat pembayaran terlambat</h2><p>Pilih pesanan yang dibatalkan otomatis atau ditolak Admin, lalu catat bukti transaksi pelanggan.</p></div><span class="tag soft">Admin</span></div>
    <form id="late-record-form" class="late-form" enctype="multipart/form-data">
        <div class="late-form-grid">
            <div class="late-field-wide late-order-search">
                <label for="late-order-code">Cari kode pesanan dari pelanggan</label>
                <div class="late-order-search-controls">
                    <input id="late-order-code" type="search" maxlength="50" autocomplete="off" placeholder="Contoh: SN-261124-DA3141" required>
                    <button class="button secondary" id="late-order-search-button" type="button">Cari pesanan</button>
                </div>
                <small>Masukkan minimal 3 karakter kode. Hasil menampilkan pesanan dibatalkan/ditolak yang belum direkonsiliasi; sebagian kode juga bisa dicari.</small>
                <p id="late-order-search-result" class="muted" aria-live="polite"></p>
                <label for="late-order">Hasil pencarian</label>
                <select id="late-order" name="id" required disabled><option value="">Cari kode pesanan terlebih dahulu</option></select>
            </div>
            <div class="late-field-wide late-subsection-heading">
                <b>Rincian pembayaran</b>
                <span>Isi data sesuai bukti transfer yang diberikan pelanggan.</span>
            </div>
            <label>Nominal dilaporkan
                <input type="number" name="nominal_dilaporkan" min="0" step="1" placeholder="Contoh: 150000" required>
            </label>
            <label>Waktu transaksi
                <input type="datetime-local" name="waktu_transaksi" required>
            </label>
            <label>Referensi transaksi <span>(opsional)</span>
                <input type="text" name="referensi_transaksi" maxlength="100" placeholder="Nomor referensi QRIS">
            </label>
            <label>File bukti
                <input type="file" name="proof" accept="image/jpeg,image/png,image/webp" required>
                <small>JPG, PNG, atau WebP &middot; maksimal 2 MB</small>
            </label>
            <label class="late-field-wide">Catatan Admin <span>(opsional)</span>
                <textarea name="catatan_admin" maxlength="1000" rows="3" placeholder="Contoh: Pelanggan menghubungi melalui WhatsApp pada 18 Nov."></textarea>
            </label>
        </div>
        <div class="late-form-footer"><p>Jika pembayaran valid, lanjutkan pesanan hanya setelah pelanggan setuju dan kuota tersedia. Jika penuh, tindak lanjuti via WhatsApp.</p><button class="button primary" type="submit">Simpan untuk diverifikasi <span class="ui-icon icon-panah-kanan" aria-hidden="true"></span></button></div>
        <p class="form-error" id="late-record-error" role="alert"></p>
    </form>
</section>

<section class="panel late-list-panel">
    <div class="panel-heading late-panel-heading"><div><span class="late-section-kicker">RIWAYAT</span><h2>Daftar rekonsiliasi</h2><p>Pantau status verifikasi dan tindak lanjut setiap pembayaran terlambat.</p></div><button class="button small secondary" id="late-refresh" type="button"><span class="ui-icon icon-segarkan" aria-hidden="true"></span> Muat ulang</button></div>
    <div id="late-list" class="late-list">Memuat…</div>
</section>

<section class="panel" id="late-detail-panel" hidden>
    <div class="panel-heading"><h2>Detail rekonsiliasi</h2><button class="button small secondary" id="late-close" type="button">Tutup</button></div>
    <div id="late-detail"></div>
    <div id="late-log"></div>
</section>
<?php
admin_foot();
