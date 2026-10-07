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
<body>
    <div class="login-container">
        <h1 class="login-title" > Login </h1>
        <form class="login-box" action="login.php" method="POST">
            <input type="email" name="email" placeholder="Email" required autofocus>
            <input type="password" name="password" placeholder="Password" required>
            <button type="button" class="login-btn">Login</button>
        </form>

        <?php if (!empty($error)): ?> 
            <p style="color: #ff6b6b; font-size: 14px; margin-top: 15px; text-align: center;"> ">

                <?= htmlspecialchars($error) ?>
                </p>
            <?php endif; ?>


        <p class="login-footer">
            Belum punya akun? <a href="register.php">Registrasi</a>
        </p>
    </div>
</body>
</html>