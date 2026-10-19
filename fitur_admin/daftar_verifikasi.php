<?php
// fitur_admin/daftar_verifikasi.php

$pesanan = [
    ['id' => '#CAT-8840', 'nama' => 'Andri Pratama', 'desk' => '150 Pcs Gathering Kantor', 'harga' => 'Rp 3.500.000', 'jam' => '10:32 WIB'],
    ['id' => '#CAT-8839', 'nama' => 'PT Surya Kencana', 'desk' => '220 Box Nasi Liwet', 'harga' => 'Rp 7.200.000', 'jam' => '09:45 WIB'],
    ['id' => '#CAT-8835', 'nama' => 'Bpk. Hendra Gunawan', 'desk' => '75 Pcs Coffee Break', 'harga' => 'Rp 1.850.000', 'jam' => '08:12 WIB'],
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar Pesanan Admin - Se.Noesa</title>
    <link rel="stylesheet" href="../asset/style.css">
</head>
<body class="admin-order-list-bg">
    <div class="ao-container">

        <!-- Header -->
        <div class="ao-header">
            <div class="ao-brand">
                <span class="ao-subtitle">Se.Noesa</span>
                <h1 class="ao-title">Pesanan</h1>
            </div>
            <div class="ao-profile">
                <button class="ao-icon-btn" id="bellBtn" onclick="toggleNotif()">
                    🔔<span class="notif-dot" id="notifDot"></span>
                </button>
                <a href="../login.php" class="ao-avatar">A</a>
            </div>
        </div>

        <!-- Tab Menu -->
        <div class="ao-tabs">
            <a href="daftar_verifikasi.php" class="ao-tab active">Menunggu Validasi (8)</a>
            <a href="proses_selesai.php" class="ao-tab">Proses & Selesai (34)</a>
        </div>

        <!-- Kapasitas Dapur Card -->
        <div class="ao-capacity-card">
            <h3 class="ao-capacity-title">Kapasitas Dapur</h3>
            <div class="ao-progress-ring">
                <svg viewBox="0 0 120 120">
                    <circle class="ring-bg" cx="60" cy="60" r="50"></circle>
                    <circle class="ring-fill" cx="60" cy="60" r="50" 
                            stroke-dasharray="314" stroke-dashoffset="50"></circle>
                </svg>
                <div class="ao-progress-text">
                    <span class="ao-progress-value">84%</span>
                </div>
            </div>
            <p class="ao-capacity-desc">1850 / 2200 porsi · sisa 350 pax aman</p>
        </div>

        <!-- Search Bar -->
        <form class="ao-search" action="daftar_verifikasi.php" method="GET">
            <span class="search-icon">🔍</span>
            <input type="text" name="q" placeholder="Cari pemesanan atau ID pesanan">
        </form>

        <!-- Daftar Pesanan -->
        <div class="ao-order-list">
            <?php foreach ($pesanan as $p): ?>
            <div class="ao-card">
                <div class="ao-card-top">
                    <span class="ao-order-id"><?= htmlspecialchars($p['id']) ?></span>
                    <span class="ao-order-time"><?= htmlspecialchars($p['jam']) ?></span>
                </div>
                <div class="ao-card-body">
                    <div class="ao-order-icon">👤</div>
                    <div class="ao-order-info">
                        <span class="ao-order-name"><?= htmlspecialchars($p['nama']) ?></span>
                        <span class="ao-order-desc"><?= htmlspecialchars($p['desk']) ?></span>
                    </div>
                    <span class="ao-order-price"><?= htmlspecialchars($p['harga']) ?></span>
                </div>
                <!-- PERBAIKAN: Link ke validasi_qris.php -->
                <a href="validasi_qris.php" class="ao-validate-btn">Validasi Bukti QRIS</a>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Bottom Navigation -->
        <div class="dash-bottom-nav">
            <a href="../admin/dashboard_admin.php" class="nav-item">
                <span class="nav-icon">📊</span>
                <span class="nav-label">Dashboard</span>
            </a>
            <a href="daftar_verifikasi.php" class="nav-item active">
                <span class="nav-icon">📋</span>
                <span class="nav-label">Pesanan</span>
            </a>
            <!-- PERBAIKAN: Link ke produk.php, bukan edit_menu.php -->
            <a href="produk.php" class="nav-item">
                <span class="nav-icon">📦</span>
                <span class="nav-label">Produk</span>
            </a>
        </div>

    </div>
    <script src="../asset/script.js"></script>
</body>
</html>