<?php

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registrasi Akun</title>
    <link rel="stylesheet" href="asset/style.css">
</head>
<body>
    <div class="register-container">
        <div class="resgister-header">
            <a href="login.php" class="back-btn">&#10094;</a>
            <h1 class="register-title">Registrasi Akun</h1>
        </div>

        <form class="register-form" onsubmit="event.preventDefault();">
            <div class="input-group">
                <span class="input-icon">👤</span>
                <input type="text" placeholder="Nama" required>
            </div>

            <div class="input-group">
                <span class="input-icon">📞</span>
                <input type="text" placeholder="No. Hp" required>
            </div>

            <div class="input-group">
                <span class="input-icon">✉️</span>
                <input type="text" placeholder="Email" required>
            </div>

            <div class="input-group">
                <span class="input-icon">🔒</span>
                <input type="text" placeholder="Password" required>
            </div>

            <div class="input-group">
                <span class="input-icon">🔑</span>
                <input type="text" placeholder="Konfirmasi Password" required>
            </div>

            <button type="submit" class="confirm-btn">Confirm</button>
        </form>
    </div>
</body>
</html>