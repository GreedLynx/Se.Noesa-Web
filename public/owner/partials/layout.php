<?php
require_once __DIR__ . '/../../../app/bootstrap.php';

function head(string $title, string $page, bool $auth = false)
{
    if (!$auth) {
            require_owner(false);
    }
    $GLOBALS['owner_page'] = $page;
    $u = me();
    ?>
<!doctype html>
<html lang="id">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
    <title><?= htmlspecialchars($title) ?> · Se.Noesa</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700&display=swap">
    <link rel="stylesheet" href="../assets/css/app.css?v=<?= filemtime(__DIR__ . '/../../assets/css/app.css') ?>">
    <link rel="stylesheet" href="../assets/css/date-picker.css?v=<?= filemtime(__DIR__ . '/../../assets/css/date-picker.css') ?>">
</head>

<body data-page="<?= $page ?>">
    <main class="app<?= $auth ? ' auth' : '' ?>">
<?php
}

function topbar(string $title, bool $back = false)
{
    $u = me();
    ?>
        <header class="top rise">
            <div class="row">
                <?php if ($back): ?>
                    <a class="btn-ic" href="orders.php" aria-label="Kembali">‹</a>
                <?php endif; ?>
                <div style="display: flex; align-items: center; height: 100%;">
                    <small style="font-size: 20px; font-weight: 600; color: var(--gd, #3b513e); margin: 0; letter-spacing: 0.3px;">Se.Noesa / Owner</small>
                </div>
            </div>
            <div class="row">
                <button class="btn-logout" type="button" data-logout>
                    <svg viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M10 17l5-5-5-5M15 12H3M12 3h6a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-6" />
                    </svg>
                    <span>Keluar</span>
                </button>
                <div class="avatar"><?= strtoupper(substr($u['username'] ?? 'A', 0, 1)) ?></div>
            </div>
        </header>
        <?php if (in_array($GLOBALS['owner_page'] ?? '', ['products', 'orders'], true)):
                $quotaDay = $_GET['tgl_kirim'] ?? (new DateTimeImmutable('now', new DateTimeZone('Asia/Jakarta')))->format('Y-m-d');
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
    $i = [
        'dashboard' => ['Dashboard', '<rect x="4" y="4" width="6" height="6" rx="1.5"/><rect x="14" y="4" width="6" height="6" rx="1.5"/><rect x="4" y="14" width="6" height="6" rx="1.5"/><rect x="14" y="14" width="6" height="6" rx="1.5"/>'],
        'orders' => ['Pesanan', '<path d="M7 3h10v18l-2.5-1.5L12 21l-2.5-1.5L7 21zM10 8h4M10 12h4"/>'],
        'products' => ['Produk', '<path d="M4 7h16v3H4zM5 10v9h14v-9M10 14h4"/>']
    ];
    ?>
        <nav class="nav">
            <span class="pill" id="pill"></span>
            <?php foreach ($i as $k => [$l, $p]): ?>
                <a href="<?= $k ?>.php" class="<?= $on === $k ? 'on' : '' ?>">
                    <svg viewBox="0 0 24 24"><?= $p ?></svg>
                    <span><?= $l ?></span>
                </a>
            <?php endforeach; ?>
        </nav>
<?php
}

function foot()
{
    ?>
    </main>

    <!-- DIALOG KONFIRMASI CUSTOM -->
    <dialog id="app-confirm-dialog" class="confirm-dialog">
        <div class="confirm-content">
            <div class="confirm-icon" id="confirm-icon">&#10003;</div>
            <h2 id="confirm-title" style="text-align:center; font-size: 22px; margin: 15px 0 10px; color: var(--gd);">Konfirmasi</h2>
            <p class="muted" id="confirm-message" style="text-align:center; font-size: 13px; line-height: 1.5; margin-bottom: 25px;">Apakah Anda yakin?</p>
        </div>
        <div class="dialog-actions" style="justify-content:center; display: flex; gap: 10px; width: 100%;">
            <button type="button" class="btn-main" data-close-confirm style="flex: 1; background: #e2e8e1; color: var(--br);">Batal</button>
            <button type="button" class="btn-main" id="confirm-yes-btn" style="flex: 1;">Ya, Lanjutkan</button>
        </div>
    </dialog>

    <div class="toast" id="toast"></div>
    <script src="../assets/js/app.js?v=<?= filemtime(__DIR__ . '/../../assets/js/app.js') ?>"></script>
    <script src="../assets/js/date-picker.js?v=<?= filemtime(__DIR__ . '/../../assets/js/date-picker.js') ?>"></script>
</body>

</html>
<?php
}