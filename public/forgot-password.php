<?php require __DIR__ . '/owner/partials/layout.php';
if (me()) {
    header('Location: login.php');
    exit;
}
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Referrer-Policy: no-referrer');
head('Forgot Password', 'forgot-password', true); ?>
<section class="hero short">
    <div class="drift"></div>
</section>
<section class="sheet reset-sheet">
    <a class="back rise" href="login.php">← Back to Login</a>
    <h2 class="title rise">Forgot Password</h2>
    <p class="reset-note rise">Masukkan email akun Se.Noesa Anda. Jika email terdaftar, kode OTP akan dikirim.</p>
    <form id="forgot-password-form" class="rise" autocomplete="on">
        <label class="field"><svg viewBox="0 0 24 24" aria-hidden="true">
                <rect x="3" y="6" width="18" height="13" rx="2" />
                <path d="m3 8 9 6 9-6" />
            </svg><input name="email" type="email" placeholder="Email" maxlength="100" autocomplete="email" required></label>
        <p class="reset-error" id="forgot-password-error" role="alert" aria-live="polite"></p>
        <p class="reset-notice" id="forgot-password-notice" role="status" aria-live="polite"></p>
        <button class="btn-main" id="request-otp-button" type="submit">Kirim Kode OTP</button>
    </form>
    <form id="reset-password-form" class="rise" autocomplete="off" hidden>
        <label class="field"><svg viewBox="0 0 24 24" aria-hidden="true">
                <rect x="5" y="10" width="14" height="10" rx="2" />
                <path d="M8 10V7a4 4 0 0 1 8 0v3" />
            </svg><input name="otp" type="text" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" placeholder="Kode OTP 6 digit" autocomplete="one-time-code" required></label>
        <label class="field"><svg viewBox="0 0 24 24" aria-hidden="true">
                <rect x="5" y="10" width="14" height="10" rx="2" />
                <path d="M8 10V7a4 4 0 0 1 8 0v3" />
            </svg><input name="password" type="password" placeholder="Password Baru" minlength="8" maxlength="72" autocomplete="new-password" required><button type="button" class="eye" aria-label="Tampilkan password">👁</button></label>
        <label class="field"><svg viewBox="0 0 24 24" aria-hidden="true">
                <rect x="5" y="10" width="14" height="10" rx="2" />
                <path d="M8 10V7a4 4 0 0 1 8 0v3" />
            </svg><input name="confirm" type="password" placeholder="Konfirmasi Password Baru" minlength="8" maxlength="72" autocomplete="new-password" required><button type="button" class="eye" aria-label="Tampilkan password">👁</button></label>
        <p class="reset-error" id="reset-password-error" role="alert" aria-live="polite"></p>
        <button class="btn-main" type="submit">Simpan Password Baru</button>
        <button class="btn-main reset-secondary-button" id="resend-otp-button" type="button" disabled>Kirim ulang kode (60)</button>
        <button class="reset-change-email" id="change-reset-email" type="button">Ganti email</button>
    </form>
</section>
<?php foot();
