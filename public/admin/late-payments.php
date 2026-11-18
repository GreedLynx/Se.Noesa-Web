<?php
require __DIR__ . '/partials/layout.php';
admin_head('Pembayaran Terlambat', 'late-payments');
?>
<section class="page-head">
    <div>
        <p class="eyebrow">REKONSILIASI PEMBAYARAN</p>
        <h2>Pembayaran Terlambat</h2>
        <p class="muted">Catat bukti pembayaran QRIS yang diterima setelah pesanan kedaluwarsa. Status pesanan lama tidak diubah.</p>
    </div>
</section>

<section class="panel">
    <div class="panel-heading"><h2>Catat bukti terlambat</h2><span class="tag soft">Admin</span></div>
    <form id="late-record-form" class="late-form" enctype="multipart/form-data">
        <label>Pesanan dibatalkan
            <select id="late-order" name="id" required></select>
        </label>
        <label>Nominal dilaporkan
            <input type="number" name="nominal_dilaporkan" min="0" step="1" required>
        </label>
        <label>Waktu transaksi
            <input type="datetime-local" name="waktu_transaksi" required>
        </label>
        <label>Referensi transaksi (opsional)
            <input type="text" name="referensi_transaksi" maxlength="100">
        </label>
        <label>Catatan Admin (opsional)
            <textarea name="catatan_admin" maxlength="1000" rows="2"></textarea>
        </label>
        <label>File bukti (JPG/PNG/WebP, maks. 2 MB)
            <input type="file" name="proof" accept="image/jpeg,image/png,image/webp" required>
        </label>
        <button class="button primary" type="submit">Simpan catatan</button>
        <p class="form-error" id="late-record-error" role="alert"></p>
    </form>
</section>

<section class="panel">
    <div class="panel-heading"><h2>Daftar rekonsiliasi</h2><button class="button small secondary" id="late-refresh" type="button">Muat ulang</button></div>
    <div id="late-list" class="late-list">Memuat…</div>
</section>

<section class="panel" id="late-detail-panel" hidden>
    <div class="panel-heading"><h2>Detail rekonsiliasi</h2><button class="button small secondary" id="late-close" type="button">Tutup</button></div>
    <div id="late-detail"></div>
    <div id="late-log"></div>
</section>
<?php
admin_foot();
