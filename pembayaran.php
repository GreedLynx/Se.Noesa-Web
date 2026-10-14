<?php
// pembayaran.php
function e($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pembayaran - SE.Noesa</title>
    <link rel="stylesheet" href="asset/style.css">
</head>
<body class="pay-bg">
    <div class="pay-page">

        <!-- Baris atas: tombol back + judul -->
        <div class="pay-topbar">
            <a href="pesanan.php" class="pay-back" aria-label="Kembali">
                <svg viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M15 6l-6 6 6 6" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </a>
            <h1 class="pay-heading">Payment Your Order</h1>
        </div>

        <!-- Detail Pesanan -->
        <section class="pay-section">
            <h2 class="pay-title">Detail Pesanan</h2>
            <div class="pay-form-box">

                <!-- Alamat Tujuan -->
                <div class="pay-field">
                    <label class="pay-label" for="alamat">Alamat Tujuan</label>
                    <input type="text" id="alamat" placeholder="Masukkan alamat lengkap" required>
                </div>

                <!-- Tanggal -->
                <div class="pay-field">
                    <label class="pay-label" for="tanggal">Tanggal Pengambilan</label>
                    <input type="text" id="tanggal" placeholder="DD/MM/YYYY" onfocus="(this.type='date')" onblur="(this.type='text')" required>
                </div>

                <!-- Jam -->
                <div class="pay-field">
                    <label class="pay-label" for="jam">Jam Pengantaran</label>
                    <select id="jam" required>
                        <option value="" disabled selected>Pilih Jam</option>
                        <?php for ($jam = 7; $jam <= 19; $jam++): ?>
                            <option value="<?= sprintf('%02d:00', $jam) ?>"><?= sprintf('%02d:00', $jam) ?></option>
                        <?php endfor; ?>
                    </select>
                </div>

                <!-- Ringkasan Pesanan -->
                <div class="pay-field">
                    <label class="pay-label" for="ringkasan">Ringkasan Pesanan</label>
                    <input type="text" id="ringkasan" placeholder="Total pax & Total harga" readonly>
                </div>

            </div>
        </section>

        <!-- Pembayaran -->
        <section class="pay-section">
            <h2 class="pay-title">Pembayaran</h2>
            <div class="pay-form-box">
                <p class="pay-subtitle">Metode Pembayaran</p>
                <div class="qr-box">
                    <span>QRIS</span>
                </div>
            </div>
        </section>

        <!-- Bukti Pembayaran -->
        <section class="pay-section">
            <h2 class="pay-title">Bukti Pembayaran</h2>

            <div class="pay-field">
                <label class="pay-label" for="atas-nama">Atas Nama</label>
                <input type="text" class="pay-full-input" id="atas-nama" placeholder="Nama pengirim" required>
            </div>

            <div class="pay-field">
                <label class="pay-label">Upload Bukti Transfer</label>
                <label for="file-upload" class="upload-area">
                    <div class="upload-box">
                        <span class="upload-icon">📷</span>
                        <img id="preview-image" src="" alt="Preview" style="display: none;">
                    </div>
                </label>
                <input type="file" id="file-upload" accept="image/*" hidden>
            </div>
        </section>

        <button type="button" class="confirm-btn" onclick="konfirmasiPesanan()">Confirm</button>
    </div>

    <script src="asset/script.js"></script>
</body>
</html>