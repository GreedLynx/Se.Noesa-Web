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

$error   = '';
$success = isset($_GET['registered']) ? 'Akun berhasil dibuat, silakan login.' : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identifier = trim($_POST['email'] ?? '');   // email atau username
    $password   = $_POST['password'] ?? '';

    // Simulasi validasi (Ganti dengan query database nanti)
    if (in_array($identifier, ['admin@senoesa.com', 'admin'], true) && $password === 'admin123') {
        $_SESSION['user_id'] = 1;
        $_SESSION['role']    = 'admin';
        header('Location: admin/dashboard_admin.php');
        exit;
    } elseif (in_array($identifier, ['user@senoesa.com', 'user'], true) && $password === 'user123') {
        $_SESSION['user_id'] = 2;
        $_SESSION['role']    = 'pelanggan';
        header('Location: pesanan.php');
        exit;
    } else {
        $error = 'Email/Username atau Password salah!';
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

        <!-- Header: sapaan + logo -->
        <div class="auth-header-top">
            <div class="auth-greeting">
                <h1>Hello!</h1>
                <p>Welcome to Se.Noesa</p>
            </div>
            <img src="foto/Icon_Logo.png" alt="Logo Se.Noesa" class="app-logo">
        </div>

        <!-- Kartu putih -->
        <div class="auth-card-white">
            <h2 class="auth-title-green">Login</h2>

            <form action="login.php" method="POST">
                <!-- Email / Username -->
                <div class="input-icon-wrapper">
                    <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                    <input type="text" name="email" placeholder="Email atau Username" required autofocus>
                </div>

                <!-- Password -->
                <div class="input-icon-wrapper">
                    <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg>
                    <input type="password" name="password" id="loginPass" placeholder="Password" required>
                    <span class="toggle-password" onclick="togglePass('loginPass', this)">
                        <svg class="eye-closed" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9c4 5 14 5 18 0"/><path d="M6.5 13.5L5 16M12 15v3M17.5 13.5L19 16"/></svg>
                        <svg class="eye-open" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s4-6 10-6 10 6 10 6-4 6-10 6S2 12 2 12z"/><circle cx="12" cy="12" r="3"/></svg>
                    </span>
                </div>

                <?php if (!empty($error)): ?>
                    <p class="auth-error"><?= htmlspecialchars($error) ?></p>
                <?php elseif (!empty($success)): ?>
                    <p class="auth-success"><?= htmlspecialchars($success) ?></p>
                <?php endif; ?>

                <button type="submit" class="auth-btn-green">Login</button>
            </form>

            <p class="auth-footer-text">Belum punya akun? <a href="register.php">Register</a></p>
        </div>
    </div>

    <script>
        function togglePass(id, el) {
            const input = document.getElementById(id);
            const show = input.type === "password";
            input.type = show ? "text" : "password";
            el.classList.toggle("show", show);
        }
    </script>
</body>
</html>