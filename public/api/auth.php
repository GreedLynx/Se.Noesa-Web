<?php
// POST ?a=login|register  GET ?a=logout
require __DIR__ . '/../../app/bootstrap.php';

$action = $_GET['a'] ?? '';
$request = body();

if ($action === 'logout') {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
    json(['ok' => true]);
}

if ($action === 'login') {
    $identity = trim($request['identity'] ?? '');
    $user = q(
        "SELECT *
            FROM users
            WHERE (email = :email OR username = :username) AND role IN ('owner', 'admin', 'pelanggan')",
        [':email' => $identity, ':username' => $identity]
    )->fetch();
    if (!$user || !password_verify($request['password'] ?? '', $user['password_hash'])) {
        json(['error' => 'Email/username atau password salah'], 401);
    }
    session_regenerate_id(true);
    $_SESSION['user'] = ['id' => $user['id'], 'username' => $user['username'], 'role' => $user['role']];
    $redirect = ['owner' => 'owner/dashboard.php', 'admin' => 'admin/dashboard.php', 'pelanggan' => 'pelanggan/index.php'][$user['role']] ?? 'login.php';
    json(['ok' => true, 'redirect' => $redirect]);
}

if ($action === 'register') {
    foreach (['username', 'email', 'password', 'phone'] as $field) {
        if (empty($request[$field])) {
            json(['error' => "Kolom $field wajib diisi"], 422);
        }
    }
    if (!filter_var($request['email'], FILTER_VALIDATE_EMAIL)) {
        json(['error' => 'Format email tidak valid'], 422);
    }
    if (strlen($request['password']) < 6) {
        json(['error' => 'Password minimal 6 karakter'], 422);
    }
    if ($request['password'] !== ($request['confirm'] ?? '')) {
        json(['error' => 'Konfirmasi password tidak cocok'], 422);
    }
    try {
        q(
            "INSERT INTO users (username, email, password_hash, phone, role)
                VALUES (?, ?, ?, ?, 'pelanggan')",
            [$request['username'], $request['email'], password_hash($request['password'], PASSWORD_DEFAULT), $request['phone']]
        );
    } catch (PDOException $e) {
        json(['error' => 'Username atau email sudah dipakai'], 409);
    }
    json(['ok' => true, 'redirect' => 'login.php']);
}
json(['error' => 'Not found'], 404);
