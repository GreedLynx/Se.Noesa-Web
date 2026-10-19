<?php 
// login.php
session_start();

// Redirect jika sudah login
if (isset($_SESSION['user_id'])) {
    if ($_SESSION['role'] === 'admin') {
        header('Location: admin/dashboard_admin.php');
    } else {
        header('Location: pesanan.php');
    }
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    // Simulasi validasi (Ganti dengan query database nanti)
    if ($email === 'admin@senoesa.com' && $password === 'admin123') {
        $_SESSION['user_id'] = 1;
        $_SESSION['role']    = 'admin';
        header('Location: admin/dashboard_admin.php');
        exit;
    } elseif ($email === 'user@senoesa.com' && $password === 'user123') {
        $_SESSION['user_id'] = 2;
        $_SESSION['role']    = 'pelanggan';
        header('Location: pesanan.php');
        exit;
    } else {
        $error = 'Email atau Password salah!';
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Se.Noesa</title>
    <link rel="stylesheet" href="asset/style.css">
</head>
<body class="auth-bg-pattern">
    
    <div class="login-wrapper">
        
        <!-- Header dengan Teks dan Logo -->
        <div class="auth-header-top">
            <div class="auth-greeting">
                <h1>Hello!</h1>
                <p>Welcome to Se.Noesa</p>
            </div>
            <img src="foto/logo.png" alt="Logo Se.Noesa" class="app-logo">
        </div>

        <!-- Kartu Putih Form Login -->
        <div class="auth-card-white">
            <h2 class="auth-title-green">Login</h2>

            <form action="login.php" method="POST">
                <!-- Input Email -->
                <div class="input-icon-wrapper">
                    <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="#5a7a5e" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
                    <input type="email" name="email" placeholder="Email atau Username" required autofocus>
                </div>

                <!-- Input Password -->
                <div class="input-icon-wrapper">
                    <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="#5a7a5e" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
                    <input type="password" name="password" id="loginPass" placeholder="Password" required>
                    <span class="toggle-password" onclick="togglePass('loginPass', this)">👁️</span>
                </div>

                <!-- Pesan Error -->
                <?php if (!empty($error)): ?>
                    <p style="color: #ff6b6b; font-size: 13px; margin-bottom: 10px; text-align: center;"><?= htmlspecialchars($error) ?></p>
                <?php endif; ?>

                <button type="submit" class="auth-btn-green">Login</button>
            </form>

            <p class="auth-footer-text">Belum punya akun? <a href="register.php">Register</a></p>
        </div>
    </div>

    <script>
        function togglePass(id, el) {
            const input = document.getElementById(id);
            if (input.type === "password") {
                input.type = "text";
                el.style.opacity = "1";
            } else {
                input.type = "password";
                el.style.opacity = "0.5";
            }
        }
    </script>
</body>
</html>