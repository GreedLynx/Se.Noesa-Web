<?php
// register.php
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
        
        <!-- Header Logo di Kanan -->
        <div class="auth-header-top" style="justify-content: flex-end; padding-bottom: 10px;">
            <img src="foto/logo.png" alt="Logo Se.Noesa" class="app-logo">
        </div>

        <!-- Kartu Putih Form Register -->
        <div class="auth-card-full">
            <a href="login.php" class="back-to-login">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
                Back to Login
            </a>

            <h2 class="auth-title-green">Sign Up</h2>

            <form action="register.php" method="POST">
                <!-- Input Username -->
                <div class="input-icon-wrapper">
                    <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="#5a7a5e" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
                    <input type="text" name="username" placeholder="Username" required>
                </div>

                <!-- Input Email -->
                <div class="input-icon-wrapper">
                    <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="#5a7a5e" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><polyline points="22,6 12,13 2,6"></polyline></svg>
                    <input type="email" name="email" placeholder="Email" required>
                </div>

                <!-- Input Password -->
                <div class="input-icon-wrapper">
                    <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="#5a7a5e" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
                    <input type="password" name="password" id="regPass" placeholder="Password" required>
                    <span class="toggle-password" onclick="togglePass('regPass', this)">👁️</span>
                </div>

                <!-- Input Konfirmasi Password -->
                <div class="input-icon-wrapper">
                    <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="#5a7a5e" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
                    <input type="password" name="konfirmasi_password" id="regPassConf" placeholder="Konfirmasi Password" required>
                    <span class="toggle-password" onclick="togglePass('regPassConf', this)">👁️</span>
                </div>

                <!-- Input Nomor Telpon -->
                <div class="input-icon-wrapper">
                    <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="#5a7a5e" stroke-width="2"><rect x="5" y="2" width="14" height="20" rx="2" ry="2"></rect><line x1="12" y1="18" x2="12.01" y2="18"></line></svg>
                    <input type="tel" name="no_telp" placeholder="Nomor Telpon" required>
                </div>

                <button type="submit" class="auth-btn-green">Sign Up</button>
            </form>

            <p class="auth-footer-text">Sudah punya akun? <a href="login.php">Login</a></p>
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