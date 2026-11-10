<?php
require __DIR__ . '/../../app/bootstrap.php';
require __DIR__ . '/../../app/mailer.php';

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  header('Allow: POST');
  json(['error' => 'Metode request tidak didukung'], 405);
}

$request = body();
$action = $request['action'] ?? '';

if ($action === 'request') {
  $email = strtolower(trim((string)($request['email'] ?? '')));
  if ($email === '' || strlen($email) > 100 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    json(['error' => 'Masukkan alamat email yang valid'], 422);
  }
  if (!password_reset_mail_configured()) {
    json([
      'error' => 'Layanan email belum siap. Admin perlu memeriksa Composer, OpenSSL, dan konfigurasi SMTP.',
    ], 503);
  }

  $db = db();
  $db->beginTransaction();
  try {
    // Cari akun berdasarkan email dan kunci baris agar permintaan OTP bersamaan tidak saling menimpa.
    $matches = q('SELECT id,email FROM users WHERE LOWER(email)=? ORDER BY id LIMIT 2 FOR UPDATE', [$email])->fetchAll();
    $user = count($matches) === 1 ? $matches[0] : null;
    $otpId = null;
    $otp = null;
    $otpHash = null;

    if ($user) {
      $otp = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
      $otpHash = password_hash($otp, PASSWORD_DEFAULT);
      // UPSERT membuat atau memperbarui OTP; batas waktu mencegah pengiriman ulang terlalu cepat.
      $challenge = q(
        "INSERT INTO password_reset_otps(user_id,otp_hash,expires_at,attempts,requested_at,used_at,created_at)
         VALUES(?,?,now()+interval '10 minutes',0,now(),NULL,now())
         ON CONFLICT(user_id) DO UPDATE SET otp_hash=EXCLUDED.otp_hash,
           expires_at=EXCLUDED.expires_at,attempts=0,requested_at=now(),used_at=NULL,created_at=now()
         WHERE password_reset_otps.requested_at<=now()-interval '60 seconds'
         RETURNING id",
        [$user['id'], $otpHash]
      )->fetch();
      $otpId = $challenge ? (int)$challenge['id'] : null;
    }

    $db->commit();
  } catch (Throwable $error) {
    if ($db->inTransaction()) {
      $db->rollBack();
    }
    error_log('[Se.Noesa password reset] OTP request database operation failed (' . get_class($error) . ').');
    json(['error' => 'Permintaan reset belum dapat diproses. Silakan coba kembali nanti.'], 500);
  }

  if ($user && $otpId !== null && $otp !== null && $otpHash !== null) {
    try {
      send_password_reset_otp($user['email'], $otp);
    } catch (Throwable $error) {
      error_log('[Se.Noesa password reset] OTP email delivery failed (' . get_class($error) . ').');
      try {
        // Tandai OTP sebagai tidak aktif jika email gagal dikirim.
        q(
          'UPDATE password_reset_otps SET used_at=now() WHERE id=? AND otp_hash=? AND used_at IS NULL',
          [$otpId, $otpHash]
        );
      } catch (Throwable $cleanupError) {
        error_log('[Se.Noesa password reset] Could not invalidate undelivered OTP (' . get_class($cleanupError) . ').');
      }
    }
  }

  json([
    'message' => 'Jika email terdaftar dan email berhasil dikirim, kode OTP akan diterima. Jangan bagikan kode ini kepada siapa pun.',
  ]);
}

if ($action === 'reset') {
  $email = strtolower(trim((string)($request['email'] ?? '')));
  $otp = trim((string)($request['otp'] ?? ''));
  $password = (string)($request['password'] ?? '');
  $confirm = (string)($request['confirm'] ?? '');

  if ($email === '' || strlen($email) > 100 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    json(['error' => 'Masukkan alamat email yang valid'], 422);
  }
  if (!preg_match('/^\d{6}$/', $otp)) {
    json(['error' => 'Masukkan kode OTP 6 digit yang valid'], 422);
  }
  if (strlen($password) < 8 || strlen($password) > 72) {
    json(['error' => 'Password baru harus terdiri dari 8 hingga 72 byte'], 422);
  }
  if ($password !== $confirm) {
    json(['error' => 'Konfirmasi password tidak cocok'], 422);
  }

  $db = db();
  $db->beginTransaction();
  try {
    // Cari dan kunci akun pemilik email selama proses reset.
    $matches = q('SELECT id FROM users WHERE LOWER(email)=? ORDER BY id LIMIT 2 FOR UPDATE', [$email])->fetchAll();
    $user = count($matches) === 1 ? $matches[0] : null;
    if (!$user) {
      $db->rollBack();
      json(['error' => 'Kode OTP salah atau sudah kedaluwarsa'], 422);
    }

    // Ambil OTP yang masih berlaku dan kunci record agar jumlah percobaan atomik.
    $challenge = q(
      'SELECT id,otp_hash,attempts FROM password_reset_otps
       WHERE user_id=? AND used_at IS NULL AND expires_at>now() FOR UPDATE',
      [$user['id']]
    )->fetch();
    if (!$challenge || (int)$challenge['attempts'] >= 5) {
      $db->rollBack();
      json(['error' => 'Kode OTP salah atau sudah kedaluwarsa'], 422);
    }

    if (!password_verify($otp, $challenge['otp_hash'])) {
      // Tambah jumlah percobaan dan tandai OTP terpakai setelah lima kali gagal.
      q(
        "UPDATE password_reset_otps
         SET attempts=attempts+1,
             used_at=CASE WHEN attempts+1>=5 THEN now() ELSE used_at END
         WHERE id=?",
        [$challenge['id']]
      );
      $db->commit();
      json(['error' => 'Kode OTP salah atau sudah kedaluwarsa'], 422);
    }

    // UPDATE mengganti password hanya setelah OTP dinyatakan benar.
    q('UPDATE users SET password_hash=? WHERE id=?', [password_hash($password, PASSWORD_DEFAULT), $user['id']]);
    // Tandai OTP terpakai sekali saja dan RETURNING memastikan perubahan berhasil.
    $used = q(
      'UPDATE password_reset_otps SET used_at=now() WHERE id=? AND used_at IS NULL RETURNING id',
      [$challenge['id']]
    )->fetch();
    if (!$used) {
      $db->rollBack();
      json(['error' => 'Kode OTP salah atau sudah kedaluwarsa'], 422);
    }

    $db->commit();
    json(['ok' => true, 'message' => 'Password berhasil diperbarui. Silakan login dengan password baru.']);
  } catch (Throwable $error) {
    if ($db->inTransaction()) {
      $db->rollBack();
    }
    error_log('[Se.Noesa password reset] Password update failed (' . get_class($error) . ').');
    json(['error' => 'Password belum dapat diperbarui. Silakan coba kembali.'], 500);
  }
}

json(['error' => 'Aksi tidak valid'], 422);
