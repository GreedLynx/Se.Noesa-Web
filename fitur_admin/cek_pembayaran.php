<?php
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cek Pembayaran - SE.Noesa</title>
    <link rel="stylesheet" href="../asset/style.css">
</head>
<body>
    <div class="app-container">

    <div class="admin-header" style="justify-content: flex-start; gap: 15px;">
        <a href="verifikasi_pesanan.php" class="back-btn">&#10094;</a>
        <h1 class="admin-title">Cek Pembayaran</h1>
    </div>

    <div class="proof-box">
        <span class="proof-emoji">🖼️</span>
    </div>
    <button class="confirm-payment-btn" id="btnKonfirmasi" onclick="konfirmasiPembayaran()">Konfirmasi Pesanan</button>
    </div>
    <script src="../asset/script.js"></script>
</body>
</html>