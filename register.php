<?php
// register.php
$error = '';
$old   = ['username' => '', 'email' => '', 'no_telp' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $old['username'] = trim($_POST['username'] ?? '');
    $old['email']    = trim($_POST['email'] ?? '');
    $old['no_telp']  = trim($_POST['no_telp'] ?? '');
    $password        = $_POST['password'] ?? '';
    $konfirmasi      = $_POST['konfirmasi_password'] ?? '';

    if ($old['username'] === '' || $old['email'] === '' || $old['no_telp'] === '' || $password === '') {
        $error = 'Semua kolom wajib diisi.';
    } elseif (!filter_var($old['email'], FILTER_VALIDATE_EMAIL)) {
        $error = 'Format email tidak valid.';
    } elseif (!preg_match('/^[0-9+\-\s]{8,16}$/', $old['no_telp'])) {
        $error = 'Nomor telepon tidak valid.';
    } elseif (strlen($password) < 6) {
        $error = 'Password minimal 6 karakter.';
    } elseif ($password !== $konfirmasi) {
        $error = 'Konfirmasi password tidak sama.';
    } else {
        // TODO: simpan ke database
        // $hash = password_hash($password, PASSWORD_DEFAULT);
        header('Location: login.php?registered=1');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign Up - Se.Noesa</title>
    <link rel="stylesheet" href="asset/style.css">
</head>
<body class="auth-bg-pattern">

    <div class="register-wrapper">

        <!-- Header: logo di kanan -->
        <div class="auth-header-top">
            <img src="foto/Icon_Logo.png" alt="Logo Se.Noesa" class="app-logo">
        </div>

        <!-- Kartu putih -->
        <div class="auth-card-white">
            <a href="login.php" class="back-to-login">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
                Back to Login
            </a>

            <h2 class="auth-title-green">Sign Up</h2>

            <form action="register.php" method="POST">
                <!-- Username -->
                <div class="input-icon-wrapper">
                    <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                    <input type="text" name="username" placeholder="Username" value="<?= htmlspecialchars($old['username']) ?>" required>
                </div>

                <!-- Email (ikon amplop solid) -->
                <div class="input-icon-wrapper">
                    <svg class="input-icon" viewBox="0 0 24 24" fill="currentColor"><path d="M3 5h18a1 1 0 0 1 1 1v12a1 1 0 0 1-1 1H3a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1z"/><path d="M3 7l9 6 9-6" fill="none" stroke="#ffffff" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    <input type="email" name="email" placeholder="Email" value="<?= htmlspecialchars($old['email']) ?>" required>
                </div>

                <!-- Password -->
                <div class="input-icon-wrapper">
                    <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg>
                    <input type="password" name="password" id="regPass" placeholder="Password" required>
                    <span class="toggle-password" onclick="togglePass('regPass', this)">
                        <svg class="eye-closed" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9c4 5 14 5 18 0"/><path d="M6.5 13.5L5 16M12 15v3M17.5 13.5L19 16"/></svg>
                        <svg class="eye-open" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s4-6 10-6 10 6 10 6-4 6-10 6S2 12 2 12z"/><circle cx="12" cy="12" r="3"/></svg>
                    </span>
                </div>

                <!-- Konfirmasi Password -->
                <div class="input-icon-wrapper">
                    <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg>
                    <input type="password" name="konfirmasi_password" id="regPassConf" placeholder="Konfirmasi Password" required>
                    <span class="toggle-password" onclick="togglePass('regPassConf', this)">
                        <svg class="eye-closed" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9c4 5 14 5 18 0"/><path d="M6.5 13.5L5 16M12 15v3M17.5 13.5L19 16"/></svg>
                        <svg class="eye-open" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s4-6 10-6 10 6 10 6-4 6-10 6S2 12 2 12z"/><circle cx="12" cy="12" r="3"/></svg>
                    </span>
                </div>

                <!-- Nomor Telpon -->
                <div class="input-icon-wrapper">
                    <svg class="input-icon" viewBox="0 0 24 24" fill="#d9cfc6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="7" y="2" width="10" height="20" rx="2.5"/><line x1="11" y1="18.5" x2="13" y2="18.5"/></svg>
                    <input type="tel" name="no_telp" placeholder="Nomor Telpon" value="<?= htmlspecialchars($old['no_telp']) ?>" required>
                </div>

                <?php if (!empty($error)): ?>
                    <p class="auth-error"><?= htmlspecialchars($error) ?></p>
                <?php endif; ?>

                <button type="submit" class="auth-btn-green">Sign Up</button>
            </form>

            <p class="auth-footer-text">Sudah punya akun? <a href="login.php">Login</a></p>
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