<?php
require_once __DIR__ . '/../../../app/bootstrap.php';
require_once __DIR__ . '/../../../app/menu-notifications.php';

function customer_head(string $title, string $page): array
{
    $customer = require_role('pelanggan', false);
    $GLOBALS['customer_page'] = $page;
    $unreadNotifications = menu_notice_customer_unread_count((int) $customer['id']);
    $GLOBALS['customer_unread'] = $unreadNotifications;
    $css = fn($file) => filemtime(__DIR__ . '/../assets/' . $file);
?>
<!doctype html>
<html lang="id">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
    <title><?= htmlspecialchars($title) ?> · se.noesa</title>
    <link rel="icon" type="image/png" sizes="16x16 32x32" href="../assets/img/logo-icon.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/customer.css?v=<?= $css('customer.css') ?>">
    <link rel="stylesheet" href="../assets/component/icons.css?v=<?= filemtime(__DIR__ . '/../../assets/component/icons.css') ?>">
    <link rel="stylesheet" href="assets/quantity-input.css?v=<?= $css('quantity-input.css') ?>">
    <link rel="stylesheet" href="assets/catalog-mobile.css?v=<?= $css('catalog-mobile.css') ?>">
    <link rel="stylesheet" href="../assets/css/date-picker.css?v=<?= filemtime(__DIR__ . '/../../assets/css/date-picker.css') ?>">
    <link rel="stylesheet" href="assets/menu-notifications.css?v=<?= $css('menu-notifications.css') ?>">
</head>

<body data-page="<?= htmlspecialchars($page) ?>">
    <div class="customer-shell">
        <aside class="customer-rail" id="customer-sidebar" tabindex="-1" aria-label="Menu pelanggan">
            <button class="customer-sidebar-close" type="button" aria-label="Tutup menu"><span class="ui-icon icon-tutup" aria-hidden="true"></span></button>
            <a class="customer-logo" href="index.php" aria-label="se.noesa — kembali ke katalog">
                <img src="../assets/img/logo-icon.png" alt="" width="32" height="40">
            </a>
            <nav class="customer-page-nav" aria-label="Navigasi pelanggan">
                <a class="<?= $page === 'catalog' ? 'active' : '' ?>" href="index.php">
                    <span class="ui-icon icon-menu-makanan" aria-hidden="true"></span>
                    <small>Menu</small>
                </a>
                <a class="<?= $page === 'history' ? 'active' : '' ?>" href="riwayat.php">
                    <span class="ui-icon icon-riwayat" aria-hidden="true"></span>
                    <small>Riwayat</small>
                </a>
                <a class="<?= $page === 'notifications' ? 'active' : '' ?>" href="menu-notifications.php">
                    <span class="ui-icon icon-notifikasi" aria-hidden="true"></span>
                    <small>Notifikasi</small>
                    <b class="customer-notif-count desktop" data-notif-count<?= $unreadNotifications > 0 ? '' : ' hidden' ?>><?= $unreadNotifications ?></b>
                </a>
            </nav>
            <button class="rail-logout" type="button" data-logout aria-label="Keluar dari akun">
                <span class="ui-icon icon-keluar" aria-hidden="true"></span>
                <small>Keluar</small>
            </button>
        </aside>
        <button class="customer-sidebar-backdrop" type="button" aria-label="Tutup menu" hidden></button>
        <main class="customer-main">
            <header class="customer-top">
                <button class="customer-sidebar-toggle" type="button" aria-label="Buka menu" aria-controls="customer-sidebar" aria-expanded="false"><span class="ui-icon icon-menu-navigasi" aria-hidden="true"></span></button>
                <div><small>se.noesa / Pelanggan</small></div>
                <div class="customer-user">
                    <span class="user-icon"><?= htmlspecialchars(strtoupper(substr($customer['username'] ?? 'P', 0, 1))) ?></span>
                    <span class="user-name"><?= htmlspecialchars($customer['username'] ?? 'Pelanggan') ?></span>
                </div>
            </header>
            <div class="customer-content">
<?php
    return $customer;
}

