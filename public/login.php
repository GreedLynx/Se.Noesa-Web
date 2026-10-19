<?php require __DIR__ . '/partials/layout.php';
if (me()) {
    header('Location: dashboard.php');
    exit;
}
head('Login', 'login', true); ?>
<section class="hero">
    <div class="drift"></div>
    <div class="hello rise">
        <h2>Hello!</h2>
        <p>Welcome to Se.Noesa</p>
    </div>
</section>
<section class="sheet">
    <h2 class="title rise">Login</h2>
    <form id="f" class="rise" data-api="api/auth.php?a=login" autocomplete="on">
        <label class="field"><svg viewBox="0 0 24 24">
                <circle cx="12" cy="8" r="4" />
                <path d="M4 21c0-4 4-6 8-6s8 2 8 6" />
            </svg><input name="identity" placeholder="Email atau Username" required></label>
        <label class="field"><svg viewBox="0 0 24 24">
                <rect x="5" y="10" width="14" height="10" rx="2" />
                <path d="M8 10V7a4 4 0 0 1 8 0v3" />
            </svg><input name="password" type="password" placeholder="Password" required><button type="button" class="eye" aria-label="Tampilkan password">👁</button></label>
        <p class="err" id="err"></p><button class="btn-main">Login</button>
    </form>
    <p class="alt rise">Belum punya akun? <a href="register.php">Register</a></p>
</section>
<?php foot();
