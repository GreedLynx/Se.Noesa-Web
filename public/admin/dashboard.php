<?php require __DIR__ . '/partials/layout.php';
admin_head('Dashboard', 'dashboard'); ?>
<section class="welcome-row">
  <div>
    <p class="eyebrow">PANTAU OPERASIONAL HARI INI</p>
    <h2>Selamat datang, <?= htmlspecialchars(me()['username'], ENT_QUOTES, 'UTF-8') ?>!</h2>
    <p class="muted">Ringkasan pesanan, kapasitas dapur, dan produk Se.Noesa.</p>
  </div><a class="button primary" href="orders.php">Buka antrean <span>&rarr;</span></a>
</section>
<section class="stats-grid" id="admin-stats" aria-live="polite">
  <article class="stat-card"><span class="stat-icon">&#9776;</span><span class="stat-label">Pesanan hari ini</span><strong class="stat-value" data-stat="orders">--</strong><small class="stat-hint">semua status</small></article>
  <article class="stat-card"><span class="stat-icon">&#10003;</span><span class="stat-label">Perlu validasi</span><strong class="stat-value" data-stat="pending">--</strong><small class="stat-hint">menunggu pemeriksaan</small></article>
  <article class="stat-card"><span class="stat-icon">&#9749;</span><span class="stat-label">Kapasitas dapur</span><strong class="stat-value" data-stat="capacity">--</strong><small class="stat-hint" data-stat="capacity-hint">memuat kapasitas</small>
    <div class="meter"><i data-meter></i></div>
  </article>
  <article class="stat-card"><span class="stat-icon">&#9888;</span><span class="stat-label">Produk kritis</span><strong class="stat-value" data-stat="critical">--</strong><small class="stat-hint">kuota tersisa 20% atau kurang</small></article>
</section>
<section class="content-grid">
  <article class="panel chart-panel">
    <div class="panel-heading">
      <div>
        <p class="eyebrow">PERFORMA</p>
        <h2>Pesanan 7 hari terakhir</h2>
      </div><span class="pill soft">Pesanan selesai</span>
    </div>
    <div class="chart" id="orders-chart">
      <div class="loading">Memuat grafik...</div>
    </div>
  </article>
  <article class="panel priority-panel">
    <div class="panel-heading">
      <div>
        <p class="eyebrow">BUTUH TINDAKAN</p>
        <h2>Antrean prioritas</h2>
      </div><a href="orders.php">Lihat semua &rarr;</a>
    </div>
    <div id="priority-list">
      <div class="loading">Memuat antrean...</div>
    </div>
  </article>
</section>
<section class="panel activity-panel">
  <div class="panel-heading">
    <div>
      <p class="eyebrow">RINGKASAN</p>
      <h2>Aktivitas terbaru</h2>
    </div><a href="orders.php">Kelola pesanan &rarr;</a>
  </div>
  <div class="activity-list" id="activity-list">
    <div class="loading">Memuat aktivitas...</div>
  </div>
</section>
<?php admin_foot(); ?>