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
        <span class="late-process-icon" aria-hidden="true">&#10003;</span>
        <div><b>3 langkah sederhana</b><small>Catat bukti &middot; Verifikasi mutasi &middot; Tindak lanjuti</small></div>
    </aside>
</section>

<section class="panel late-record-panel">
    <div class="panel-heading late-panel-heading"><div><span class="late-section-kicker">LANGKAH 1 DARI 3</span><h2>Catat bukti terlambat</h2><p>Pilih pesanan yang dibatalkan, lalu lengkapi data transaksi dari pelanggan.</p></div><span class="tag soft">Admin</span></div>
    <form id="late-record-form" class="late-form" enctype="multipart/form-data">
        <div class="late-form-grid">
            <label class="late-field-wide">Pesanan dibatalkan
                <select id="late-order" name="id" required></select>
                <small>Hanya pesanan kedaluwarsa yang belum memiliki catatan rekonsiliasi.</small>
            </label>
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
        <div class="late-form-footer"><p>Pesanan lama tetap dibatalkan. Catatan ini tidak mengubah stok atau kuota.</p><button class="button primary" type="submit">Simpan untuk diverifikasi <span aria-hidden="true">&#8594;</span></button></div>
        <p class="form-error" id="late-record-error" role="alert"></p>
    </form>
</section>

<section class="panel late-list-panel">
    <div class="panel-heading late-panel-heading"><div><span class="late-section-kicker">RIWAYAT</span><h2>Daftar rekonsiliasi</h2><p>Pantau status verifikasi dan tindak lanjut setiap pembayaran terlambat.</p></div><button class="button small secondary" id="late-refresh" type="button">&#8635; Muat ulang</button></div>
    <div id="late-list" class="late-list">Memuat…</div>
</section>

<section class="panel" id="late-detail-panel" hidden>
    <div class="panel-heading"><h2>Detail rekonsiliasi</h2><button class="button small secondary" id="late-close" type="button">Tutup</button></div>
    <div id="late-detail"></div>
    <div id="late-log"></div>
</section>
<?php
admin_foot();
