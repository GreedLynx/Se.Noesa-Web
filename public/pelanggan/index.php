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
        <h2>Pilih Menu</h2>
        <p class="catalog-description">Pilih menu favorit untuk pesanan berikutnya.</p>
    </div>
</section>
<section class="date-panel date-panel--delivery" aria-label="Pilih tanggal pengiriman">
    <div class="date-field">
        <label for="catalog-delivery-date">Tanggal pengiriman</label>
        <input class="form-control" id="catalog-delivery-date" type="date" min="<?= $firstDelivery ?>" value="<?= $firstDelivery ?>"
            aria-describedby="delivery-hint quota-full-hint daily-quota-hint" data-date-picker data-availability-url="api/availability.php" required>
    </div>
    <p class="date-panel-hint" id="delivery-hint" role="status" hidden></p>
    <p class="date-panel-hint" id="quota-full-hint" role="status" hidden></p>
    <p class="date-panel-hint" id="daily-quota-hint" role="status" hidden></p>
</section>
<div class="catalog-toolbar">
    <div class="category-tabs" id="catalog-category-tabs" aria-label="Filter kategori">
        <button type="button" class="active" data-category="" aria-label="Semua menu" aria-pressed="true">Semua</button>
        <button type="button" data-category="makanan" aria-label="Makanan" aria-pressed="false">Makanan</button>
        <button type="button" data-category="minuman" aria-label="Minuman" aria-pressed="false">Minuman</button>
    </div>
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
    <p class="eyebrow">ISI KERANJANG</p> <!-- TEKS SUDAH DIUBAH -->
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
