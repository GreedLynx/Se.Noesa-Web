<?php require_once __DIR__.'/../app/bootstrap.php'; $go=me()?'dashboard.php':'login.php'; ?>
<!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Se.Noesa</title><link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Outfit:wght@600&display=swap">
<link rel="stylesheet" href="assets/css/app.css"></head>
<body data-page="splash" data-next="<?=htmlspecialchars($go,ENT_QUOTES,'UTF-8')?>"><main class="app splash"><div class="drift"></div>
<img class="logo-pop" src="assets/img/logo-full.png" alt="se.noesa"></main><script src="assets/js/app.js"></script></body></html>
