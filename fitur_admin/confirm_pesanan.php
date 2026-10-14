<?php
// fitur_admin/confirm_pesanan.php
$username = isset($_GET['user']) ? $_GET['user'] : 'Username';

// Contoh data menu
$menus = [
    ['nama' => 'Menu 1', 'qty' => 2, 'checked' => true],
    ['nama' => 'Menu 2', 'qty' => 2, 'checked' => true],
    ['nama' => 'Menu 3', 'qty' => 2, 'checked' => false],
    ['nama' => 'Menu 4', 'qty' => 2, 'checked' => false],
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Confirm Pesanan - Admin</title>
    <link rel="stylesheet" href="../asset/style.css">
</head>
<body class="admin-detail-bg">
    <div class="admin-detail-page">

        <!-- Header -->
        <div class="admin-detail-header">
            <a href="pesanan_selesai.php" class="admin-icon-btn" aria-label="Kembali">
                <svg viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M15 6l-6 6 6 6" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </a>
            <div class="admin-detail-userinfo">
                <span class="admin-detail-username"><?= htmlspecialchars($username) ?></span>
                <span class="admin-detail-time">
                    <span>DD/MM/YY</span>
                    <span>00:00 WIB</span>
                </span>
            </div>
            <button type="button" class="admin-confirm-btn" id="openConfirm">confirm</button>
        </div>

        <!-- Kartu putih: upload foto makanan -->
        <div class="admin-detail-body">
            <div class="admin-bukti-box">
                <p class="admin-bukti-label">upload foto makanan</p>
                <span class="admin-bukti-icon">📷</span>
            </div>
        </div>

        <!-- Baris tanggal & jam -->
        <div class="admin-datetime-row">
            <span class="admin-datetime-box">DD/MM/YY</span>
            <span class="admin-datetime-box">00.00 WIB</span>
        </div>

        <!-- Daftar menu dengan checkbox -->
        <div class="admin-menu-list">
            <?php foreach ($menus as $m): ?>
                <label class="admin-menu-item">
                    <span class="admin-menu-check <?= $m['checked'] ? 'checked' : '' ?>">
                        <?php if ($m['checked']): ?>
                            <svg viewBox="0 0 24 24" aria-hidden="true">
                                <path d="M5 12l5 5L20 7" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        <?php endif; ?>
                    </span>
                    <span class="admin-menu-name"><?= htmlspecialchars($m['nama']) ?></span>
                    <span class="admin-menu-qty"><?= $m['qty'] ?> pcs</span>
                    <input type="checkbox" hidden <?= $m['checked'] ? 'checked' : '' ?>>
                </label>
            <?php endforeach; ?>
        </div>

    </div>

    <!-- Popup Konfirmasi -->
    <div class="admin-popup-overlay" id="popupOverlay"></div>
    <div class="admin-popup" id="popup" aria-hidden="true">
        <div class="admin-popup-card">
            <button type="button" class="admin-popup-back" id="closePopup" aria-label="Kembali">
                <svg viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M15 6l-6 6 6 6" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </button>

            <div class="admin-popup-body">
                <p class="admin-popup-question">
                    Apakah Anda sudah yakin telah memeriksa foto dan menu yang sesuai dengan orderan ?
                </p>

                <label class="admin-popup-check">
                    <input type="checkbox" id="chk1">
                    <span>Jenis dan jumlah menu sudah sesuai</span>
                </label>

                <label class="admin-popup-check">
                    <input type="checkbox" id="chk2">
                    <span>Foto yang di upload sudah sesuai</span>
                </label>
            </div>

            <button type="button" class="admin-popup-confirm" id="confirmAction">confirm</button>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const popup = document.getElementById('popup');
            const overlay = document.getElementById('popupOverlay');
            const openBtn = document.getElementById('openConfirm');
            const closeBtn = document.getElementById('popupClose') || document.getElementById('closePopup');
            const confirmBtn = document.getElementById('confirmAction');

            function openPopup() {
                overlay.classList.add('open');
                popup.classList.add('open');
                popup.setAttribute('aria-hidden', 'false');
            }

            function closePopup() {
                overlay.classList.remove('open');
                popup.classList.remove('open');
                popup.setAttribute('aria-hidden', 'true');
            }

            openBtn.addEventListener('click', openPopup);
            closeBtn.addEventListener('click', closePopup);
            overlay.addEventListener('click', closePopup);

            confirmBtn.addEventListener('click', function () {
                const c1 = document.getElementById('chk1').checked;
                const c2 = document.getElementById('chk2').checked;

                if (!c1 || !c2) {
                    alert('Harap centang kedua persetujuan terlebih dahulu.');
                    return;
                }

                alert('Pesanan berhasil dikonfirmasi!');
                window.location.href = 'pesanan_selesai.php';
            });

            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape') closePopup();
            });

            // Toggle checkmark saat klik menu
            document.querySelectorAll('.admin-menu-item').forEach(function (item) {
                item.addEventListener('click', function () {
                    const check = item.querySelector('.admin-menu-check');
                    const input = item.querySelector('input[type="checkbox"]');
                    input.checked = !input.checked;
                    check.classList.toggle('checked', input.checked);

                    if (input.checked) {
                        check.innerHTML = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12l5 5L20 7" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/></svg>';
                    } else {
                        check.innerHTML = '';
                    }
                });
            });
        });
    </script>
</body>
</html>