<?php
// riwayat.php

$orders = [
    [
        'id' => 'SN-0003',
        'status' => 'diproses',
        'tanggal' => '10 Okt 2026',
        'jam' => '11:00 WIB',
        'menus' => [
            ['nama' => 'Mie Pasta Merah', 'desc' => '4 porsi x 25.000', 'harga' => 100000, 'emoji' => '🍝'],
        ],
        'total' => 320000,
        'jadwal_tanggal' => 'Sel, 10 Okt 2026',
        'jadwal_jam' => '11:00 WIB',
    ],
    [
        'id' => 'SN-0002',
        'status' => 'selesai',
        'tanggal' => '10 Okt 2026',
        'jam' => '11:00 WIB',
        'menus' => [
            ['nama' => 'Mie Pasta Merah', 'desc' => '4 porsi x 25.000', 'harga' => 100000, 'emoji' => '🍝'],
        ],
        'total' => 320000,
        'jadwal_tanggal' => 'Sel, 10 Okt 2026',
        'jadwal_jam' => '11:00 WIB',
    ],
    [
        'id' => 'SN-0001',
        'status' => 'diproses',
        'tanggal' => '10 Okt 2026',
        'jam' => '11:00 WIB',
        'menus' => [
            ['nama' => 'Mie Pasta Merah', 'desc' => '4 porsi x 25.000', 'harga' => 100000, 'emoji' => '🍝'],
            ['nama' => 'Rat Crispy', 'desc' => '4 porsi x 50.000', 'harga' => 200000, 'emoji' => '🍚'],
        ],
        'total' => 300000,
        'jadwal_tanggal' => 'Sab, 10 Okt 2026',
        'jadwal_jam' => '11:00 WIB',
    ],
];

function rupiah($n) { return 'Rp ' . number_format($n, 0, ',', '.'); }

function status_label($status) {
    switch ($status) {
        case 'diproses': return 'DIPROSES';
        case 'selesai':  return 'SELESAI';
        case 'menunggu': return 'Menunggu Verifikasi';
        default:         return 'DIPROSES';
    }
}

