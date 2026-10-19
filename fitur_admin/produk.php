<?php
// fitur_admin/produk.php

$produk = [
    ['id' => 'NK-01', 'nama' => 'Paket Nasi Kotak Ayam', 'harga' => 'Rp 42.000', 'stok' => '45/80 porsi'],
    ['id' => 'BF-04', 'nama' => 'Buffet Prasmanan', 'harga' => 'Rp 65.000', 'stok' => '18/200 pax'],
    ['id' => 'TP-02', 'nama' => 'Tumpeng Kuning', 'harga' => 'Rp 550.000', 'stok' => '3/10 paket'],
    ['id' => 'BT-04', 'nama' => 'Bento Beef Teriyaki', 'harga' => 'Rp 48.000', 'stok' => '72/120 porsi'],
    ['id' => 'SB-03', 'nama' => 'Snack Box Coffee Break', 'harga' => 'Rp 22.000', 'stok' => '0/100 box - habis'],
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Produk & Kuota - Se.Noesa</title>
    <link rel="stylesheet" href="../asset/style.css">
</head>
<body class="admin-order-list-bg">
    <div class="ao-container">

        <!-- Header -->
        <div class="ao-header">
            <div class="ao-brand">
                <span class="ao-subtitle">Se.Noesa</span>
                <h1 class="ao-title">Produk & Kuota</h1>
            </div>
            <div class="ao-profile">
                <button class="prod-add-btn" title="Tambah Produk">+</button>
                <button class="ao-icon-btn" id="bellBtn" onclick="toggleNotif()">
                    🔔<span class="notif-dot" id="notifDot"></span>
                </button>
                <a href="../login.php" class="ao-avatar">A</a>
            </div>
        </div>

        <!-- Search Bar -->
        <form class="dash-search" action="produk.php" method="GET" style="margin-bottom: 15px;">
            <span class="search-icon">🔍</span>
            <input type="text" name="q" placeholder="Cari nama menu atau kode">
        </form>

        <!-- Filter Tabs -->
        <div class="prod-filter-tabs">
            <span class="filter-tab active">Semua (24)</span>
            <span class="filter-tab">Nasi Kotak (10)</span>
            <span class="filter-tab">Prasmanan (5)</span>
            <span class="filter-tab">Minuman (9)</span>
        </div>

        <!-- Daftar Produk -->
        <div class="prod-list">
            <?php foreach ($produk as $p): ?>
            <div class="prod-card">
                <div class="prod-info">
                    <span class="prod-id-badge"><?= htmlspecialchars($p['id']) ?></span>
                    <div class="prod-details">
                        <span class="prod-name"><?= htmlspecialchars($p['nama']) ?></span>
                        <span class="prod-sub"><?= htmlspecialchars($p['harga']) ?> · <?= htmlspecialchars($p['stok']) ?></span>
                    </div>
                </div>
                <a href="edit_produk.php?id=<?= urlencode($p['id']) ?>" class="prod-edit-btn">✎ Edit</a>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Bottom Navigation -->
        <div class="dash-bottom-nav">
            <a href="../admin/dashboard_admin.php" class="nav-item">
                <span class="nav-icon">📊</span>
                <span class="nav-label">Dashboard</span>
            </a>
            <a href="daftar_verifikasi.php" class="nav-item">
                <span class="nav-icon">📋</span>
                <span class="nav-label">Pesanan</span>
            </a>
            <a href="produk.php" class="nav-item active">
                <span class="nav-icon">📦</span>
                <span class="nav-label">Produk</span>
            </a>
        </div>

    </div>
    <script src="../asset/script.js"></script>
</body>
</html>