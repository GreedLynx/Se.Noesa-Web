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
<body class="auth-bg auth-register">
    <div class="auth-card">
        <h1 class="auth-title">Registration</h1>

        <form class="auth-form" action="regirster.php" method="POST">
            <div class="auth-input-group">
                <span class="icon">👤</span>
                <input type="text" name="nama" placeholder="Nama" required>
            </div>

            <div class="auth-input-group">
                <span class="icon">📞</span>
                <input type="tel" name="no_telp" placeholder="No. Hp" required>
            </div>

            <div class="auth-input-group">
                <span class="icon">✉️</span>
                <input type="email" nama="email" placeholder="Email" required>
            </div>

            <div class="auth-input-group">
                <span class="icon">🔒</span>
                <input type="password" name="password" placeholder="Password" required>
            </div>

            <div class="auth-input-group">
                <span class="icon">🔑</span>
                <input type="passwword" name="konfirmasi_password" placeholder="Verify Password" required>
            </div>

            <button type="submit" class="auth-btn">Confirm</button>
        </form>
    </div>
</body>
</html>