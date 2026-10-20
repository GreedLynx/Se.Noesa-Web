<?php require __DIR__ . '/owner/partials/layout.php';
head('Sign Up', 'register', true); ?>
<section class="hero short">
    <div class="drift"></div>
</section>
<section class="sheet"><a class="back rise" href="login.php">← Back to Login</a>
    <h2 class="title rise">Sign Up</h2>
    <form id="f" class="rise" data-api="api/auth.php?a=register">
        <label class="field"><svg viewBox="0 0 24 24">
                <circle cx="12" cy="8" r="4" />
                <path d="M4 21c0-4 4-6 8-6s8 2 8 6" />
            </svg><input name="username" placeholder="Username" required></label>
        <label class="field"><svg viewBox="0 0 24 24">
                <rect x="3" y="6" width="18" height="13" rx="2" />
                <path d="m3 8 9 6 9-6" />
            </svg><input name="email" type="email" placeholder="Email" required></label>
        <label class="field"><svg viewBox="0 0 24 24">
                <rect x="5" y="10" width="14" height="10" rx="2" />
                <path d="M8 10V7a4 4 0 0 1 8 0v3" />
            </svg><input name="password" type="password" placeholder="Password" required><button type="button" class="eye">👁</button></label>
        <label class="field"><svg viewBox="0 0 24 24">
                <rect x="5" y="10" width="14" height="10" rx="2" />
                <path d="M8 10V7a4 4 0 0 1 8 0v3" />
            </svg><input name="confirm" type="password" placeholder="Konfirmasi Password" required><button type="button" class="eye">👁</button></label>
        <label class="field"><svg viewBox="0 0 24 24">
                <rect x="7" y="3" width="10" height="18" rx="2" />
            </svg><input name="phone" type="tel" placeholder="Nomor Telpon" required></label>
        <p class="err" id="err"></p><button class="btn-main">Sign Up</button>
    </form>
    <p class="alt rise">Sudah punya akun? <a href="login.php">Login</a></p>
</section>
<?php foot();
