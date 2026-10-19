<?php require __DIR__ . '/partials/layout.php';
head('Dashboard', 'dashboard');
topbar('Dashboard'); ?>
<section class="rise">
    <h2 class="greet">Selamat Pagi, <span id="nama"><?= ucfirst(me()['username']) ?></span></h2>
    <p class="sub">Pantauan performa operasional &amp; kapasitas dapur Se.Noesa.</p>
</section>
<section class="hero-card rise">
    <div class="row between"><small id="hLabel">OMZET TERVERIFIKASI HARI INI</small><span class="chip-d" id="growth"></span></div>
    <div class="big">Rp <b id="omzet" data-count="0">0</b></div>
    <p class="ok">✓ Pesanan Lunas</p>
    <div class="stats">
        <div><small>TIKET RATA-RATA</small><b id="tiket">-</b></div>
        <div><small id="l2">KUANTITAS PAX</small><b id="items">-</b></div>
        <div><small>STATUS LUNAS</small><b>100% Lunas</b></div>
    </div>
</section>
<section class="card rise">
    <div class="row between">
        <h3>Tren Pesanan</h3>
        <div class="seg" id="period"><button data-p="today" class="on">Hari ini</button><button data-p="week">Minggu</button><button data-p="month">Bulan</button></div>
    </div>
    <div class="bars" id="bars"></div>
</section>
<section class="card rise">
    <div class="row between">
        <h3>Pesanan Masuk</h3><span class="ic">▤</span>
    </div>
    <div class="num" id="orders">0 Pesanan</div>
    <div class="row between meta"><span id="tot">Total Hari Ini</span><b>100% Lunas</b></div>
</section>
<section class="card rise">
    <div class="row between">
        <h3>Rincian Kategori</h3><span class="ic">◔</span>
    </div>
    <div id="kat"></div>
</section>
<h3 class="sec rise">Produk Terlaris</h3>
<div id="top"></div>
<?php nav('dashboard');
foot();
