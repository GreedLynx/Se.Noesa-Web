<?php
require_once __DIR__ . '/../../../app/bootstrap.php';

function admin_head(string $title, string $page): void
{
    $GLOBALS['__admin_current_page'] = $page;
    $u = require_role('admin', false);
?>
<!doctype html>
<html lang="id">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="csrf-token" content="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
    <title><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?> | Se.Noesa Admin</title>
    <link rel="icon" type="image/png" sizes="16x16 32x32" href="../assets/img/logo-icon.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700&display=swap">
    <link rel="stylesheet" href="assets/admin.css?v=<?= filemtime(__DIR__ . '/../assets/admin.css') ?>">
    <link rel="stylesheet" href="../assets/component/icons.css?v=<?= filemtime(__DIR__ . '/../../assets/component/icons.css') ?>">
    <link rel="stylesheet" href="../assets/css/date-picker.css?v=<?= filemtime(__DIR__ . '/../../assets/css/date-picker.css') ?>">
    <link rel="stylesheet" href="../assets/css/menu-notifications.css?v=<?= filemtime(__DIR__ . '/../../assets/css/menu-notifications.css') ?>">
</head>

<body data-page="<?= htmlspecialchars($page, ENT_QUOTES, 'UTF-8') ?>">
    <div class="admin-shell">
        <aside class="sidebar">
            <a class="brand" href="dashboard.php">
                <span class="brand-mark">S</span>
                <span>Se.Noesa<small>ADMIN PANEL</small></span>
            </a>
            <div class="side-label"></div>
            <nav class="side-nav">
                <a href="dashboard.php" class="<?= $page === 'dashboard' ? 'active' : '' ?>">
                    <span class="nav-icon"><span class="ui-icon icon-dashboard" aria-hidden="true"></span></span>Dashboard
                </a>
                <a href="orders.php" class="<?= in_array($page, ['orders', 'order-detail'], true) ? 'active' : '' ?>">
                    <span class="nav-icon"><span class="ui-icon icon-pesanan" aria-hidden="true"></span></span>Pesanan <i id="pending-badge"></i>
                </a>
                <a href="late-payments.php" class="<?= $page === 'late-payments' ? 'active' : '' ?>">
                    <span class="nav-icon"><span class="ui-icon icon-terlambat" aria-hidden="true"></span></span>Pembayaran Terlambat
                </a>
                <a href="products.php" class="<?= $page === 'products' ? 'active' : '' ?>">
                    <span class="nav-icon"><span class="ui-icon icon-produk" aria-hidden="true"></span></span>Produk &amp; Kuota
                </a>
                <a href="schedules.php" class="<?= $page === 'schedules' ? 'active' : '' ?>"><span class="nav-icon"><span class="ui-icon icon-kalender" aria-hidden="true"></span></span>Jadwal
                </a>
                <a href="menu-notifications.php" class="<?= $page === 'menu-notifications' ? 'active' : '' ?>">
                    <span class="nav-icon"><span class="ui-icon icon-notifikasi" aria-hidden="true"></span></span>Notifikasi
                </a>
            </nav>
            <div class="sidebar-bottom">
                <button class="signout" data-logout><span class="ui-icon icon-keluar" aria-hidden="true"></span> Keluar</button>
            </div>
        </aside>
        <main class="main-panel">
            <header class="topbar">
                <button class="menu-toggle" aria-label="Buka menu"><span class="ui-icon icon-menu-navigasi" aria-hidden="true"></span></button>
                <div class="top-copy">
                    <small>Se.Noesa / Admin</small>
                    <h1><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></h1>
                </div>
                <div class="top-actions">
                    <span class="today-label" id="today-label"></span>
                </div>
            </header>
            <div class="page-content">
            <?php if (in_array($page, ['products', 'orders', 'dashboard'], true)):
                    $quotaDay = $_GET['tgl_kirim'] ?? (new DateTimeImmutable('now', new DateTimeZone('Asia/Jakarta')))->format('Y-m-d');
                    if (!is_string($quotaDay)) {
                            $quotaDay = '';
                    }
                    ?>
                <section class="date-panel" aria-label="Filter tanggal kuota">
                    <div>
                        <span class="date-eyebrow">KUOTA &amp; KAPASITAS</span>
                        <p class="date-panel-hint" id="quota-date-hint">Lihat kapasitas pada tanggal pilihan. Daftar pesanan tetap mengikuti tab dan pencarian.</p>
                    </div>
                    <div class="date-field">
                        <label for="quota-date">Tanggal kuota dapur dan produk</label>
                        <input class="form-control" id="quota-date" type="date" value="<?= htmlspecialchars($quotaDay, ENT_QUOTES, 'UTF-8') ?>"
                            aria-describedby="quota-date-hint" data-date-picker>
                    </div>
                </section>
            <?php endif; ?>
<?php
}

