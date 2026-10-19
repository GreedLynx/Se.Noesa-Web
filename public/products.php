<?php require __DIR__ . '/partials/layout.php';
head('Produk & Kuota', 'products');
topbar('Produk &amp; Kuota'); ?>
<label class="search rise">🔍︎<input id="q" placeholder="Cari nama menu atau kode"></label>
<div class="chips rise" id="chips"><button data-k="" class="on">Semua <span id="cs">0</span></button><button data-k="makanan">Makanan</button><button data-k="minuman">Minuman</button><button data-k="snack">Snack</button><button data-k="kritis" class="crit">Kritis <span id="ck">0</span></button></div>
<section class="card rise" id="plist"></section>
<?php nav('products');
foot();
