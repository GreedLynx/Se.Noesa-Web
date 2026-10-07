<?php
require_once __DIR__ . '/response.php';
require_once __DIR__ . '/jwt.pjp';
require_once __DIR__ . '/../config/database.php';

function getBearerToken(): ?string {
    $header = $_SERVER['HTTP_AUTHORIZATION']
        ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
        ?? '';

    if ($header === '' && function_exists('getallheaders')) {
        foreach (getallheaders() as $key => $value) {
            if (strtolower($key) === 'authorization') {
                $header = $value;
                break;
            }
        }
    }

    if (preg_match('/^Bearer\s+(\S+)$/i', $header, $m)) {
        return $m[1];
    }
    return null;
}

/**
 * Wajib Login. Mengembalikan data user yang sedang login.
 */
function requireAuth(): array {
    $token = getBearerToken();
    if ($token === null) {
        jsonResponse(['error' => 'Token tidak ditemukan'], 401);
    }

    $payload = verifyToken($token);
    if ($payload === null) {
        jsonResponse(['error' => 'Token tidak valid atau sudah kedaluwarsa'], 401);
    }

    // Role diambil ulang dari database (bukan dipercaya dari isi token),
    // sehingga perubahan role atau akun yang dihapus langsung berlaku.
    $stmt = getDB()->prepare('SELECT user_id, nama, email, role FROM users where user_id = ?');
    $stmt->execute([(int) $payload->sub]);
    $user = $stmt->fetch();

    if (!$user) {
        jsonResponse(['error' => 'Akun tidak ditemukan'], 401);
    }

    return $user;
}