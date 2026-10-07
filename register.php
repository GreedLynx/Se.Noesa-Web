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
        <div class="register-header">
            <a href="login.php" class="back-btn">&#10094;</a>
            <h1 class="register-title">Registrasi Akun</h1>
        </div>

        <form class="register-form" onsubmit="event.preventDefault();">
            <div class="input-group">
                <span class="input-icon">👤</span>
                <input type="text" name="nama" placeholder="Nama" required>
            </div>

            <div class="input-group">
                <span class="input-icon">📞</span>
                <input type="tel" name="no_telp" placeholder="No. Hp" required>
            </div>

            <div class="input-group">
                <span class="input-icon">✉️</span>
                <input type="email" nama="email" placeholder="Email" required>
            </div>

            <div class="input-group">
                <span class="input-icon">🔒</span>
                <input type="password" name="password" placeholder="Password" required>
            </div>

            <div class="input-group">
                <span class="input-icon">🔑</span>
                <input type="passwword" name="konfirmasi_password" placeholder="Konfirmasi Password" required>
            </div>

            <button type="submit" class="confirm-btn">Confirm</button>
        </form>
    </div>
</body>
</html>