<?php
// pesanan.php
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pesanan - SE.Noesa</title>
    <link rel="stylesheet" href="asset/style.css">
</head>
<body>
    <div class="app-container">
        <div class="tabs">
            <a href="pesanan.php" class="tab active">Pesanan</a>
            <a href="riwayat.php" class="tab" id="tab-riwayat" style="display: none;">Riwayat Pesanan</a>
        </div>

        <!-- kategori menu -->
        <div class="categories">
            <a href="#makanan" class="cat-btn">Makanan</a>
            <a href="#minuman" class="cat-btn">Minuman</a>
        </div>

        <!-- section makanan -->
        <div id="makanan" class="menu-section">
            <h2 class="section-title">Makanan</h2>
            <div class="product-list">
                <div class="product-card">
                    <img src="https://via.placeholder.com/80" alt="Ayam Sambel Ijo">
                    <div class="product-info">
                        <h3>Ayam Sambel Ijo</h3>
                        <p class="price">Rp 15.000,-</p>
                        <p class="stock">-/50</p>
                    </div>
                </div>

                <div class="product-card">
                    <img src="https://via.placeholder.com/80" alt="Ayam Sambel Ijo">
                    <div class="product-info">
                        <h3>Ayam Sambel Ijo</h3>
                        <p class="price">Rp 15.000,-</p>
                        <p class="stock">-/50</p>
                    </div>
                </div>    
            </div>
        </div>

        <!-- section minuman -->
        <div id="minuman" class="menu-section">
            <h2 class="section-title">Minuman</h2>
            <div class="product-list">
                <div class="product-card">
                    <img src="https://via.placeholder.com/80" alt="Es Teh Manis">
                    <div class="product-info">
                        <h3>Es Teh Manis</h3>
                        <p class="price">Rp 5.000,-</p>
                        <p class="stock">-/50</p>
                    </div>
                </div>

                <div class="product-card">
                    <img src="https://via.placeholder.com/80" alt="Es Jeruk">
                    <div class="product-info">
                        <h3>Es Jeruk</h3>
                        <p class="price">Rp 7.000,-</p>
                        <p class="stock">-/50</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- detail pesanan -->
        <h2 class="section-title">Detail Pesanan</h2>
        <div class="form-box">
            <div class="input-row date-time-group">
                <input type="text" placeholder="DD/MM/YYYY" onfocus="(this.type='datetime-local')" onblur="(this.type='text')" required>
                <select required>
                    <option value="" disabled selected>Pilih Jam</option>
                    <option value="07:00">07:00</option>
                    <option value="08:00">08:00</option>
                    <option value="09:00">09:00</option>
                    <option value="10:00">10:00</option>
                    <option value="11:00">11:00</option>
                    <option value="12:00">12:00</option>
                    <option value="13:00">13:00</option>
                    <option value="14:00">14:00</option>
                    <option value="15:00">15:00</option>
                    <option value="16:00">16:00</option>
                    <option value="17:00">17:00</option>
                    <option value="18:00">18:00</option>
                    <option value="19:00">19:00</option>
                </select>
            </div>
            <div class="input-row">
                <input type="text" placeholder="Total pax & Total harga" readonly>
            </div>
        </div>

        <!-- pembayaran -->
        <h2 class="section-title">Pembayaran</h2>
        <div class="form-box">
            <h3 class="sub-title">Metode Pembayaran</h3>
            <div class="qr-placeholder">
                <span>QRIS</span>
            </div>
        </div>

        <!-- bukti pembayaran -->
        <h2 class="section-title">Bukti Pembayaran</h2>
        <input type="text" class="full-width-input" placeholder="Atas Nama" required>
        <div class="upload-area">
            <label for="file-upload" class="upload-label">
                <div class="upload-box" id="upload-box">
                    <span class="upload-icon">📷</span>
                    <img id="preview-image" src="" alt="Preview" style="display: none;">


                </div>
            </label>
            <input type="file" id="file-upload" accept="image/*" hidden>
        </div>
        <button type="button" class="confirm-btn" onclick="konfirmasiPesanan()">Confirm</button>
    </div>
    <script src="asset/script.js"></script>
</body>
</html>