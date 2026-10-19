<?php
// admin/dashboard_admin.php
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admin - SE.Noesa</title>
    <link rel="stylesheet" href="../asset/style.css">
</head>
<body class="admin-dashboard-bg">
    <div class="admin-dashboard-container">

        <!-- ============ HEADER ============ -->
        <div class="dash-header">
            <div class="dash-brand">
                <span class="dash-subtitle">Se.Noesa</span>
                <h1 class="dash-title">Dashboard</h1>
            </div>
            <div class="dash-profile">
                <!-- LONCENG: Klik untuk toggle notifikasi -->
                <button class="dash-icon-btn" id="bellBtn" onclick="toggleNotif()">
                    🔔
                    <span class="notif-dot" id="notifDot"></span>
                </button>
                
                <!-- PROFIL: Klik untuk logout ke halaman login -->
                <a href="../login.php" class="dash-avatar" title="Logout">A</a>
            </div>
        </div>

        <!-- ============ SEARCH BAR (Bisa Diketik) ============ -->
        <form class="dash-search" action="../fitur_admin/daftar_verifikasi.php" method="GET">
            <span class="search-icon">🔍</span>
            <input type="text" name="q" placeholder="Cari pesanan atau menu">
        </form>

        <!-- ============ STATISTIK CARDS ============ -->
        <div class="dash-stats-grid">
            <div class="dash-card">
                <div class="card-icon">📋</div>
                <div class="card-value">42</div>
                <div class="card-label">Pesanan hari ini <span class="highlight">+14%</span></div>
            </div>
            <div class="dash-card">
                <div class="card-icon">🔢</div>
                <div class="card-value">8</div>
                <div class="card-label">Perlu validasi <span class="highlight">5 DP</span></div>
            </div>
            <div class="dash-card">
                <div class="card-icon">🍲</div>
                <div class="card-value">84%</div>
                <div class="card-label">Kapasitas dapur <span class="highlight">+2%</span></div>
            </div>
            <div class="dash-card">
                <div class="card-icon">⚠️</div>
                <div class="card-value">3</div>
                <!-- PERBAIKAN: Menggunakan &lt; agar tidak error -->
                <div class="card-label">Menu kritis <span class="highlight">&lt;20 porsi</span></div>
            </div>
        </div>

        <!-- ============ GRAFIK TREN PESANAN ============ -->
        <div class="dash-chart-section">
            <div class="chart-header">
                <h2>Tren Pesanan</h2>
                <div class="chart-toggle">
                    <span class="active" id="btnMinggu" onclick="showChart('minggu')">Minggu</span>
                    <span id="btnBulan" onclick="showChart('bulan')">Bulan</span>
                </div>
            </div>
            
            <!-- Data Grafik Minggu (7 Hari) -->
            <div class="chart-bars" id="chartMinggu">
                <div class="bar-wrapper"><div class="bar" style="height: 30%;"></div><span>Sen</span></div>
                <div class="bar-wrapper"><div class="bar" style="height: 40%;"></div><span>Sel</span></div>
                <div class="bar-wrapper"><div class="bar" style="height: 35%;"></div><span>Rab</span></div>
                <div class="bar-wrapper"><div class="bar" style="height: 50%;"></div><span>Kam</span></div>
                <div class="bar-wrapper"><div class="bar" style="height: 65%;"></div><span>Jum</span></div>
                <div class="bar-wrapper"><div class="bar" style="height: 55%;"></div><span>Sab</span></div>
                <div class="bar-wrapper"><div class="bar" style="height: 45%;"></div><span>Min</span></div>
            </div>

            <!-- Data Grafik Bulan (5 Minggu) -->
            <div class="chart-bars" id="chartBulan" style="display: none;">
                <div class="bar-wrapper"><div class="bar" style="height: 40%;"></div><span>Mg-1</span></div>
                <div class="bar-wrapper"><div class="bar" style="height: 65%;"></div><span>Mg-2</span></div>
                <div class="bar-wrapper"><div class="bar" style="height: 55%;"></div><span>Mg-3</span></div>
                <div class="bar-wrapper"><div class="bar" style="height: 80%;"></div><span>Mg-4</span></div>
                <div class="bar-wrapper"><div class="bar" style="height: 70%;"></div><span>Mg-5</span></div>
            </div>
        </div>

        <!-- ============ ANTREAN PRIORITAS ============ -->
        <div class="dash-priority-section">
            <div class="priority-header">
                <h2>Antrean Prioritas</h2>
                <a href="../fitur_admin/daftar_verifikasi.php" class="see-all">Lihat semua</a>
            </div>
            
            <div class="priority-card">
                <div class="priority-top">
                    <span class="priority-id">#CAT-8821 - PT Mandiri Sejahtera</span>
                    <span class="badge badge-cooking">Sedang masak</span>
                </div>
                <div class="priority-bottom">
                    120 Box Nasi Kotak Premium B - Kirim 11:30
                </div>
            </div>

            <div class="priority-card">
                <div class="priority-top">
                    <span class="priority-id">#CAT-8825 - Ibu Maya Arifin</span>
                    <span class="badge badge-packing">Pengepakan</span>
                </div>
                <div class="priority-bottom">
                    80 Box Snack Syukuran - Kirim 12:15
                </div>
            </div>
        </div>

        <!-- ============ BOTTOM NAVIGATION ============ -->
        <div class="dash-bottom-nav">
            <!-- Ikon Dashboard (Aktif) -->
            <div class="nav-item active">
                <span class="nav-icon">📊</span>
                <span class="nav-label">Dashboard</span>
            </div>
            
            <!-- Ikon Pesanan → Mengarah ke daftar_verifikasi.php -->
            <a href="../fitur_admin/daftar_verifikasi.php" class="nav-item">
                <span class="nav-icon">📋</span>
                <span class="nav-label">Pesanan</span>
            </a>

            <!-- Ikon Produk → Mengarah ke produk.php -->
            <a href="../fitur_admin/produk.php" class="nav-item">
                <span class="nav-icon">📦</span>
                <span class="nav-label">Produk</span>
            </a>
        </div>

    </div>
    
    <script src="../asset/script.js"></script>
</body>
</html>