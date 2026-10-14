<?php
// admin/detail_pesanan.php
$username = isset($_GET['user']) ? $_GET['user'] : 'Username';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail Pesanan - Admin</title>
    <link rel="stylesheet" href="../asset/style.css">
</head>
<body class="admin-detail-bg">
    <div class="admin-detail-page">

        <!-- Header -->
        <div class="admin-detail-header">
            <a href="daftar_pesanan.php" class="admin-icon-btn" aria-label="Kembali">
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

        <!-- Kartu putih (SS bukti) -->
        <div class="admin-detail-body">
            <div class="admin-bukti-box">
                <p class="admin-bukti-label">SS bukti pembayaran</p>
                <span class="admin-bukti-icon">📷</span>
            </div>
        </div>

        <!-- Alamat tujuan -->
        <div class="admin-detail-alamat">
            <span class="admin-alamat-icon">
                <svg viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M12 22s7-7.5 7-13a7 7 0 1 0-14 0c0 5.5 7 13 7 13z"
                          fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/>
                    <circle cx="12" cy="9" r="2.5" fill="none" stroke="currentColor" stroke-width="1.8"/>
                </svg>
            </span>
            <div class="admin-alamat-text">
                <p class="admin-alamat-title">Alamat</p>
                <p class="admin-alamat-desc">( text max 3 baris )</p>
            </div>
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
                    Apakah Anda yakin sudah memeriksa pembayaran dan alamat tujuan ?
                </p>

                <label class="admin-popup-check">
                    <input type="checkbox" id="chk1">
                    <span>Sudah memastikan dana customer sudah masuk ke rekening</span>
                </label>

                <label class="admin-popup-check">
                    <input type="checkbox" id="chk2">
                    <span>Sudah memastikan alamat dapat ditemukan di google map / dapat dijangkau oleh kurir</span>
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
            const closeBtn = document.getElementById('closePopup');
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
                window.location.href = 'daftar_pesanan.php';
            });

            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape') closePopup();
            });
        });
    </script>
</body>
</html>