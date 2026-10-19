<?php
// fitur_admin/validasi_qris.php
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Validasi Bukti QRIS - Se.Noesa</title>
    <link rel="stylesheet" href="../asset/style.css">
</head>
<body class="admin-order-list-bg">
    <div class="ao-container">

        <!-- Header -->
        <div class="ao-header">
            <div class="ao-brand">
                <span class="ao-subtitle">Se.Noesa</span>
                <h1 class="ao-title">Validasi QRIS</h1>
            </div>
            <div class="ao-profile">
                <button class="ao-icon-btn" id="bellBtn" onclick="toggleNotif()">
                    🔔<span class="notif-dot" id="notifDot"></span>
                </button>
                <a href="../login.php" class="ao-avatar">A</a>
            </div>
        </div>

        <!-- Tombol Back -->
        <a href="daftar_verifikasi.php" class="btn-back-inline">&#10094; Kembali</a>

        <!-- Info Pesanan Card -->
        <div class="val-card">
            <div class="val-card-top">
                <span class="val-order-id">#CAT-8840</span>
                <span class="val-order-time">10:32 WIB</span>
            </div>
            <div class="val-card-body">
                <span class="val-name">Andri Pratama</span>
                <span class="val-desc">150 Pcs Gathering Kantor</span>
                <span class="val-price">Rp 3.500.000</span>
            </div>
        </div>

        <!-- Bukti Transfer Section -->
        <h3 class="val-section-title">Bukti Transfer Pelanggan <span class="badge-status">QRIS Status</span></h3>
        <div class="val-upload-box" onclick="openUploadPopup()">
            <span class="val-upload-icon">🖼️</span>
            <p class="val-upload-text">Screenshot bukti QRIS dari pelanggan</p>
        </div>

        <!-- Data pada Struk -->
        <h3 class="val-section-title">Data pada Struk <span class="badge-status">Nominal sesuai</span></h3>
        <div class="val-struk-list">
            <div class="val-struk-row">
                <span class="val-struk-label">Nama pengirim</span>
                <span class="val-struk-value">ANDRI PRATAMA (Liviri Mandiri)</span>
            </div>
            <div class="val-struk-row">
                <span class="val-struk-label">No. referensi (RRN)</span>
                <span class="val-struk-value">902857482910</span>
            </div>
            <div class="val-struk-row">
                <span class="val-struk-label">Nominal di struk</span>
                <span class="val-struk-value">Rp 3.500.000</span>
            </div>
            <div class="val-struk-row">
                <span class="val-struk-label">Total tagihan</span>
                <span class="val-struk-value">Rp 3.500.000</span>
            </div>
        </div>

        <!-- Alamat Tujuan Pengiriman -->
        <h3 class="val-section-title">Alamat Tujuan Pengiriman</h3>
        <div class="val-alamat-box">
            <div class="val-alamat-icon">📍</div>
            <div class="val-alamat-text">
                <span class="val-alamat-name">Andri Pratama - 0812-3456-7890</span>
                <span class="val-alamat-desc">Ruang Rapat Lt. 4, Manara Mandiri, Ngawi</span>
                <span class="val-alamat-desc">Kirim hari ini, 11:15 WIB</span>
            </div>
        </div>

        <!-- Tombol Aksi -->
        <div class="val-actions">
            <button class="val-btn-outline" onclick="alert('Pesanan ditolak!')">✕ Tolak</button>
            <button class="val-btn-fill" onclick="window.location.href='proses_selesai.php'">✓ Konfirmasi Pembayaran</button>
        </div>

    </div>

    <!-- ============ POPUP UPLOAD FOTO ============ -->
    <div class="upload-popup-overlay" id="uploadOverlay" onclick="closeUploadPopup()"></div>
    <div class="upload-popup" id="uploadPopup">
        <div class="upload-popup-card">
            <div class="upload-popup-header">
                <h3>Upload Foto</h3>
                <button class="upload-popup-close" onclick="closeUploadPopup()">✕</button>
            </div>
            <div class="upload-popup-body">
                <label for="popup-file-upload" class="upload-popup-area">
                    <span class="upload-popup-icon" id="popupIcon">🖼️</span>
                    <p class="upload-popup-text" id="popupText">Upload Foto Pesanan</p>
                    <img id="popup-preview" src="" alt="Preview" style="display: none;">
                </label>
                <input type="file" id="popup-file-upload" accept="image/*" hidden>
            </div>
            <button class="upload-popup-confirm" onclick="confirmUpload()">✓ Konfirmasi Foto</button>
        </div>
    </div>

    <script src="../asset/script.js"></script>
    <script>
        function openUploadPopup() {
            document.getElementById('uploadOverlay').classList.add('open');
            document.getElementById('uploadPopup').classList.add('open');
        }
        function closeUploadPopup() {
            document.getElementById('uploadOverlay').classList.remove('open');
            document.getElementById('uploadPopup').classList.remove('open');
        }
        function confirmUpload() {
            alert('Foto berhasil diupload!');
            closeUploadPopup();
        }
        // Preview gambar di popup
        document.addEventListener('DOMContentLoaded', function() {
            const fileInput = document.getElementById('popup-file-upload');
            const preview = document.getElementById('popup-preview');
            const icon = document.getElementById('popupIcon');
            const text = document.getElementById('popupText');
            if (fileInput) {
                fileInput.addEventListener('change', function(e) {
                    const file = e.target.files[0];
                    if (file) {
                        const reader = new FileReader();
                        reader.onload = function(ev) {
                            preview.src = ev.target.result;
                            preview.style.display = 'block';
                            icon.style.display = 'none';
                            text.style.display = 'none';
                        };
                        reader.readAsDataURL(file);
                    }
                });
            }
        });
    </script>
</body>
</html>