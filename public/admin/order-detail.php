<?php
require __DIR__ . '/partials/layout.php';
admin_head('Detail Pesanan', 'order-detail');
?>
<div class="detail-heading">
    <a class="back-link" href="orders.php">
        &larr; Kembali ke pesanan
    </a>
    <span
        class="status-badge" id="detail-status">
        Memuat
    </span>
</div>
<div class="detail-grid" id="order-detail">
    <article class="panel loading">
        Memuat detail pesanan...
    </article>
</div>
<div class="detail-actions" id="detail-actions" hidden>
    <button class="button danger-outline"
        data-order-action="reject">
        Tolak pembayaran
    </button>
    <button class="button primary"
        data-order-action="validate">
        Konfirmasi pembayaran
    </button>
    <button class="button primary"
        data-order-action="complete">
        Tandai selesai &amp; kirim
    </button>
</div>
<dialog id="reject-order-dialog" class="product-dialog reject-order-dialog">
    <form id="reject-order-form">
        <div class="dialog-heading">
            <div>
                <p class="eyebrow">PENOLAKAN PEMBAYARAN</p>
                <h2>Berikan alasan</h2>
            </div>
            <button type="button" class="icon-close" data-cancel-rejection aria-label="Tutup">&times;</button>
        </div>
        <p class="muted">Alasan ini akan ditampilkan kepada pelanggan pada detail pesanan.</p>
        <label for="rejection-reason">Alasan penolakan
            <textarea id="rejection-reason" name="alasan_penolakan" rows="4" maxlength="500" required
                placeholder="Contoh: Nominal transfer tidak sesuai dengan total pesanan."></textarea>
        </label>
        <p id="rejection-reason-error" class="form-error" role="alert"></p>
        <div class="dialog-actions">
            <button type="button" class="button secondary" data-cancel-rejection>Batal</button>
            <button type="submit" class="button danger-outline">Tolak pembayaran</button>
        </div>
    </form>
</dialog>
<?php admin_foot(); ?>
