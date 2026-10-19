<?php
// pesanan.php

$kategori = [
    'makanan' => [
        'label' => 'Makanan',
        'icon'  => '<circle cx="12" cy="12" r="6"/><circle cx="12" cy="12" r="3.4"/><path d="M3.5 5v14M20.5 5v14"/>',
    ],
    'minuman' => [
        'label' => 'Minuman',
        'icon'  => '<path d="M6 8h12l-1.2 12H7.2L6 8z"/><path d="M5 8h14"/><path d="M13 8l2-5"/>',
    ],
    'snack' => [
        'label' => 'Snack',
        'icon'  => '<rect x="2.5" y="9" width="19" height="6.5" rx="3.2" transform="rotate(-35 12 12)"/><path d="M8 7l8 9M11 5.5l7.5 8"/>',
    ],
];

$menu = [
    'makanan' => [
        ['id' => 'mie-pasta-merah', 'nama' => 'Mie Pasta Merah', 'desk' => 'lorem ipsum dolor sit amet', 'harga' => 25000, 'foto' => 'foto/menu/mie-pasta-merah.png', 'emoji' => '🍝', 'min' => 4, 'sisa' => 46, 'total' => 50],
        ['id' => 'rat-crispy',      'nama' => 'Rat Crispy',      'desk' => 'lorem ipsum dolor sit amet', 'harga' => 50000, 'foto' => 'foto/menu/rat-crispy.png',      'emoji' => '🍚', 'min' => 4, 'sisa' => 46, 'total' => 50],
    ],
    'minuman' => [
        ['id' => 'matcha-latte',    'nama' => 'Matcha Latte',    'desk' => 'lorem ipsum dolor sit amet', 'harga' => 13000, 'foto' => 'foto/menu/matcha-latte.png',    'emoji' => '🍵', 'min' => 4, 'sisa' => 46, 'total' => 50],
        ['id' => 'es-merah-darah',  'nama' => 'Es Merah Darah',  'desk' => 'lorem ipsum dolor sit amet', 'harga' => 8000,  'foto' => 'foto/menu/es-merah-darah.png',  'emoji' => '🥤', 'min' => 4, 'sisa' => 46, 'total' => 50],
    ],
    'snack' => [
        ['id' => 'udang-goreng',    'nama' => 'Udang Goreng',    'desk' => 'lorem ipsum dolor sit amet', 'harga' => 15000, 'foto' => 'foto/menu/udang-goreng.png',    'emoji' => '🍤', 'min' => 4, 'sisa' => 46, 'total' => 50],
        ['id' => 'french-fries',    'nama' => 'French Fries',    'desk' => 'lorem ipsum dolor sit amet', 'harga' => 13000, 'foto' => 'foto/menu/french-fries.png',    'emoji' => '🍟', 'min' => 4, 'sisa' => 46, 'total' => 50],
    ],
];

