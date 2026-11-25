<?php
require __DIR__ . '/partials/layout.php';
head('Daftar Pesanan', 'orders');
topbar('Pesanan');
?>
<section class="owner-orders-intro rise">
    <div>
        <h2>Antrean pesanan</h2>
        <p>Periksa pembayaran, proses pesanan, dan pantau jadwal pengiriman.</p>
    </div>
</section>
<section class="card owner-capacity rise">
    <div>
        <h3>Kapasitas dapur</h3>
        <p id="orders-capacity">Memuat kapasitas...</p>
    </div>
    <span class="capacity-icon" aria-hidden="true"><span class="ui-icon icon-kapasitas"></span></span>
</section>
<div class="seg order-tabs rise" id="order-tabs" role="tablist" aria-label="Filter status pesanan">
    <button class="on" type="button" data-tab="semua">Semua <b id="all-count">0</b></button>
    <button type="button" data-tab="pembayaran">Menunggu pembayaran <b id="payment-count">0</b></button>
    <button type="button" data-tab="validasi">Menunggu verifikasi <b id="pending-count">0</b></button>
    <button type="button" data-tab="proses">Proses <b id="processing-count">0</b></button>
    <button type="button" data-tab="selesai">Selesai <b id="completed-count">0</b></button>
    <button type="button" data-tab="ditolak">Ditolak <b id="rejected-count">0</b></button>
</div>
<label class="search rise">
    <span class="ui-icon icon-cari" aria-hidden="true"></span>
    <input id="order-search" type="search" placeholder="Cari pemesan atau kode pesanan">
</label>
<div id="order-list" aria-live="polite">
    <p class="empty">Memuat pesanan...</p>
</div>

<?php
nav('orders');
foot();
