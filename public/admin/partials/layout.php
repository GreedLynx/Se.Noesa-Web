<?php
require_once __DIR__ . '/../../../app/bootstrap.php';
function admin_head(string $title, string $page): void {
  $u = require_role('admin', false);
  ?><!doctype html>
<html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?=htmlspecialchars($title, ENT_QUOTES, 'UTF-8')?> | Se.Noesa Admin</title>
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700&display=swap">
<link rel="stylesheet" href="assets/admin.css?v=<?=filemtime(__DIR__.'/../assets/admin.css')?>"></head>
<body data-page="<?=htmlspecialchars($page, ENT_QUOTES, 'UTF-8')?>">
<div class="admin-shell"><aside class="sidebar">
  <a class="brand" href="dashboard.php"><span class="brand-mark">S</span><span>Se.Noesa<small>ADMIN PANEL</small></span></a>
  <div class="side-label">MENU UTAMA</div>
  <nav class="side-nav">
    <a href="dashboard.php" class="<?=$page==='dashboard'?'active':''?>"><span class="nav-icon">&#9638;</span>Dashboard</a>
    <a href="orders.php" class="<?=in_array($page,['orders','order-detail'],true)?'active':''?>"><span class="nav-icon">&#9776;</span>Pesanan <i id="pending-badge"></i></a>
    <a href="products.php" class="<?=$page==='products'?'active':''?>"><span class="nav-icon">&#9633;</span>Produk &amp; Kuota</a>
  </nav>
  <div class="sidebar-bottom"><div class="admin-profile"><span class="avatar"><?=htmlspecialchars(strtoupper(substr($u['username']??'A',0,1)), ENT_QUOTES, 'UTF-8')?></span><span><b><?=htmlspecialchars($u['username']??'Admin', ENT_QUOTES, 'UTF-8')?></b><small>Administrator</small></span></div><button class="signout" data-logout>&#10140; Keluar</button></div>
</aside><main class="main-panel">
<header class="topbar"><button class="menu-toggle" aria-label="Buka menu">&#9776;</button><div class="top-copy"><small>Se.Noesa / Admin</small><h1><?=htmlspecialchars($title, ENT_QUOTES, 'UTF-8')?></h1></div><div class="top-actions"><span class="today-label" id="today-label"></span><span class="avatar top-avatar"><?=htmlspecialchars(strtoupper(substr($u['username']??'A',0,1)), ENT_QUOTES, 'UTF-8')?></span></div></header>
<div class="page-content">
<?php
}
function admin_foot(): void { ?>
</div><nav class="mobile-nav"><a href="dashboard.php">&#9638;<span>Dashboard</span></a><a href="orders.php">&#9776;<span>Pesanan</span></a><a href="products.php">&#9633;<span>Produk</span></a><button data-logout>&#10140;<span>Keluar</span></button></nav>
</main></div><div class="toast" id="toast"></div><script src="assets/admin.js?v=<?=filemtime(__DIR__.'/../assets/admin.js')?>"></script></body></html><?php }
