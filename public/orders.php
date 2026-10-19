<?php require __DIR__ . '/partials/layout.php';
head('Pesanan', 'orders');
topbar('Pesanan'); ?>
<div class="seg wide rise" id="tabs"><button data-t="validasi" class="on">Menunggu Validasi (<span id="cv">0</span>)</button><button data-t="proses">Proses &amp; Selesai (<span id="cp">0</span>)</button></div>
<section class="card center rise">
    <h3>Kapasitas Dapur</h3>
    <svg class="gauge" viewBox="0 0 200 110">
        <path class="g-bg" d="M20 100A80 80 0 0 1 180 100" />
        <path class="g-fg" id="gfg" d="M20 100A80 80 0 0 1 180 100" pathLength="100" />
    </svg>
    <div class="gnum" id="gpct">0%</div><small id="gtxt"></small>
</section>
<label class="search rise">🔍︎<input id="q" placeholder="Cari pemesan atau ID pesanan"></label>
<div id="list"></div>
<?php nav('orders');
foot();
