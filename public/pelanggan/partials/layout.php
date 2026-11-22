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
    <title><?= htmlspecialchars($title) ?> · Se.Noesa</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/customer.css?v=<?= $css('customer.css') ?>">
    <link rel="stylesheet" href="assets/quantity-input.css?v=<?= $css('quantity-input.css') ?>">
    <link rel="stylesheet" href="assets/catalog-mobile.css?v=<?= $css('catalog-mobile.css') ?>">
    <link rel="stylesheet" href="../assets/css/date-picker.css?v=<?= filemtime(__DIR__ . '/../../assets/css/date-picker.css') ?>">
    <link rel="stylesheet" href="assets/menu-notifications.css?v=<?= $css('menu-notifications.css') ?>">
</head>

<body data-page="<?= htmlspecialchars($page) ?>">
    <div class="customer-shell">
        <aside class="customer-rail">
            <a class="customer-logo" href="riwayat.php" aria-label="Profil pelanggan">
                <svg viewBox="0 0 24 24" aria-hidden="true">
                    <circle cx="12" cy="8" r="3.3" />
                    <path d="M5.5 19c.7-3.1 3.1-4.8 6.5-4.8s5.8 1.7 6.5 4.8" />
                    <circle cx="12" cy="12" r="10" />
                </svg>
            </a>
            <nav class="customer-page-nav" aria-label="Navigasi pelanggan">
                <a class="<?= $page === 'catalog' ? 'active' : '' ?>" href="index.php">
                    <span>◯</span>
                    <small>Menu</small>
                </a>
                <a class="<?= $page === 'history' ? 'active' : '' ?>" href="riwayat.php">
                    <span>☰</span>
                    <small>Riwayat</small>
                </a>
                <a class="<?= $page === 'notifications' ? 'active' : '' ?>" href="menu-notifications.php">
                    <span>✉</span>
                    <small>Notifikasi</small>
                    <b class="customer-notif-count desktop" data-notif-count<?= $unreadNotifications > 0 ? '' : ' hidden' ?>><?= $unreadNotifications ?></b>
                </a>
            </nav>
        </aside>
        <main class="customer-main">
            <header class="customer-top">
                <div><small>Se.Noesa / Pelanggan</small></div>
                <div class="customer-user">
                    <span class="user-icon"><?= htmlspecialchars(strtoupper(substr($customer['username'] ?? 'P', 0, 1))) ?></span>
                    <span class="user-name"><?= htmlspecialchars($customer['username'] ?? 'Pelanggan') ?></span>
                    <button class="logout-text" type="button" data-logout>Keluar</button>
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
        <nav class="customer-mobile-nav" aria-label="Navigasi pelanggan">
            <a class="<?= $GLOBALS['customer_page'] === 'catalog' ? 'active' : '' ?>" href="index.php" aria-label="Menu">
                <svg viewBox="0 0 24 24" aria-hidden="true">
                    <circle cx="12" cy="12" r="8" />
                    <path d="M4 12h16" />
                </svg>
                <span>Menu</span>
            </a>
            <a class="<?= $GLOBALS['customer_page'] === 'history' ? 'active' : '' ?>" href="riwayat.php" aria-label="Riwayat">
                <svg viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M5 6h14M5 12h14M5 18h14" />
                </svg>
                <span>Riwayat</span>
            </a>
            <a class="<?= $GLOBALS['customer_page'] === 'notifications' ? 'active' : '' ?>" href="menu-notifications.php" aria-label="Notifikasi">
                <svg viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4" />
                </svg>
                <span>Notifikasi</span>
                <b class="customer-notif-count mobile" data-notif-count<?= $unreadNotifications > 0 ? '' : ' hidden' ?>><?= $unreadNotifications ?></b>
            </a>
            <button type="button" data-logout aria-label="Keluar">
                <svg viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9" />
                </svg>
                <span>Keluar</span>
            </button>
        </nav>
        <dialog id="detail-dialog" class="customer-dialog" aria-label="Detail menu">
            <button class="dialog-close" data-close-detail aria-label="Tutup">&times;</button>
            <div id="detail-content"></div>
        </dialog>
        <dialog id="checkout-dialog" class="customer-dialog checkout-dialog" aria-labelledby="checkout-title">
            <button class="dialog-close" data-close-checkout aria-label="Tutup">&times;</button>
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
                <label for="checkout-date">
                    Tanggal kirim
                    <input id="checkout-date" name="tgl_kirim" type="date" required>
                </label>
                <div class="catalog-policy" style="margin-top:10px">
                    <ul>
                        <li>Pastikan tanggal kirim dan jumlah porsi sesuai kebutuhan acara.</li>
                        <li>Kuota menu mengikuti tanggal pengiriman yang dipilih.</li>
                    </ul>
                </div>
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