function admin_foot(?string $page = null): void
{
    $page = $GLOBALS['__admin_current_page'] ?? '';
    ?>
            </div>
            <nav class="mobile-nav">
                <a href="dashboard.php" class="<?= $page === 'dashboard' ? 'active' : '' ?>"><span class="ui-icon icon-dashboard" aria-hidden="true"></span><span>Dashboard</span></a>
                <a href="orders.php" class="<?= in_array($page, ['orders', 'order-detail'], true) ? 'active' : '' ?>"><span class="ui-icon icon-pesanan" aria-hidden="true"></span><span>Pesanan</span></a>
                <a href="late-payments.php" class="<?= ($GLOBALS['__admin_current_page'] ?? '') === 'late-payments' ? 'active' : '' ?>"><span class="ui-icon icon-terlambat" aria-hidden="true"></span><span>Terlambat</span></a>
                <a href="products.php" class="<?= $page === 'products' ? 'active' : '' ?>"><span class="ui-icon icon-produk" aria-hidden="true"></span><span>Produk</span></a>
                <a href="schedules.php" class="<?= $page === 'schedules' ? 'active' : '' ?>">
                    <span class="ui-icon icon-kalender" aria-hidden="true"></span>
                    <span>Jadwal</span>
                </a>
                <a href="menu-notifications.php" class="<?= $page === 'menu-notifications' ? 'active' : '' ?>"><span class="ui-icon icon-notifikasi" aria-hidden="true"></span><span>Notifikasi</span></a>
                <button data-logout><span class="ui-icon icon-keluar" aria-hidden="true"></span><span>Keluar</span></button>
            </nav>
        </main>
    </div>

    <!-- DIALOG KONFIRMASI CUSTOM (MODERN) -->
    <dialog id="app-confirm-dialog" class="product-dialog confirm-dialog">
        <div class="confirm-content">
            <div class="confirm-icon" id="confirm-icon"><span class="ui-icon icon-berhasil" aria-hidden="true"></span></div>
            <h2 id="confirm-title" style="text-align:center; font-size: 24px; margin: 15px 0 10px; color: var(--ink);">Konfirmasi</h2>
            <p class="muted" id="confirm-message" style="text-align:center; font-size: 14px; line-height: 1.6; margin-bottom: 25px;">Apakah Anda yakin?</p>
        </div>
        <div class="dialog-actions" style="justify-content:center; display: flex; gap: 12px; width: 100%;">
            <button type="button" class="button secondary" data-close-confirm style="flex: 1;">Batal</button>
            <button type="button" class="button" id="confirm-yes-btn" style="flex: 1;">Ya, Lanjutkan</button>
        </div>
    </dialog>

    <div class="toast" id="toast"></div>
    <script src="assets/admin.js?v=<?= filemtime(__DIR__ . '/../assets/admin.js') ?>"></script>
    <?php if (($GLOBALS['__admin_current_page'] ?? '') === 'late-payments'): ?>
    <script src="assets/late-payments.js?v=<?= filemtime(__DIR__ . '/../assets/late-payments.js') ?>"></script>
    <?php endif; ?>
    <script src="../assets/js/date-picker.js?v=<?= filemtime(__DIR__ . '/../../assets/js/date-picker.js') ?>"></script>
    <script src="../assets/js/menu-notifications.js?v=<?= filemtime(__DIR__ . '/../../assets/js/menu-notifications.js') ?>"></script>
</body>

</html>
<?php
}
