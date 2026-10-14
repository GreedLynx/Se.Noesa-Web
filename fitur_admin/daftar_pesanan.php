<?php
// admin/daftar_pesanan.php

// Contoh data pesanan (ganti dengan data dari database)
$pesanan = [
    ['username' => 'Username', 'tanggal' => 'DD/MM/YY', 'jam' => '00:00 WIB'],
    ['username' => 'Username', 'tanggal' => 'DD/MM/YY', 'jam' => '00:00 WIB'],
    ['username' => 'Username', 'tanggal' => 'DD/MM/YY', 'jam' => '00:00 WIB'],
    ['username' => 'Username', 'tanggal' => 'DD/MM/YY', 'jam' => '00:00 WIB'],
    ['username' => 'Username', 'tanggal' => 'DD/MM/YY', 'jam' => '00:00 WIB'],
    ['username' => 'Username', 'tanggal' => 'DD/MM/YY', 'jam' => '00:00 WIB'],
    ['username' => 'Username', 'tanggal' => 'DD/MM/YY', 'jam' => '00:00 WIB'],
    ['username' => 'Username', 'tanggal' => 'DD/MM/YY', 'jam' => '00:00 WIB'],
    ['username' => 'Username', 'tanggal' => 'DD/MM/YY', 'jam' => '00:00 WIB'],
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar Pesanan - Admin</title>
    <link rel="stylesheet" href="../asset/style.css">
</head>
<body class="admin-pesanan-bg">
    <div class="admin-pesanan-page">

        <!-- Judul Admin -->
        <h1 class="admin-pesanan-title">Admin</h1>

        <!-- Tombol Back & Refresh -->
        <div class="admin-pesanan-actions">
            <a href="dashboard_admin.php" class="admin-icon-btn" aria-label="Kembali">
                <svg viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M15 6l-6 6 6 6" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </a>
            <button type="button" class="admin-icon-btn" aria-label="Refresh" onclick="location.reload()">
                <svg viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M4 12a8 8 0 0 1 13.7-5.6L20 8M20 4v4h-4M20 12a8 8 0 0 1-13.7 5.6L4 16M4 20v-4h4"
                          fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </button>
        </div>

        <!-- Panel daftar pesanan -->
        <div class="admin-pesanan-panel">
            <?php foreach ($pesanan as $p): ?>
                <a href="detail_pesanan.php?user=<?= urlencode($p['username']) ?>" class="admin-order-row">
                    <span class="admin-order-user"><?= htmlspecialchars($p['username']) ?></span>
                    <span class="admin-order-time">
                        <span class="admin-order-date"><?= htmlspecialchars($p['tanggal']) ?></span>
                        <span class="admin-order-hour"><?= htmlspecialchars($p['jam']) ?></span>
                    </span>
                </a>
            <?php endforeach; ?>
        </div>

    </div>
</body>
</html>