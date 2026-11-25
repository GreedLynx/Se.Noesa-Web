<?php
require_once __DIR__ . '/../../../app/bootstrap.php';

function head(string $title, string $page, bool $auth = false)
{
    if (!$auth) {
        require_owner(false);
    }
    $GLOBALS['owner_page'] = $page;
    $GLOBALS['owner_auth'] = $auth;
    $assetPrefix = $auth ? 'assets/' : '../assets/';
    $u = me();
    ?>
<!doctype html>
<html lang="id">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
    <meta name="csrf-token" content="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
    <title><?= htmlspecialchars($title) ?> · <?= $auth ? 'se.noesa' : 'Se.Noesa' ?></title>
    <link rel="icon" type="image/png" sizes="16x16 32x32" href="<?= $auth ? 'assets/img/logo-icon.png' : '../assets/img/logo-icon.png' ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700&display=swap">
    <link rel="stylesheet" href="<?= $assetPrefix ?>css/app.css?v=<?= filemtime(__DIR__ . '/../../assets/css/app.css') ?>">
    <link rel="stylesheet" href="<?= $assetPrefix ?>component/icons.css?v=<?= filemtime(__DIR__ . '/../../assets/component/icons.css') ?>">
    <link rel="stylesheet" href="<?= $assetPrefix ?>css/date-picker.css?v=<?= filemtime(__DIR__ . '/../../assets/css/date-picker.css') ?>">
    <link rel="stylesheet" href="<?= $assetPrefix ?>css/menu-notifications.css?v=<?= filemtime(__DIR__ . '/../../assets/css/menu-notifications.css') ?>">
</head>

<body data-page="<?= htmlspecialchars($page, ENT_QUOTES, 'UTF-8') ?>" data-role="owner">
    <main class="app<?= $auth ? ' auth' : '' ?>">
<?php
}

