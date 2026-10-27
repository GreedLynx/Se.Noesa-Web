<?php require __DIR__ . '/partials/layout.php';
admin_head('Detail Pesanan', 'order-detail'); ?>
<div class="detail-heading"><a class="back-link" href="orders.php">&larr; Kembali ke pesanan</a><span class="status-badge" id="detail-status">Memuat</span></div>
<div class="detail-grid" id="order-detail">
    <article class="panel loading">Memuat detail pesanan...</article>
</div>
<div class="detail-actions" id="detail-actions" hidden><button class="button danger-outline" data-order-action="reject">Tolak pembayaran</button><button class="button primary" data-order-action="validate">Konfirmasi pembayaran</button><button class="button primary" data-order-action="complete">Tandai selesai &amp; kirim</button></div>
<?php admin_foot(); ?>