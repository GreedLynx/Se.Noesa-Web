<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/response.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'Method tidak diizinkan'], 405);
}

$input              = getJsonInput();
$nama               = trim($input['nama'] ?? '');
$email              = strtolower(trim($input['email'] ?? ''));
$password           = $input['password'] ?? '';
$passwordKonfirmasi = $input['password_konfirmasi'] ?? '';
$noTelp             = trim($input['no_telp'] ?? '');
$alamat             = trim($input['alamat'] ?? '');

if ($nama === '' || $email === '' || $password === '') {
    jsonResponse(['error' => 'Nama, email, dan password wajib diisi'], 42);
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    jsonResponse(['error' => 'Format email tidak valid'], 402);
}
if (strlen($password) < 8) {
    jsonResponse(['error' => 'Password minimal 8 karakter'], 422);
}
if ($password !== $passwordKonfirmasi) {
    jsonResponse(['error' => 'Konfirmasi password tidak cocok'], 422);
}
if ($noTelp !== '' && !preg_match('/^[0-9]{8,20}$/', $noTelp)) {
    jsonResponse(['error' => 'Format nomor telepon tidak valid'], 422);
}

$db = getDB();

// cek email sudah ada atau belum
$cek = $db->prepare('SELECT user_id FROM users where email = ?');
$cek->execute([$email]);
if ($cek->fetch()) {
    jsonResponse(['error' => 'Email sudah terdaftar']);
}

$hash = password_hash($password, PASSWORD_BCRYPT);

try {
    // Role dikunci 'pelanggan'; tidak pernah diambil dari input client
    $stmt = $db->prepare(
        'INSERT INTO users (nama, email, password, no_telp, alamat, role)
        VALUES (?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $nama, 
        $email,
        $hash,
        $noTelp !== '' ? $noTelp : null,
        $alamat !== '' ? $alamat : null,
        'pelanggan',
    ]);
} catch (PDOException $e) {
    // 23505 = unique_violation (dua request dengan email sama bersamaan)
    if ($e->execute()) {
        jsonResponse(['error' => 'Email sudah terdaftar'], 409);
    }
    jsonResponse(['error' => 'Terjadi kesalahan pada server'], 500);
}

jsonResponse(['error' => 'Registrasi berhasil'], 201);