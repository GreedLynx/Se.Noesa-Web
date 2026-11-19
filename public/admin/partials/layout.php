<?php
require_once __DIR__ . '/../../../app/bootstrap.php';

function admin_head(string $title, string $page): void
{
    $u = require_role('admin', false);
    $GLOBALS['admin_page'] = $page;
?>
<!doctype html>
<html lang="id">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <meta name="csrf-token" content="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
    <title><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?> | Se.Noesa Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700&display=swap">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200">
    <link rel="stylesheet" href="assets/admin.css?v=<?= filemtime(__DIR__ . '/../assets/admin.css') ?>">
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
                    <span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20" aria-hidden="true"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg></span>Dashboard
                </a>
                <a href="orders.php" class="<?= in_array($page, ['orders', 'order-detail'], true) ? 'active' : '' ?>">
                    <span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20" aria-hidden="true"><path d="M4 6h16M4 12h16M4 18h16"/></svg></span>Pesanan <i id="pending-badge"></i>
                </a>
                <a href="products.php" class="<?= $page === 'products' ? 'active' : '' ?>">
                    <span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20" aria-hidden="true"><path d="M3 7l9-4 9 4-9 4-9-4z"/><path d="M3 7v10l9 4 9-4V7"/></svg></span>Produk &amp; Kuota
                </a>
                <a href="schedules.php" class="<?= $page === 'schedules' ? 'active' : '' ?>"><span class="nav-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20">
                            <circle cx="12" cy="12" r="9"/>
                            <path d="M12 7v5l3 3"/>
                        </svg>
                    </span>Jadwal
                </a>
                <a href="menu-notifications.php" class="<?= $page === 'menu-notifications' ? 'active' : '' ?>">
                    <span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20" aria-hidden="true"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4"/></svg></span>Notifikasi
                </a>
            </nav>
            <div class="sidebar-bottom">
                <div class="admin-profile">
                    <span class="avatar"><?= htmlspecialchars(strtoupper(substr($u['username'] ?? 'A', 0, 1)), ENT_QUOTES, 'UTF-8') ?></span>
                    <span><b><?= htmlspecialchars($u['username'] ?? 'Admin', ENT_QUOTES, 'UTF-8') ?></b><small>Administrator</small></span>
                </div>
                <button class="signout" data-logout><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18" aria-hidden="true"><path d="M14 4h5v16h-5M3 12h12m-4-4 4 4-4 4"/></svg> Keluar</button>
            </div>
        </aside>
        <main class="main-panel">
            <header class="topbar admin-topbar<?= $page === 'dashboard' ? ' dashboard-topbar' : '' ?>">
                <a class="dashboard-brand mobile-admin-brand" href="dashboard.php" aria-label="Se.Noesa Admin">
                    <span class="dashboard-brand-mark">S</span>
                    <span class="dashboard-brand-copy"><span class="dashboard-brand-title"><b>Se.Noesa</b><em>ADMIN</em></span><small>Artisanal Catering</small></span>
                </a>
                <?php if ($page !== 'dashboard'): ?>
                <div class="top-copy admin-desktop-page-title">
                    <small>Se.Noesa / Admin</small>
                    <h1><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></h1>
                </div>
                <?php endif; ?>
                <div class="dashboard-top-actions">
                    <?php if ($page === 'dashboard'): ?>
                    <button class="dashboard-top-button" type="button" data-set-today aria-label="Pilih tanggal hari ini"><span class="material-symbols-outlined">today</span><span>Hari Ini</span></button>
                    <?php elseif (in_array($page, ['products', 'orders'], true)): ?>
                    <button class="dashboard-top-button" type="button" data-set-today aria-label="Pilih tanggal hari ini"><span class="material-symbols-outlined">today</span><span>Hari Ini</span></button>
                    <?php else: ?>
                    <a class="dashboard-top-button" href="schedules.php" aria-label="Jadwal operasional"><span class="material-symbols-outlined">today</span><span>Hari Ini</span></a>
                    <?php endif; ?>
                    <a class="dashboard-top-icon<?= $page === 'menu-notifications' ? ' is-current' : '' ?>" href="menu-notifications.php" aria-label="Notifikasi"<?= $page === 'menu-notifications' ? ' aria-current="page"' : '' ?>><span class="material-symbols-outlined">notifications</span></a>
                    <div class="dashboard-account">
                        <button class="dashboard-top-avatar" type="button" data-account-menu-toggle aria-label="Menu akun" aria-expanded="false" aria-controls="dashboard-account-menu">
                            <span class="material-symbols-outlined">person</span>
                        </button>
                        <div class="dashboard-account-menu" id="dashboard-account-menu" role="menu" hidden>
                            <div class="dashboard-account-summary">
                                <b><?= htmlspecialchars($u['username'] ?? 'Admin', ENT_QUOTES, 'UTF-8') ?></b>
                                <small>Administrator</small>
                            </div>
                            <button type="button" class="dashboard-account-logout" data-logout role="menuitem">
                                <span class="material-symbols-outlined">logout</span>Keluar
                            </button>
                        </div>
                    </div>
                </div>
            </header>
            <div class="page-content<?= $page === 'dashboard' ? ' dashboard-page' : '' ?>">
            <?php if (in_array($page, ['products', 'orders', 'dashboard'], true)):
                    $quotaDay = $_GET['tgl_kirim'] ?? (new DateTimeImmutable('now', new DateTimeZone('Asia/Jakarta')))->format('Y-m-d');
                    if (!is_string($quotaDay)) {
                            $quotaDay = '';
                    }
                    ?>
                <section class="date-panel" aria-label="Filter tanggal kuota">
                    <div>
                        <span class="date-eyebrow">KUOTA &amp; KAPASITAS</span>
                        <p class="date-panel-hint" id="quota-date-hint"><?= $page === 'dashboard' ? 'Pantau kuota dapur untuk tanggal pengiriman pilihan.' : 'Lihat kapasitas pada tanggal pilihan. Daftar pesanan tetap mengikuti tab dan pencarian.' ?></p>
                    </div>
                    <div class="date-field">
                        <label for="quota-date">Tanggal kuota dapur dan produk</label>
                        <input class="form-control" id="quota-date" type="date" value="<?= htmlspecialchars($quotaDay, ENT_QUOTES, 'UTF-8') ?>"
                            aria-describedby="quota-date-hint" data-date-picker>
                    </div>
                    <?php if ($page === 'dashboard'): ?>
                    <a class="dashboard-queue-link" href="#action-queue"><span>Antrean</span><b id="dashboard-pending-count">—</b><span class="material-symbols-outlined">arrow_forward</span></a>
                    <?php endif; ?>
                </section>
            <?php endif; ?>
<?php
}

