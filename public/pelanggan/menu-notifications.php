<?php
require __DIR__ . '/partials/layout.php';

customer_head('Notifikasi Menu', 'notifications'); ?>
<div id="mnc-page" data-api="api/menu-notifications.php">
<section class="catalog-intro">
    <div>
        <p class="eyebrow">PEMBERITAHUAN</p>
        <h2>Notifikasi Menu</h2>
        <p class="catalog-description">Perubahan menu baru berlaku setelah Admin mengonfirmasi pilihanmu. Status pesanan dan pembayaran tidak berubah karena notifikasi ini.</p>
    </div>
</section>
    <div class="mnc-toolbar">
        <button type="button" class="customer-button secondary" id="mnc-refresh">Muat ulang</button>
    </div>
    <section class="mnc-list" id="mnc-list" aria-live="polite">
        <p class="mnc-empty">Memuat notifikasi…</p>
    </section>
    <button type="button" class="customer-button secondary mnc-more" id="mnc-more" hidden>Muat lebih banyak</button>
</div>
<dialog id="mnc-detail-dialog" class="customer-dialog mnc-dialog" aria-label="Detail notifikasi menu">
    <button class="dialog-close" type="button" data-mnc-close aria-label="Tutup">&times;</button>
    <div id="mnc-detail"></div>
</dialog>
<?php customer_foot(); ?>
