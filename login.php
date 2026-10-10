<?php 

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title>
    <link rel="stylesheet" href="asset/style.css">
</head>
<body class="auth-bg">
    <div class="auth-card">
        <img src="foto/logo.png" alt="logo" class="bg-decoration">
        <h1 class="auth-title">Login</h1>

        <form class="auth-form" action="login.php" method="POST">
            <div class="auth-input-group">
                <span class="icon">✉️</span>
                <input type="email" name="email" placeholder="Email" required autofocus>
            </div>

            <div class="auth-input-group">
                <span class="icon">🔑</span>
                <input type="password" name="password" placeholder="Password" required>
            </div>

            <p class="auth-link">Belum punya akun? <a href="register.php">Daftar</a></p>

            <button type="submit" class="auth-btn">Login</button>
        </form>

        <?php if (!empty($error)): ?>
            <p style="color: #ff6b6b; font-size: 14px; margin-top:15px; text-align: center;">
                <?=htmlspecialchars($error) ?>
            </p>
            <?php endif; ?>
    </div>
</body>
</html>