function admin_foot(): void
{
    $page = $GLOBALS['admin_page'] ?? '';
    ?>
            </div>
            <nav class="mobile-nav dashboard-mobile-nav" aria-label="Navigasi admin">
                <a href="dashboard.php" class="<?= $page === 'dashboard' ? 'active' : '' ?>"<?= $page === 'dashboard' ? ' aria-current="page"' : '' ?>><span class="material-symbols-outlined">dashboard</span><span>Dashboard</span></a>
                <a href="orders.php" class="<?= in_array($page, ['orders', 'order-detail'], true) ? 'active' : '' ?>"<?= in_array($page, ['orders', 'order-detail'], true) ? ' aria-current="page"' : '' ?>><span class="dashboard-nav-icon"><span class="material-symbols-outlined">receipt_long</span><span class="dashboard-nav-badge" data-pending-count hidden>0</span></span><span>Pesanan</span></a>
                <a href="products.php" class="<?= $page === 'products' ? 'active' : '' ?>"<?= $page === 'products' ? ' aria-current="page"' : '' ?>><span class="material-symbols-outlined">inventory_2</span><span>Menu &amp; Kuota</span></a>
                <a href="schedules.php" class="<?= $page === 'schedules' ? 'active' : '' ?>"<?= $page === 'schedules' ? ' aria-current="page"' : '' ?>><span class="material-symbols-outlined">calendar_month</span><span>Jadwal</span></a>
            </nav>
        </main>
    </div>

    <!-- DIALOG KONFIRMASI CUSTOM (MODERN) -->
    <dialog id="app-confirm-dialog" class="product-dialog confirm-dialog">
        <div class="confirm-content">
            <div class="confirm-icon" id="confirm-icon">&#10003;</div>
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
    <script src="../assets/js/date-picker.js?v=<?= filemtime(__DIR__ . '/../../assets/js/date-picker.js') ?>"></script>
    <script src="../assets/js/menu-notifications.js?v=<?= filemtime(__DIR__ . '/../../assets/js/menu-notifications.js') ?>"></script>
</body>

</html>
<?php
}