function customer_foot(): void
{
    $unreadNotifications = (int) ($GLOBALS['customer_unread'] ?? 0);
?>
            </div>
        </main>
        <dialog id="detail-dialog" class="customer-dialog" aria-label="Detail menu">
            <button class="dialog-close" data-close-detail aria-label="Tutup"><span class="ui-icon icon-tutup" aria-hidden="true"></span></button>
            <div id="detail-content"></div>
        </dialog>
        <dialog id="checkout-dialog" class="customer-dialog checkout-dialog" aria-labelledby="checkout-title">
            <button class="dialog-close" data-close-checkout aria-label="Tutup"><span class="ui-icon icon-tutup" aria-hidden="true"></span></button>
            <div class="dialog-heading">
                <p class="eyebrow">KONFIRMASI</p>
                <h2 id="checkout-title">Data pemesanan</h2>
                <p class="muted">Pilih alamat tersimpan atau tambahkan alamat baru.</p>
            </div>
            <form id="checkout-form">
                <label for="checkout-name">
                    Nama pemesan
                    <input id="checkout-name" name="nama_pemesan" required>
                </label>
                <label for="address-select">
                    Alamat pengiriman
                    <select id="address-select" name="alamat_id">
                        <option value="">+ Tambah alamat baru</option>
                    </select>
                </label>
                <div id="address-fields">
                    <label for="checkout-label">
                        Label alamat
                        <input id="checkout-label" name="label_alamat" value="Rumah" maxlength="50" required>
                    </label>
                    <div class="form-row">
                        <label for="checkout-recipient">
                            Nama penerima
                            <input id="checkout-recipient" name="nama_penerima" maxlength="100" required>
                        </label>
                        <label for="checkout-phone">
                            Nomor HP
                            <input id="checkout-phone" name="telepon" type="tel" maxlength="20" required>
                        </label>
                    </div>
                    <label for="checkout-address">
                        Alamat lengkap
                        <textarea id="checkout-address" name="alamat" rows="3" maxlength="2000" required></textarea>
                    </label>
                    <label class="save-address-option" for="checkout-save-address">
                        <input id="checkout-save-address" type="checkbox" name="simpan_alamat" value="1" checked> Simpan alamat ini untuk pesanan berikutnya
                    </label>
                    <label class="save-address-option" for="checkout-default-address">
                        <input id="checkout-default-address" type="checkbox" name="jadikan_default" value="1"> Jadikan alamat default
                    </label>
                </div>
                <div class="catalog-policy" style="margin-top:10px">
                    <ul>
                        <li>Pastikan tanggal kirim dan jumlah porsi sesuai kebutuhan acara.</li>
                        <li>Kuota menu mengikuti tanggal pengiriman yang dipilih.</li>
                    </ul>
                </div>
                <label for="checkout-date">
                    Tanggal kirim
                    <input id="checkout-date" name="tgl_kirim" type="date" required>
                </label>
                <label for="checkout-note">
                    Keterangan pesanan (opsional)
                    <textarea id="checkout-note" name="keterangan" rows="2" maxlength="120" placeholder="Contoh: tanpa sambal, kirim ke ruang rapat"></textarea>
                    <small class="field-hint">Maksimal 120 karakter.</small>
                </label>
                <p id="schedule-note" class="schedule-note"></p>
                <p class="field-hint">Batas maksimal pembayaran: 1 jam sejak pesanan dibuat. Jika belum dibayar, pesanan dibatalkan otomatis.</p>
                <p id="checkout-error" class="form-error" role="alert"></p>
                <button id="submit-order" class="customer-button primary" type="submit">Kirim pesanan</button>
            </form>
        </dialog>
        <div id="customer-toast" class="toast" role="status" aria-live="polite"></div>
    </div>
    <script>window.snoesaContact = <?= json_encode([
        'adminWhatsapp' => cfg()['contact']['admin_whatsapp'] ?? '',
        'instagram' => cfg()['contact']['instagram'] ?? '',
        'tiktok' => cfg()['contact']['tiktok'] ?? '',
    ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;</script>
    <script src="assets/customer.js?v=<?= filemtime(__DIR__ . '/../assets/customer.js') ?>"></script>
    <script src="assets/menu-notifications.js?v=<?= filemtime(__DIR__ . '/../assets/menu-notifications.js') ?>"></script>
    <script src="../assets/js/date-picker.js?v=<?= filemtime(__DIR__ . '/../../assets/js/date-picker.js') ?>"></script>
</body>

</html>
<?php
}
