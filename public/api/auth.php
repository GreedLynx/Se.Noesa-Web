<?php // POST ?a=login|register  GET ?a=logout
require __DIR__ . '/../../app/bootstrap.php';
$a = $_GET['a'] ?? '';
$b = body();
if ($a === 'logout') {
  session_destroy();
  json(['ok' => true]);
}
if ($a === 'login') {
  $identity = trim($b['identity'] ?? '');
  $u = q(
    "SELECT * FROM users WHERE (email=:email OR username=:username) AND role='owner'",
    [':email' => $identity, ':username' => $identity]
  )->fetch();
  if (!$u || !password_verify($b['password'] ?? '', $u['password_hash'])) json(['error' => 'Email/username atau password salah'], 401);
  session_regenerate_id(true);
  $_SESSION['user'] = ['id' => $u['id'], 'username' => $u['username'], 'role' => $u['role']];
  json(['ok' => true, 'redirect' => 'dashboard.php']);
}
if ($a === 'register') {
  foreach (['username', 'email', 'password', 'phone'] as $f) if (empty($b[$f])) json(['error' => "Kolom $f wajib diisi"], 422);
  if (!filter_var($b['email'], FILTER_VALIDATE_EMAIL)) json(['error' => 'Format email tidak valid'], 422);
  if (strlen($b['password']) < 6) json(['error' => 'Password minimal 6 karakter'], 422);
  if ($b['password'] !== ($b['confirm'] ?? '')) json(['error' => 'Konfirmasi password tidak cocok'], 422);
  try {
    q(
      "INSERT INTO users(username,email,password_hash,phone,role) VALUES(?,?,?,?,'owner')",
      [$b['username'], $b['email'], password_hash($b['password'], PASSWORD_DEFAULT), $b['phone']]
    );
  } catch (PDOException $e) {
    json(['error' => 'Username atau email sudah dipakai'], 409);
  }
  json(['ok' => true, 'redirect' => 'login.php']);
}
json(['error' => 'Not found'], 404);
