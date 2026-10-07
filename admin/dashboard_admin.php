<?php

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admin - SE.Noesa</title>
    <link rel="stylesheet" href="../asset/style.css">
</head>
<body>
    <div class="app-container">

    <div class="admin-header">
        <h1 class="admin-title">Dashboard Admin</h1>
        <div class="profile-menu">
        <a href="#" class="profile-icon" onclick="toggleLogout(event)">👤</a>
        <a href="../login.php" class="logout-btn" id="logoutMenu">Logout</a>
        </div>
    </div>

    <div class="stats-row">
        <div class="stat-card">
            <span class="stat-emoji">📋</span>
        <p class="stat-label">Dipesan</p>
        <p class="stat-value">-</p>
        </div>

    <div class="stat-card">
        <span class="stat-emoji">📦</span>
        <p class="stat-label">Sisa</p>
        <p class="stat-value">-</p>
    </div>

    <div class="stat-card">
        <div class="stat-emoji">🎯</div>
        <p class="stat-label">Maks</p>
        <p class="stat-value">-</p>
    </div>
    </div>

    <div class="action-buttons">
        <a href="verifikasi_pesanan.php" class="action-btn">
            <span class="btn-icon">🔍</span>
            <span class="btn-text">Verifikasi Pesanan</span>
            <span class="btn-arrow">❯</span>
        </a>

        <a href="pesanan_siap_antar.php" class="action-btn">
            <span class="btn-icon">🚚</span>
            <span class="btn-text">Pesanan Siap Antar</span>
            <span class="btn-arrow">❯</span>
        </a>
    </div>
    </div>

    <script src="../asset/script.js"></script>
</body>
</html>