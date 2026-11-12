<?php
require_once __DIR__ . '/../../../app/bootstrap.php';

function admin_head(string $title, string $page): void
{
    $u = require_role('admin', false);
?>
<!doctype html>
<html lang="id">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?> | Se.Noesa Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700&display=swap">
    <link rel="stylesheet" href="assets/admin.css?v=<?= filemtime(__DIR__ . '/../assets/admin.css') ?>">
    <link rel="stylesheet" href="../assets/css/date-picker.css?v=<?= filemtime(__DIR__ . '/../../assets/css/date-picker.css') ?>">
</head>

<body data-page="<?= htmlspecialchars($page, ENT_QUOTES, 'UTF-8') ?>">
    <div class="admin-shell">
        <aside class="sidebar">
            <a class="brand" href="dashboard.php">
                <span class="brand-mark">S</span>
                <span>Se.Noesa<small>ADMIN PANEL</small></span>
            </a>
            <div class="side-label">MENU UTAMA</div>
            <nav class="side-nav">
                <a href="dashboard.php" class="<?= $page === 'dashboard' ? 'active' : '' ?>">
                    <span class="nav-icon">&#9638;</span>Dashboard
                </a>
                <a href="orders.php" class="<?= in_array($page, ['orders', 'order-detail'], true) ? 'active' : '' ?>">
                    <span class="nav-icon">&#9776;</span>Pesanan <i id="pending-badge"></i>
                </a>
                <a href="products.php" class="<?= $page === 'products' ? 'active' : '' ?>">
                    <span class="nav-icon">&#9633;</span>Produk &amp; Kuota
                </a>
                <a href="schedules.php" class="<?= $page === 'schedules' ? 'active' : '' ?>"><span class="nav-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="9"/>
                            <path d="M12 7v5l3 3"/>
                        </svg>
                    </span>Jadwal
                </a>
            </nav>
            <div class="sidebar-bottom">
                <div class="admin-profile">
                    <span class="avatar"><?= htmlspecialchars(strtoupper(substr($u['username'] ?? 'A', 0, 1)), ENT_QUOTES, 'UTF-8') ?></span>
                    <span><b><?= htmlspecialchars($u['username'] ?? 'Admin', ENT_QUOTES, 'UTF-8') ?></b><small>Administrator</small></span>
                </div>
                <button class="signout" data-logout>&#10140; Keluar</button>
            </div>
        </aside>
        <main class="main-panel">
            <header class="topbar">
                <button class="menu-toggle" aria-label="Buka menu">&#9776;</button>
                <div class="top-copy">
                    <small>Se.Noesa / Admin</small>
                    <!-- Tag <h1> dihapus dari sini agar tidak double dengan judul di bawah -->
                </div>
                <div class="top-actions">
                    <span class="today-label" id="today-label"></span>
                    <span class="avatar top-avatar"><?= htmlspecialchars(strtoupper(substr($u['username'] ?? 'A', 0, 1)), ENT_QUOTES, 'UTF-8') ?></span>
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

function admin_foot(): void
{
    ?>
            </div>
            <nav class="mobile-nav">
                <a href="dashboard.php">&#9638;<span>Dashboard</span></a>
                <a href="orders.php">&#9776;<span>Pesanan</span></a>
                <a href="products.php">&#9633;<span>Produk</span></a>
                <a href="schedules.php">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20">
                        <circle cx="12" cy="12" r="9"/>
                        <path d="M12 7v5l3 3"/>
                    </svg>
                    <span>Jadwal</span>
                </a>
                <button data-logout>&#10140;<span>Keluar</span></button>
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
</body>

</html>
<?php
}