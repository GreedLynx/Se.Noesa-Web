<?php
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Riwayat Pesanan - Se.Noesa</title>
    <link rel="stylesheet" href="asset/style.css">
</head>
<body>
    <div class="app-container">
        <div class="tabs">
            <a href="pesanan.php" class="tab">Pesanan</a>
            <a href="riwayat.php" class="tab active">Riwayat Pesanan</a>
        </div>

        <h1 class="page-title">Riwayat Pesanan</h1>

        <div class="history-card">
            <h3 class="card-title">Pesanan 1</h3>
            <div class="history-input-group">
                <input type="text" placeholder="Total pax" value="2 pax" readonly>
            </div>

            <div class="history-input-group">
                <input type="text" placeholder="Total Harga" value="Rp 30.000,-" readonly>
            </div>

            <div class="card-action">
                <button type="button" class="outline-btn">Konfirmasi Pesanan</button>
            </div>
        </div>
    </div>
</body>
</html>