function topbar(string $title, bool $back = false)
{
    $hari = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
    $bulan = [1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
    $now = new DateTimeImmutable('now', new DateTimeZone('Asia/Jakarta'));
    $todayText = $hari[(int) $now->format('w')] . ', ' . $now->format('j') . ' ' . $bulan[(int) $now->format('n')] . ' ' . $now->format('Y');
    ?>
        <header class="top rise">
            <div class="row">
                <?php if ($back): ?>
                    <a class="btn-ic" href="orders.php" aria-label="Kembali ke pesanan">
                        <span class="ui-icon icon-panah-kiri" aria-hidden="true"></span>
                    </a>
                <?php endif; ?>
                <div>
                    <h1><?= $title ?></h1>
                </div>
            </div>
            <div class="row">
                <a class="owner-header-notifications" href="menu-notifications.php" aria-label="Buka notifikasi">
                    <span class="ui-icon icon-notifikasi" aria-hidden="true"></span>
                    <span>Notifikasi</span>
                </a>
                <span class="today-label" id="today-label"><?= htmlspecialchars($todayText, ENT_QUOTES, 'UTF-8') ?></span>
            </div>
        </header>
        <?php if (in_array($GLOBALS['owner_page'] ?? '', ['products', 'orders'], true)):
                $quotaDay = $_GET['tgl_kirim'] ?? $now->format('Y-m-d');
                if (!is_string($quotaDay)) {
                    $quotaDay = '';
                }
                ?>
            <section class="date-panel" aria-label="Filter tanggal kuota">
                <div>
                    <span class="date-eyebrow">RINGKASAN KUOTA</span>
                    <p class="date-panel-hint" id="quota-date-hint">Lihat kuota dan kapasitas pada tanggal pilihan.</p>
                </div>
                <div class="date-field">
                    <label for="quota-date">Tanggal kuota dapur dan produk</label>
                    <input class="form-control" id="quota-date" type="date" value="<?= htmlspecialchars($quotaDay, ENT_QUOTES, 'UTF-8') ?>"
                        aria-describedby="quota-date-hint" data-date-picker>
                </div>
            </section>
            <?php if (($GLOBALS['owner_page'] ?? '') === 'products'): ?>
                <p id="quota-summary" role="status"></p>
            <?php endif; ?>
        <?php endif; ?>
<?php
}

function nav(string $on)
{
    $u = me();
    $i = [
        'dashboard' => ['Dashboard', 'dashboard', 'dashboard.php'],
        'orders' => ['Pesanan', 'pesanan', 'orders.php'],
        'products' => ['Produk', 'produk', 'products.php'],
        'notifications' => ['Notifikasi', 'notifikasi', 'menu-notifications.php'],
        'payments' => ['Metode Pembayaran', 'pembayaran', 'payments.php']
    ];
    ?>
        <nav class="nav">
            <a class="owner-brand" href="dashboard.php">
                <span class="owner-brand-mark"><img src="../assets/img/logo-icon.png" alt=""></span>
                <span>Se.Noesa<small>OWNER PANEL</small></span>
            </a>
            <span class="pill" id="pill"></span>
            <?php foreach ($i as $k => [$l, $icon, $href]): ?>
                <?php if ($k === 'notifications') continue; ?>
                <a href="<?= htmlspecialchars($href, ENT_QUOTES, 'UTF-8') ?>" class="<?= $on === $k ? 'on' : '' ?>">
                    <span class="ui-icon icon-<?= $icon ?>" aria-hidden="true"></span>
                    <span><?= $l ?></span>
                </a>
            <?php endforeach; ?>
            <button class="owner-mobile-logout" type="button" data-logout aria-label="Keluar dari akun owner">
                <span class="ui-icon icon-keluar" aria-hidden="true"></span>
                <span>Keluar</span>
            </button>
            <div class="owner-sidebar-bottom">
                <div class="owner-profile">
                    <span class="avatar"><?= htmlspecialchars(strtoupper(substr($u['username'] ?? 'O', 0, 1)), ENT_QUOTES, 'UTF-8') ?></span>
                    <span><b><?= htmlspecialchars($u['username'] ?? 'Owner', ENT_QUOTES, 'UTF-8') ?></b><small>Owner</small></span>
                </div>
                <button class="owner-signout" type="button" data-logout>
                    <span class="ui-icon icon-keluar" aria-hidden="true"></span>
                    <span>Keluar</span>
                </button>
            </div>
        </nav>
<?php
}

function foot()
{
    $assetPrefix = !empty($GLOBALS['owner_auth']) ? 'assets/' : '../assets/';
    ?>
    </main>

    <!-- DIALOG KONFIRMASI CUSTOM -->
    <dialog id="app-confirm-dialog" class="confirm-dialog">
        <div class="confirm-content">
            <div class="confirm-icon" id="confirm-icon"><span class="ui-icon icon-berhasil" aria-hidden="true"></span></div>
            <h2 id="confirm-title" style="text-align:center; font-size: 22px; margin: 15px 0 10px; color: var(--gd);">Konfirmasi</h2>
            <p class="muted" id="confirm-message" style="text-align:center; font-size: 13px; line-height: 1.5; margin-bottom: 25px;">Apakah Anda yakin?</p>
        </div>
        <div class="dialog-actions" style="justify-content:center; display: flex; gap: 10px; width: 100%;">
            <button type="button" class="btn-main" data-close-confirm style="flex: 1; background: #e2e8e1; color: var(--br);">Batal</button>
            <button type="button" class="btn-main" id="confirm-yes-btn" style="flex: 1;">Ya, Lanjutkan</button>
        </div>
    </dialog>

    <div class="toast" id="toast"></div>
    <script src="<?= $assetPrefix ?>js/app.js?v=<?= filemtime(__DIR__ . '/../../assets/js/app.js') ?>"></script>
    <script src="<?= $assetPrefix ?>js/date-picker.js?v=<?= filemtime(__DIR__ . '/../../assets/js/date-picker.js') ?>"></script>
    <script src="<?= $assetPrefix ?>js/menu-notifications.js?v=<?= filemtime(__DIR__ . '/../../assets/js/menu-notifications.js') ?>"></script>
</body>

</html>
<?php
}