function status_class($status) {
    switch ($status) {
        case 'diproses': return 'status-diproses';
        case 'selesai':  return 'status-selesai';
        case 'menunggu': return 'status-menunggu';
        default:         return 'status-diproses';
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Riwayat Pesanan - Se.Noesa</title>
    <link rel="stylesheet" href="asset/style.css">
</head>
<body class="riwayat-bg">
    <div class="riwayat-page">

        <!-- Header hijau -->
        <header class="riwayat-header">
            <a href="pesanan.php" class="riwayat-back" aria-label="Kembali">
                <svg viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M15 6l-6 6 6 6" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </a>
            <h1 class="riwayat-title">Riwayat Pesanan</h1>
            <a href="#" class="riwayat-avatar" aria-label="Profil">
                <svg viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20zm0 3a3 3 0 1 1 0 6 3 3 0 0 1 0-6zm0 14.2a7.2 7.2 0 0 1-6-3.2c.03-2 4-3.1 6-3.1s5.97 1.1 6 3.1a7.2 7.2 0 0 1-6 3.2z" fill="currentColor"/>
                </svg>
            </a>
        </header>

        <!-- Tab filter -->
        <nav class="riwayat-tabs">
            <button type="button" class="rtab active" data-filter="semua">Semua</button>
            <button type="button" class="rtab" data-filter="diproses">Diproses</button>
            <button type="button" class="rtab" data-filter="selesai">Selesai</button>
            <button type="button" class="rtab" data-filter="menunggu">Menunggu Verifikasi</button>
        </nav>

        <!-- Daftar pesanan -->
        <div class="riwayat-list" id="riwayatList">

            <?php foreach ($orders as $o): ?>
                <article class="order-card" data-status="<?= $o['status'] ?>">
                    <div class="order-head">
                        <div>
                            <h3 class="order-id"><?= $o['id'] ?></h3>
                            <p class="order-date"><?= $o['tanggal'] ?> | <?= $o['jam'] ?></p>
                        </div>
                        <span class="order-badge <?= status_class($o['status']) ?>">
                            <?= status_label($o['status']) ?>
                        </span>
                    </div>

                    <div class="order-body">
                        <div class="order-photo" data-emoji="<?= $o['menus'][0]['emoji'] ?>">
                            <?= $o['menus'][0]['emoji'] ?>
                        </div>
                        <div class="order-info">
                            <h4 class="order-menu-name"><?= $o['menus'][0]['nama'] ?></h4>
                            <p class="order-menu-desc"><?= $o['menus'][0]['desc'] ?></p>
                        </div>
                    </div>

                    <div class="order-foot">
                        <p class="order-total"><?= rupiah($o['total']) ?></p>
                        <?php if ($o['status'] === 'selesai'): ?>
                            <button type="button" class="order-again">Pesan Lagi</button>
                        <?php endif; ?>
                    </div>

                    <!-- Trigger untuk popup -->
                    <button type="button" class="order-open" data-id="<?= $o['id'] ?>" aria-label="Lihat detail"></button>
                </article>
            <?php endforeach; ?>

            <!-- Empty state -->
            <div class="riwayat-empty" id="riwayatEmpty" style="display: none;">
                <p class="empty-text">Belum Ada Pesanan Menunggu Verifikasi</p>
            </div>
        </div>

        <!-- Popup detail -->
        <div class="popup-overlay" id="popupOverlay"></div>
        <div class="popup" id="popup" aria-hidden="true">
            <div class="popup-card">

                <!-- Drag handle -->
                <div class="popup-handle"></div>

                <div class="popup-head">
                    <div>
                        <h3 class="popup-id" id="popupId">SN-0001</h3>
                        <span class="popup-status" id="popupStatus">Diproses</span>
                    </div>
                    <button type="button" class="popup-close" id="popupClose" aria-label="Tutup">
                        <svg viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M6 6l12 12M18 6l-12 12" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"/>
                        </svg>
                    </button>
                </div>

                <!-- Progress step -->
                <div class="popup-steps">
                    <div class="pstep done"><span class="pdot"></span><span class="plabel">PESAN</span></div>
                    <div class="pstep done"><span class="pdot"></span><span class="plabel">VERIFIKASI</span></div>
                    <div class="pstep done"><span class="pdot"></span><span class="plabel">DIPROSES</span></div>
                    <div class="pstep"><span class="pdot"></span><span class="plabel">SELESAI</span></div>
                </div>

                <!-- Daftar pesanan -->
                <h4 class="popup-section-title">PESANAN</h4>
                <div class="popup-items" id="popupItems"></div>

                <!-- Jadwal kedatangan -->
                <h4 class="popup-section-title">JADWAL KEDATANGAN</h4>
                <div class="popup-schedule">
                    <div class="sched-box">
                        <span class="sched-label">Tanggal</span>
                        <span class="sched-value" id="popupTanggal">Sel, 10 Okt 2026</span>
                    </div>
                    <div class="sched-box">
                        <span class="sched-label">Waktu</span>
                        <span class="sched-value" id="popupJam">11:00 WIB</span>
                    </div>
                </div>

                <!-- Ringkasan -->
                <h4 class="popup-section-title">RINGKASAN</h4>
                <div class="popup-summary">
                    <p class="summary-line">
                        <span>Dipesan pada</span>
                        <span id="popupDipesan">8 Okt 2026</span>
                    </p>
                    <p class="summary-line summary-total">
                        <span>Total</span>
                        <span id="popupTotal">300.000</span>
                    </p>
                </div>

                <!-- Tombol Pesan Lagi (muncul hanya jika status selesai) -->
                <button type="button" class="popup-order-again" id="popupOrderAgain" style="display: none;">
                    Pesan Lagi
                </button>
            </div>
        </div>

    </div>

    <script>
        const ORDERS = <?= json_encode($orders) ?>;

        document.addEventListener('DOMContentLoaded', function () {

            // ---- Filter tabs ----
            const tabs = document.querySelectorAll('.rtab');
            const cards = document.querySelectorAll('.order-card');
            const empty = document.getElementById('riwayatEmpty');

            tabs.forEach(function (tab) {
                tab.addEventListener('click', function () {
                    tabs.forEach(t => t.classList.remove('active'));
                    tab.classList.add('active');

                    const filter = tab.dataset.filter;
                    let visible = 0;

                    cards.forEach(function (card) {
                        const show = (filter === 'semua') || (card.dataset.status === filter);
                        card.style.display = show ? '' : 'none';
                        if (show) visible++;
                    });

                    if (filter === 'menunggu' && visible === 0) {
                        empty.style.display = 'block';
                        empty.querySelector('.empty-text').textContent = 'Belum Ada Pesanan Menunggu Verifikasi';
                    } else {
                        empty.style.display = 'none';
                    }
                });
            });

            // ---- Popup detail ----
            const popup = document.getElementById('popup');
            const overlay = document.getElementById('popupOverlay');
            const popupAgainBtn = document.getElementById('popupOrderAgain');

            function openPopup(id) {
                const order = ORDERS.find(o => o.id === id);
                if (!order) return;

                document.getElementById('popupId').textContent = order.id;
                document.getElementById('popupStatus').textContent =
                    order.status === 'diproses' ? 'Diproses' :
                    order.status === 'selesai' ? 'Selesai' : 'Menunggu Verifikasi';

                // Daftar menu
                let items = '';
                order.menus.forEach(function (m) {
                    items +=
                        '<div class="popup-item">' +
                            '<div>' +
                                '<p class="pi-name">' + m.nama.toUpperCase() + '</p>' +
                                '<p class="pi-desc">' + m.desc + '</p>' +
                            '</div>' +
                            '<span class="pi-price">' + Number(m.harga).toLocaleString('id-ID') + '</span>' +
                        '</div>';
                });
                document.getElementById('popupItems').innerHTML = items;

                document.getElementById('popupTanggal').textContent = order.jadwal_tanggal;
                document.getElementById('popupJam').textContent = order.jadwal_jam;
                document.getElementById('popupTotal').textContent = Number(order.total).toLocaleString('id-ID');

                // Tampilkan tombol "Pesan Lagi" hanya jika status SELESAI
                if (order.status === 'selesai') {
                    popupAgainBtn.style.display = 'block';
                } else {
                    popupAgainBtn.style.display = 'none';
                }

                overlay.classList.add('open');
                popup.classList.add('open');
                popup.setAttribute('aria-hidden', 'false');
            }

            function closePopup() {
                overlay.classList.remove('open');
                popup.classList.remove('open');
                popup.setAttribute('aria-hidden', 'true');
            }

            document.querySelectorAll('.order-open').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    openPopup(btn.dataset.id);
                });
            });

            // Tombol "Pesan Lagi" di dalam popup
            popupAgainBtn.addEventListener('click', function () {
                window.location.href = 'pesanan.php';
            });

            // Tombol "Pesan Lagi" di card
            document.querySelectorAll('.order-again').forEach(function (btn) {
                btn.addEventListener('click', function (e) {
                    e.stopPropagation();
                    window.location.href = 'pesanan.php';
                });
            });

            document.getElementById('popupClose').addEventListener('click', closePopup);
            overlay.addEventListener('click', closePopup);

            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape') closePopup();
            });
        });
    </script>
</body>
</html>