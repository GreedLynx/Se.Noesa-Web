<?php
require __DIR__ . '/partials/layout.php';
head('Detail Pesanan', 'detail');
topbar('Detail Pesanan', true);
?>
<div class="owner-detail-heading">
    <a class="owner-back-link" href="orders.php">‹ Kembali ke pesanan</a>
    <span class="owner-status" id="detail-status">Memuat</span>
</div>
<div id="order-detail" data-id="<?= (int) ($_GET['id'] ?? 0) ?>">
    <div class="skel"></div>
    <div class="skel"></div>
</div>
<?php
foot();
