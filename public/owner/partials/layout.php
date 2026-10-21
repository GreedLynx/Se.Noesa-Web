<?php
require_once __DIR__ . '/../../../app/bootstrap.php';
function head(string $title, string $page, bool $auth = false)
{
  if (!$auth) require_owner(false);
  $u = me(); ?>
  <!doctype html>
  <html lang="id">

  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
    <title><?= htmlspecialchars($title) ?> · Se.Noesa</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700&display=swap">
    <link rel="stylesheet" href="../assets/css/app.css?v=<?= filemtime(__DIR__ . '/../../assets/css/app.css') ?>">
  </head>

  <body data-page="<?= $page ?>">
    <main class="app<?= $auth ? ' auth' : '' ?>">
    <?php }
  function topbar(string $title, bool $back = false)
  {
    $u = me(); ?>
      <header class="top rise">
        <div class="row"><?php if ($back): ?><a class="btn-ic" href="orders.php" aria-label="Kembali">‹</a><?php endif; ?>
          <div><small>Se.Noesa</small>
            <h1><?= $title ?></h1>
          </div>
        </div>
        <div class="row"><button class="btn-logout" type="button" data-logout><svg viewBox="0 0 24 24" aria-hidden="true">
              <path d="M10 17l5-5-5-5M15 12H3M12 3h6a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-6" />
            </svg><span>Keluar</span></button>
          <div class="avatar"><?= strtoupper(substr($u['username'] ?? 'A', 0, 1)) ?></div>
        </div>
      </header>
    <?php }
  function nav(string $on)
  {
    $i = [
      'dashboard' => ['Dashboard', '<rect x="4" y="4" width="6" height="6" rx="1.5"/><rect x="14" y="4" width="6" height="6" rx="1.5"/><rect x="4" y="14" width="6" height="6" rx="1.5"/><rect x="14" y="14" width="6" height="6" rx="1.5"/>'],
      'orders' => ['Pesanan', '<path d="M7 3h10v18l-2.5-1.5L12 21l-2.5-1.5L7 21zM10 8h4M10 12h4"/>'],
      'products' => ['Produk', '<path d="M4 7h16v3H4zM5 10v9h14v-9M10 14h4"/>']
    ]; ?>
      <nav class="nav"><span class="pill" id="pill"></span>
        <?php foreach ($i as $k => [$l, $p]): ?><a href="<?= $k ?>.php" class="<?= $on === $k ? 'on' : '' ?>"><svg viewBox="0 0 24 24"><?= $p ?></svg><span><?= $l ?></span></a><?php endforeach; ?></nav>
    <?php }
  function foot()
  { ?>
    </main>
    <div class="toast" id="toast"></div>
    <script src="../assets/js/app.js"></script>
  </body>

  </html><?php }
