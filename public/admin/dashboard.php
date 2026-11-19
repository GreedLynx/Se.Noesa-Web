<?php
require __DIR__ . '/partials/layout.php';
admin_head('Dashboard', 'dashboard');
?>
<section class="welcome-row">
    <div>
        <p class="eyebrow dashboard-status"><span></span><b id="dashboard-status-text">Dapur beroperasi normal</b></p>
        <div class="dashboard-welcome-title">
            <h2>Selamat datang, <?= htmlspecialchars(me()['username'], ENT_QUOTES, 'UTF-8') ?>!</h2>
            <button class="dashboard-refresh" type="button" data-refresh-dashboard aria-label="Segarkan data"><span class="material-symbols-outlined">sync</span></button>
        </div>
        <p class="muted">
            Pantau pesanan dapur artisanal dan kuota katering hari ini.
        </p>
    </div>
</section>
<section class="stats-grid" id="admin-stats" aria-live="polite">
    <article class="stat-card">
        <div class="dashboard-stat-top"><span class="stat-icon"><span class="material-symbols-outlined">receipt_long</span></span><span class="stat-chip">Hari ini</span></div>
        <span class="stat-label">
            Pesanan hari
      ini
        </span>
        <strong class="stat-value" data-stat="orders">
            --
        </strong>
        <small class="stat-hint dashboard-orders-change" data-orders-change>Semua status pesanan</small>
    </article>
    <article class="stat-card">
        <div class="dashboard-stat-top"><span class="stat-icon dashboard-stat-secondary"><span class="material-symbols-outlined">task_alt</span></span><span class="stat-chip stat-chip-secondary">Perlu cek</span></div>
        <span class="stat-label">
            Perlu
      validasi
        </span>
        <strong class="stat-value" data-stat="pending">
            --
        </strong>
        <small class="stat-hint">Menunggu pemeriksaan</small>
    </article>
    <article class="stat-card">
        <div class="dashboard-stat-top"><span class="stat-icon"><span class="material-symbols-outlined">skillet</span></span><span class="stat-chip" data-capacity-percent>—</span></div>
        <span class="stat-label">
            Kapasitas
      dapur
        </span>
        <strong class="stat-value dashboard-capacity-value"><span data-stat="capacity">--</span><small data-capacity-total>/ -- porsi</small></strong>
        <small class="stat-hint"
      data-stat="capacity-hint">
            memuat kapasitas
        </small>
        <div class="meter">
            <i data-meter>
            </i>
        </div>
    </article>
    <article class="stat-card">
        <div class="dashboard-stat-top"><span class="stat-icon dashboard-stat-critical"><span class="material-symbols-outlined">warning</span></span><span class="stat-chip stat-chip-critical">Kritis</span></div>
        <span class="stat-label">Kuota menipis</span>
        <strong class="stat-value"><span data-stat="critical">--</span> Menu
        </strong>
        <small class="stat-hint"><b data-critical-name>—</b><span data-critical-remaining></span></small>
    </article>
</section>
<section class="dashboard-action-heading" id="action-queue">
    <h2>Butuh Tindakan <span id="dashboard-action-category-count">2 Kategori</span></h2>
    <a href="orders.php">Lihat Semua <span class="material-symbols-outlined">chevron_right</span></a>
</section>
<section class="content-grid">
    <article class="panel chart-panel">
        <div class="panel-heading">
            <div>
                <p class="eyebrow">
                    PERFORMA
                </p>
                <h2>
                    Pesanan 7 hari terakhir
                </h2>
            </div>
            <span class="pill soft">
                Pesanan selesai
            </span>
        </div>
        <div class="chart" id="orders-chart">
            <div class="loading">
                Memuat grafik...
            </div>
        </div>
    </article>
    <article class="panel priority-panel">
        <div class="panel-heading">
            <div>
                    <p class="eyebrow">ANTREAN PEMBAYARAN</p>
                <h2>
                    Menunggu verifikasi pembayaran
                </h2>
            </div>
            <span class="dashboard-priority-count" id="dashboard-priority-count">— Pesanan</span>
        </div>
        <div id="priority-list">
            <div class="loading">
                Memuat antrean...
            </div>
        </div>
    </article>
</section>
<section class="panel production-panel dashboard-production" aria-labelledby="production-title">
    <div class="panel-heading">
        <div>
            <p class="eyebrow">PERSIAPAN PRODUKSI</p>
            <h2 id="production-title">Rekap menu per tanggal kirim</h2>
            <p class="muted">Jumlah mencakup pesanan aktif; pesanan ditolak dan dibatalkan dikecualikan.</p>
        </div>
        <span class="pill soft" id="production-date"></span>
    </div>
    <div id="production-list" aria-live="polite">
        <div class="loading">Memuat rekap produksi...</div>
    </div>
</section>
<section class="panel cancellation-panel" aria-labelledby="cancellation-title">
    <div class="panel-heading">
        <div>
            <p class="eyebrow">ANTREAN TINJAUAN</p>
            <h2 id="cancellation-title">Pembatalan belum ditinjau <span id="cancellation-count">--</span></h2>
            <p class="muted" id="cancellation-urgent-count">Memuat pembatalan...</p>
        </div>
        <a href="orders.php?tab=dibatalkan&amp;review=unreviewed">Lihat semua &rarr;</a>
    </div>
    <p class="cancellation-intro">Buka detail untuk memeriksa status, bukti bayar, dan tindak lanjut. Menandai sudah ditinjau hanya mencatat pemeriksaan; refund tidak diproses di aplikasi.</p>
    <div id="cancellation-list" aria-live="polite">
        <div class="loading">Memuat pembatalan...</div>
    </div>
</section>
<section class="panel activity-panel">
    <div class="panel-heading">
        <div>
            <p class="eyebrow">
                RINGKASAN
            </p>
            <h2>
                Aktivitas terbaru
            </h2>
        </div>
        <a href="orders.php">
            Kelola pesanan &rarr;
        </a>
    </div>
    <div class="activity-list" id="activity-list">
        <div class="loading">
            Memuat aktivitas...
        </div>
    </div>
</section>
<?php admin_foot(); ?>
