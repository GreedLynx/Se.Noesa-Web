<?php
require __DIR__ . '/partials/layout.php';

$customer = customer_head('Pilih Menu', 'catalog');
// SELECT mengambil nama dan nomor telepon pelanggan untuk mengisi informasi katalog.
$profile = q('SELECT username, phone FROM users WHERE id = ?', [$customer['id']])->fetch();
$firstDelivery = (new DateTimeImmutable('now', new DateTimeZone('Asia/Jakarta')))->modify('+7 days')->format('Y-m-d');
?>
<section class="catalog-intro">
    <div>
        <p class="eyebrow">MENU SE.NOESA</p>
        <h2>Pilih</h2>
        <p class="choice-subtitle">Menu Favoritmu</p>
        <p class="catalog-description">Pilih menu favorit untuk pesanan berikutnya.</p>
    </div>
    <button type="button" class="category-toggle" data-category-toggle aria-controls="category-rail" aria-expanded="false" aria-label="Buka kategori">
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h16M7 12h10M10 17h4" /></svg>
    </button>
    <a class="history-link" href="riwayat.php" aria-label="Riwayat pesanan">
        <span><svg viewBox="0 0 24 24" aria-hidden="true">
                <path d="M6 3h12v18l-6-3-6 3V3zM9 8h6M9 12h6" />
            </svg></span>
        <b>Riwayat pesanan</b>
    </a>
</section>
<section class="date-panel date-panel--delivery" aria-labelledby="delivery-title">
    <div>
        <span class="date-eyebrow">RENCANAKAN PESANANMU</span>
        <h2 class="date-panel-title" id="delivery-title"><span class="delivery-title-desktop">Mau dikirim kapan?</span><span class="delivery-title-mobile">Tanggal pengiriman</span></h2>
        <p class="date-panel-hint" id="delivery-hint">Pilih tanggal untuk melihat menu yang tersedia.<br>Pengiriman paling awal 7 hari dari hari ini.</p>
    </div>
    <div class="date-field">
        <label for="catalog-delivery-date"></label>
        <input class="form-control" id="catalog-delivery-date" type="date" min="<?= $firstDelivery ?>" value="<?= $firstDelivery ?>"
            aria-label="Tanggal pengiriman" aria-describedby="delivery-hint" data-date-picker required>
    </div>
</section>
<div class="catalog-toolbar">
    <label class="customer-search">
        <input id="catalog-search" type="search" placeholder="Cari menu" aria-label="Cari menu">
        <span><svg viewBox="0 0 24 24" aria-hidden="true">
                <circle cx="10.8" cy="10.8" r="6.8" />
                <path d="m16 16 5 5" />
            </svg></span>
    </label>
</div>
<section class="menu-grid" id="menu-grid" aria-live="polite"></section>
<div class="cart-bar" id="cart-bar" hidden>
    <div class="cart-icon">&#128722;<b id="cart-count">0</b></div>
    <span><strong id="cart-total">Rp 0</strong><small id="cart-summary">0 menu dipilih</small></span>
    <button id="checkout-button" class="customer-button primary">Lihat pesanan <span>&rarr;</span></button>
</div>
<dialog id="cart-dialog" class="customer-dialog cart-dialog" aria-labelledby="cart-title">
    <button class="dialog-close" data-close-cart aria-label="Tutup">&times;</button>
    <p class="eyebrow">RINGKASAN KERANJANG</p>
    <h2 id="cart-title">Menu pilihan</h2>
    <div id="cart-lines"></div>
    <div class="cart-total-row"><span>Total sementara</span><b id="cart-dialog-total">Rp 0</b></div>
    <button id="continue-checkout" class="customer-button primary">Lanjutkan</button>
</dialog>
<script>
    window.customerInfo = {
        name: <?= json_encode($profile['username'] ?? '', JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>,
        phone: <?= json_encode($profile['phone'] ?? '', JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>,
    };
</script>
<?php customer_foot(); ?>
