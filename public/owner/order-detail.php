<?php
require __DIR__ . '/partials/layout.php';
head('Detail Pesanan', 'detail');
topbar('Detail Pesanan', true);
?>
<div class="owner-detail-heading">
    <a class="owner-back-link" href="orders.php">&larr; Kembali ke pesanan</a>
    <span class="owner-status" id="detail-status">Memuat</span>
</div>
<div id="order-detail" data-id="<?= (int) ($_GET['id'] ?? 0) ?>">
    <div class="skel"></div>
    <div class="skel"></div>
</div>
<div class="owner-detail-actions" id="detail-actions" hidden>
    <button class="owner-danger" data-order-action="reject" type="button">Tolak pembayaran</button>
    <button class="btn-main" data-order-action="validate" type="button">Konfirmasi pembayaran</button>
    <button class="btn-main" data-order-action="complete" type="button">Tandai selesai &amp; kirim</button>
</div>
<dialog id="reject-order-dialog" class="owner-dialog">
    <form id="reject-order-form">
        <div class="owner-dialog-heading">
            <div><small>PENOLAKAN PEMBAYARAN</small><h2>Berikan alasan</h2></div>
            <button type="button" class="owner-close" data-cancel-rejection aria-label="Tutup">&times;</button>
        </div>
        <p class="owner-muted">Alasan ini akan ditampilkan kepada pelanggan pada detail pesanan.</p>
        <label for="rejection-reason">Alasan penolakan
            <textarea id="rejection-reason" name="alasan_penolakan" rows="4" maxlength="500" required placeholder="Contoh: Nominal transfer tidak sesuai dengan total pesanan."></textarea>
        </label>
        <p id="rejection-reason-error" class="owner-error" role="alert"></p>
        <div class="owner-dialog-actions">
            <button type="button" class="owner-secondary" data-cancel-rejection>Batal</button>
            <button type="submit" class="owner-danger">Tolak pembayaran</button>
        </div>
    </form>
</dialog>
<?php
foot();
