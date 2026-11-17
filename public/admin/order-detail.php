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
    <button class="button secondary" data-order-action="offer_reschedule">
        Tawarkan jadwal pengganti
    </button>
    <button class="button primary" data-order-action="review_cancellation">
        Sudah ditinjau
    </button>
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
    <p class="form-error" id="review-cancellation-error" role="alert"></p>
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
<dialog id="reschedule-order-dialog" class="product-dialog reject-order-dialog" aria-labelledby="reschedule-order-title">
    <form id="reschedule-order-form">
        <div class="dialog-heading">
            <div>
                <p class="eyebrow">JADWAL PENGGANTI</p>
                <h2 id="reschedule-order-title">Tawarkan tanggal baru</h2>
            </div>
            <button type="button" class="icon-close" data-cancel-reschedule aria-label="Tutup">&times;</button>
        </div>
        <p class="muted">Kuota tanggal baru akan diperiksa kembali ketika pelanggan menerima tawaran.</p>
        <p id="reschedule-payment-note" class="muted" hidden>Mengirim tawaran ini menyatakan pembayaran sudah valid. Jika pelanggan menerima, pesanan langsung berstatus Diproses.</p>
        <label for="reschedule-date">Tanggal pengiriman baru
            <input id="reschedule-date" name="tanggal_usulan" type="date" required>
        </label>
        <label for="reschedule-reason">Alasan perubahan jadwal
            <textarea id="reschedule-reason" name="alasan" rows="4" maxlength="500" required
                placeholder="Contoh: Operasional pada tanggal sebelumnya ditutup."></textarea>
        </label>
        <p id="reschedule-order-error" class="form-error" role="alert"></p>
        <div class="dialog-actions">
            <button type="button" class="button secondary" data-cancel-reschedule>Batal</button>
            <button type="submit" class="button primary">Kirim tawaran</button>
        </div>
    </form>
</dialog>
<?php admin_foot(); ?>
