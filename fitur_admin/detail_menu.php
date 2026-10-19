<?php
// fitur_admin/detail_menu.php
$id = isset($_GET['id']) ? (int)$_GET['id'] : 1;
$kategori = isset($_GET['kategori']) ? $_GET['kategori'] : 'makanan';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail Menu - Admin</title>
    <link rel="stylesheet" href="../asset/style.css">
</head>
<body class="admin-detail-bg">
    <div class="admin-detail-page">

        <!-- Header -->
        <div class="admin-detail-header">
            <a href="edit_menu_kategori.php?kategori=<?= urlencode($kategori) ?>" class="admin-icon-btn" aria-label="Kembali">
                <svg viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M15 6l-6 6 6 6" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </a>
            <div class="admin-detail-userinfo">
                <span class="admin-detail-username">Nama Menu</span>
            </div>
            <button type="button" class="admin-confirm-btn" id="openConfirm">confirm</button>
        </div>

        <!-- Foto menu -->
        <div class="admin-detail-body">
            <div class="admin-bukti-box">
                <p class="admin-bukti-label">Gambar Menu</p>
                <span class="admin-bukti-icon">📷</span>
            </div>
        </div>

        <!-- Field Nama Menu -->
        <div class="admin-field-box">
            <span class="admin-field-label">Nama Menu</span>
        </div>

        <!-- Field Harga -->
        <div class="admin-field-box">
            <span class="admin-field-label">Harga Menu</span>
        </div>

        <!-- Baris bawah: harga + tombol edit/delete -->
        <div class="admin-menu-foot">
            <span class="admin-menu-price">50.000</span>
            <div class="admin-menu-actions">
                <button type="button" class="admin-mini-btn" aria-label="Edit">
                    <svg viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M4 20h4l10-10-4-4L4 16v4z" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/>
                        <path d="M14 6l4 4" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                    </svg>
                </button>
                <button type="button" class="admin-mini-btn" aria-label="Hapus">
                    <svg viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M5 7h14M9 7V5h6v2M7 7l1 13h8l1-13" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </button>
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
                    Apakah Anda yakin ingin menyimpan perubahan pada menu ini?
                </p>

                <label class="admin-popup-check">
                    <input type="checkbox" id="chk1">
                    <span>Data menu sudah sesuai</span>
                </label>

                <label class="admin-popup-check">
                    <input type="checkbox" id="chk2">
                    <span>Gambar menu sudah diperbarui</span>
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

                alert('Perubahan menu berhasil disimpan!');
                closePopup();
            });

            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape') closePopup();
            });
        });
    </script>
</body>
</html>