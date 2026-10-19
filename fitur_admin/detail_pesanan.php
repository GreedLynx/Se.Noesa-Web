<?php
// fitur_admin/detail_pesanan.php
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail Pesanan - Se.Noesa</title>
    <link rel="stylesheet" href="../asset/style.css">
</head>
<body class="admin-order-list-bg">
    <div class="ao-container">

        <!-- ============ HEADER ============ -->
        <div class="ao-header">
            <div class="ao-brand">
                <span class="ao-subtitle">Se.Noesa</span>
                <h1 class="ao-title">Detail Pesanan</h1>
            </div>
            <div class="ao-profile">
                <button class="ao-icon-btn" id="bellBtn" onclick="toggleNotif()">
                    🔔<span class="notif-dot" id="notifDot"></span>
                </button>
                <a href="../login.php" class="ao-avatar">A</a>
            </div>
        </div>

        <!-- Tombol Back -->
        <a href="proses_selesai.php" class="btn-back-inline">&#10094; Kembali</a>

        <!-- ============ ORDER INFO ============ -->
        <div class="dp-info-row">
            <div>
                <span class="dp-label-small">Order Pesanan</span>
                <span class="dp-order-id">#CAT-8840</span>
            </div>
            <span class="dp-status-badge">Diproses</span>
        </div>

        <!-- ============ PEMESAN ============ -->
        <div class="dp-pemesan-row">
            <span class="dp-label-small">Pemesan</span>
            <div class="dp-pemesan-info">
                <span class="dp-pemesan-avatar">👤</span>
                <span class="dp-pemesan-name">Andri Pratama</span>
            </div>
        </div>

        <!-- ============ BUKTI TRANSFER PELANGGAN (KLIK UNTUK POPUP) ============ -->
        <h3 class="val-section-title">Bukti Pesanan Pelanggan</h3>
        <div class="dp-bukti-box" onclick="openUploadPopup()">
            <span class="dp-bukti-icon" id="dp-bukti-icon">🖼️</span>
            <p class="dp-bukti-text" id="dp-bukti-text">Foto bukti pesanan</p>
            <img id="dp-bukti-preview" src="" alt="Preview" style="display: none;">
        </div>

        <!-- ============ DAFTAR MENU ============ -->
        <h3 class="val-section-title">Daftar Menu <span class="badge-status">3 Jenis</span></h3>
        <div class="dp-menu-list">
            <div class="dp-menu-item">
                <div class="dp-menu-check"></div>
                <div class="dp-menu-info">
                    <span class="dp-menu-name">Paket Nasi Kotak Ayam</span>
                    <span class="dp-menu-qty">30.000 x 100 porsi</span>
                </div>
                <span class="dp-menu-price">Rp 3.000.000</span>
            </div>
            <div class="dp-menu-item">
                <div class="dp-menu-check"></div>
                <div class="dp-menu-info">
                    <span class="dp-menu-name">Snack Box Coffee Break</span>
                    <span class="dp-menu-qty">5.000 x 100 porsi</span>
                </div>
                <span class="dp-menu-price">Rp 500.000</span>
            </div>
        </div>

        <!-- ============ JADWAL ============ -->
        <h3 class="val-section-title">Jadwal</h3>
        <div class="dp-jadwal-box">
            <div class="dp-jadwal-row">
                <span class="dp-jadwal-label">Tanggal & Waktu Pemesanan</span>
                <span class="dp-jadwal-value">18 Okt 2026, 10:32 WIB</span>
            </div>
            <div class="dp-jadwal-row">
                <span class="dp-jadwal-label">Tanggal & Waktu Pengiriman</span>
                <span class="dp-jadwal-value">18 Okt 2026, 11:30 WIB</span>
            </div>
        </div>

        <!-- ============ TOMBOL SELESAI ============ -->
        <button class="dp-btn-selesai" onclick="alert('Pesanan selesai dan siap dikirim!')">✓ Selesai dan Kirim</button>

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
        // ====== FUNGSI POPUP UPLOAD ======
        function openUploadPopup() {
            document.getElementById('uploadOverlay').classList.add('open');
            document.getElementById('uploadPopup').classList.add('open');
        }
        
        function closeUploadPopup() {
            document.getElementById('uploadOverlay').classList.remove('open');
            document.getElementById('uploadPopup').classList.remove('open');
        }
        
        function confirmUpload() {
            const preview = document.getElementById('popup-preview');
            const targetPreview = document.getElementById('dp-bukti-preview');
            const targetIcon = document.getElementById('dp-bukti-icon');
            const targetText = document.getElementById('dp-bukti-text');
            
            // Jika ada gambar yang diupload di popup, pindahkan ke kotak utama
            if (preview && preview.src && preview.style.display !== 'none') {
                targetPreview.src = preview.src;
                targetPreview.style.display = 'block';
                targetIcon.style.display = 'none';
                targetText.style.display = 'none';
            }
            
            alert('Foto berhasil dikonfirmasi!');
            closeUploadPopup();
        }
        
        // Preview gambar di dalam popup
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