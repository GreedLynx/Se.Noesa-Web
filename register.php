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
    <div class="auth-wrap">
        <a href="login.php" class="back-btn" aria-label="Kembali ke login">
            <svg viewBox="0 0 24 24" aria-hidden="true">
                <path d="M9 14L4 9l5-5"/>
                <path d="M4 9h10.5a5.5 5.5 0 0 1 0 11H11"/>
            </svg>
        </a>
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
    </div>
</body>
</html>