function e($s) {
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pesanan - SE.Noesa</title>
    <link rel="stylesheet" href="asset/style.css">
</head>
<body class="order-bg">
    <div class="order-app">

        <aside class="order-rail">
            <a href="riwayat.php" class="order-avatar" aria-label="Riwayat pesanan">
                <svg viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20zm0 3a3 3 0 1 1 0 6 3 3 0 0 1 0-6zm0 14.2a7.2 7.2 0 0 1-6-3.2c.03-2 4-3.1 6-3.1s5.97 1.1 6 3.1a7.2 7.2 0 0 1-6 3.2z"/>
                </svg>
            </a>

            <nav class="rail-nav">
                <?php $i = 0; foreach ($kategori as $key => $k): ?>
                    <button type="button" class="rail-item<?= $i === 0 ? ' active' : '' ?>" data-cat="<?= e($key) ?>" aria-label="<?= e($k['label']) ?>">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><?= $k['icon'] ?></svg>
                    </button>
                <?php $i++; endforeach; ?>
            </nav>

            <svg class="rail-leaf" viewBox="0 0 40 130" aria-hidden="true">
                <path fill="#26302a" d="M22 2C40 30 30 60 20 85 14 100 10 112 6 126 4 100 14 80 22 55 28 35 24 18 22 2z"/>
                <rect x="14" y="118" width="9" height="9" fill="#6b3d1f" transform="rotate(45 18.5 122.5)"/>
            </svg>
        </aside>

        <main class="order-main" id="orderMain">
            <header class="order-head">
                <h1 class="order-title">Select</h1>
                <p class="order-sub">Your Choices</p>
            </header>

            <div class="order-search">
                <input type="text" id="searchInput" placeholder="Search" autocomplete="off">
                <button type="button" aria-label="Cari">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M20 20l-4-4"/></svg>
                </button>
            </div>

            <?php $i = 0; foreach ($menu as $key => $produk): ?>
                <section class="menu-list<?= $i === 0 ? ' active' : '' ?>" data-cat="<?= e($key) ?>">
                    <?php foreach ($produk as $p): ?>
                        <article class="menu-item"
                                 data-id="<?= e($p['id']) ?>"
                                 data-nama="<?= e($p['nama']) ?>"
                                 data-desk="<?= e($p['desk']) ?>"
                                 data-harga="<?= (int) $p['harga'] ?>"
                                 data-min="<?= (int) $p['min'] ?>"
                                 data-sisa="<?= (int) $p['sisa'] ?>"
                                 data-total="<?= (int) $p['total'] ?>"
                                 data-foto="<?= e($p['foto']) ?>"
                                 data-emoji="<?= e($p['emoji']) ?>">
                            <button type="button" class="menu-photo photo" data-emoji="<?= e($p['emoji']) ?>" aria-label="Lihat detail <?= e($p['nama']) ?>">
                                <img src="<?= e($p['foto']) ?>" alt="<?= e($p['nama']) ?>" onerror="this.parentNode.classList.add('no-img')">
                            </button>
                            <div class="menu-row">
                                <div class="menu-meta">
                                    <h3 class="menu-name"><?= e($p['nama']) ?></h3>
                                    <p class="menu-desc"><?= e($p['desk']) ?></p>
                                    <p class="menu-price">Rp <?= number_format($p['harga'], 0, ',', '.') ?></p>
                                </div>
                                <button type="button" class="menu-add" aria-label="Pilih <?= e($p['nama']) ?>">
                                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
                                </button>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </section>
            <?php $i++; endforeach; ?>

            <p class="menu-empty" id="menuEmpty">Menu tidak ditemukan</p>
        </main>

        <div class="cart" id="cart">
            <button type="button" class="cart-head" id="cartHead" aria-expanded="false">
                <span class="cart-count" id="cartCount">0</span>
                <span class="cart-label">Total Biaya</span>
                <span class="cart-total" id="cartTotal">0</span>
                <svg class="cart-chevron" viewBox="0 0 24 24" aria-hidden="true"><path d="M6 15l6-6 6 6"/></svg>
            </button>
            <div class="cart-body">
                <div class="cart-items" id="cartItems"></div>
                <a href="pembayaran.php" class="cart-pay" id="cartPay" style="text-align:center; text-decoration:none;">Bayar | 0</a>
            </div>
        </div>

        <div class="sheet-overlay" id="sheetOverlay"></div>
        <div class="sheet" id="sheet" role="dialog" aria-modal="true" aria-hidden="true" aria-label="Detail produk">
            <div class="sheet-hero">
                <div class="sheet-info">
                    <h2 class="sheet-name" id="sheetName"></h2>
                    <p class="sheet-desc-title">Description</p>
                    <p class="sheet-desc" id="sheetDesc"></p>
                    <hr class="sheet-line">
                    <div class="sheet-stats">
                        <div class="sheet-stat"><span>Min. Pesan</span><b id="statMin">-</b></div>
                        <div class="sheet-stat"><span>Jumlah</span><b id="statJumlah">-</b></div>
                        <div class="sheet-stat"><span>Sisa Porsi</span><b id="statSisa">-</b></div>
                    </div>
                </div>
                <div class="sheet-photo photo" id="sheetPhoto">
                    <img id="sheetImg" alt="">
                </div>
            </div>

            <div class="sheet-qty">
                <span>Atur jumlah porsi</span>
                <div class="stepper">
                    <button type="button" class="step-minus" id="qtyMinus" aria-label="Kurangi">&minus;</button>
                    <output id="qtyVal">0</output>
                    <button type="button" class="step-plus" id="qtyPlus" aria-label="Tambah">+</button>
                </div>
            </div>

            <button type="button" class="sheet-order" id="sheetOrder">Pesan Sekarang</button>
            <button type="button" class="sheet-remove" id="sheetRemove" hidden>Hapus dari pesanan</button>
        </div>

    </div>

    <script src="asset/script.js"></script>
</body>
</html>