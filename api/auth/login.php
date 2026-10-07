<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/response.php';
require_once __DIR__ . '/../../helpers/jwt.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'Method tidak diizinkan'], 405);
}

$input      = getJsonInput();
$email      = strtolower(trim($input['email'] ?? ''));
$password   = $input['password'] ?? '';

if ($email == '' || $password == '') {
    jsoResponse(['error' => 'Email dan Password wajib diisi'], 422);
}

$stmt = getDB()->prepare(
    'SELECT user_id, nama, email, password, no_telp, alamat, role FROM user WHERE email = ?'
);
$stmt->execute([$email]);
$user = $stmt->fetch();


if (!$user || !password_verify($password, $user['password'])) {
    jsonResponse(['error' => 'Email atau Password salah'], 401);
}

jsonResponse([
    'message'   =>  'Login Berhasil',
    'token'     =>  createToken($user),
    'user'      => [
        'user_id'   => (int) $user['user_id'],
        'nama'      => $user['nama'],
        'email'     => $user['email'],
        'no_telp'   => $user['no_telp'],
        'alamat'    => $user['alamat'],
        'role'      => $user['role'],
    ],
]);