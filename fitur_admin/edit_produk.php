<?php
// fitur_admin/edit_produk.php
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Produk - Se.Noesa</title>
    <link rel="stylesheet" href="../asset/style.css">
</head>
<body class="admin-order-list-bg">
    <div class="ao-container">

        <!-- Header -->
        <div class="ao-header">
            <div class="ao-brand" style="flex-direction: row; align-items: center; gap: 10px;">
                <a href="produk.php" class="btn-back-circle">&#10094;</a>
                <div style="display: flex; flex-direction: column;">
                    <span class="ao-subtitle">Se.Noesa</span>
                    <h1 class="ao-title" style="font-size: 22px;">Edit Produk</h1>
                </div>
            </div>
            <div class="ao-profile">
                <button class="ao-icon-btn" id="bellBtn" onclick="toggleNotif()">
                    🔔<span class="notif-dot" id="notifDot"></span>
                </button>
                <a href="../login.php" class="ao-avatar">A</a>
            </div>
        </div>

        <div class="ep-form-container">
            <!-- Foto Produk -->
            <div class="ep-group">
                <div class="ep-label-row">
                    <span class="ep-label">Foto Produk</span>
                    <span class="ep-badge-text" onclick="openUploadPopup()">Ganti Foto</span>
                </div>
                <div class="ep-photo-box" onclick="openUploadPopup()">
                    <span class="ep-photo-icon" id="ep-photo-icon">🖼️</span>
                    <p class="ep-photo-text" id="ep-photo-text">Foto produk saat ini</p>
                    <img id="ep-photo-preview" src="" alt="Preview" style="display: none;">
                </div>
            </div>

            <!-- Nama Menu -->
            <div class="ep-group">
                <label class="ep-label">Nama Menu</label>
                <input type="text" class="ep-input" value="Paket Nasi Kotak Ayam">
            </div>

            <!-- Harga -->
            <div class="ep-group">
                <label class="ep-label">Harga (Rp)</label>
                <input type="number" class="ep-input" value="42000">
            </div>

            <!-- Deskripsi -->
            <div class="ep-group">
                <label class="ep-label">Deskripsi Menu</label>
                <textarea class="ep-input ep-textarea">Nasi kotak ayam bakar dengan sambal terpisah, lalapan segar, dan kerupuk. Cocok untuk acara kantor.</textarea>
            </div>

            <!-- Ubah Kuota -->
            <div class="ep-group">
                <label class="ep-label">Ubah Kuota</label>
                <div class="ep-kuota-row">
                    <div class="ep-kuota-col">
                        <span class="ep-sub-label">Batas ambil tanggal</span>
                        <input type="date" class="ep-input" value="2026-10-16">
                    </div>
                    <div class="ep-kuota-col">
                        <span class="ep-sub-label">Kuota baru</span>
                        <input type="number" class="ep-input" value="150">
                    </div>
                </div>
            </div>

            <!-- Tombol Konfirmasi -->
            <button class="ep-btn-confirm" onclick="alert('Perubahan berhasil disimpan!')">✓ Konfirmasi Perubahan</button>
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
                    <p class="upload-popup-text" id="popupText">Upload Foto Produk</p>
                    <img id="popup-preview" src="" alt="Preview" style="display: none;">
                </label>
                <input type="file" id="popup-file-upload" accept="image/*" hidden>
            </div>
            <button class="upload-popup-confirm" onclick="confirmUpload()">✓ Konfirmasi Perubahan</button>
        </div>
    </div>

    <script src="../asset/script.js"></script>
    <script>
        // ====== LOGIKA POPUP UPLOAD ======
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
            const targetPreview = document.getElementById('ep-photo-preview');
            const targetIcon = document.getElementById('ep-photo-icon');
            const targetText = document.getElementById('ep-photo-text');
            
            if (preview && preview.src && preview.style.display !== 'none') {
                targetPreview.src = preview.src;
                targetPreview.style.display = 'block';
                targetIcon.style.display = 'none';
                targetText.style.display = 'none';
            }
            alert('Foto produk berhasil diganti!');
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