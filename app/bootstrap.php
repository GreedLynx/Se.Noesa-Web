<?php
// Argumen PDO memuat kredensial dan tidak boleh masuk ke stack trace.
ini_set('zend.exception_ignore_args', '1');
// Pengaturan cookie sesi dikeraskan sebelum sesi dimulai (K1/D1).
// Secure hanya aktif pada HTTPS agar pengembangan lokal via HTTP tetap jalan;
// HttpOnly mencegah pembacaan cookie oleh skrip, SameSite=Lax membatasi
// pengiriman cookie pada permintaan lintas situs tanpa merusak navigasi normal.
$_snoesa_https = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== '' && strtolower($_SERVER['HTTPS']) !== 'off')
    || strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => $_snoesa_https,
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();

// Token CSRF terikat pada sesi; dibuat sekali per sesi dan dipakai ulang
// hingga sesi baru dibuat lewat session_regenerate_id() saat login.
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function csrf_require(): void
{
    // Token diterima dari header X-CSRF-Token (JS), field form csrf_token,
    // atau field JSON body csrf_token agar semua pola permintaan tercakup.
    $token = null;
    $header = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (is_string($header) && $header !== '') {
        $token = $header;
    } elseif (isset($_POST['csrf_token']) && is_string($_POST['csrf_token']) && $_POST['csrf_token'] !== '') {
        $token = $_POST['csrf_token'];
    } else {
        $raw = file_get_contents('php://input');
        if (is_string($raw) && $raw !== '') {
            $json = json_decode($raw, true);
            if (is_array($json) && isset($json['csrf_token'])
                && is_string($json['csrf_token']) && $json['csrf_token'] !== '') {
                $token = $json['csrf_token'];
            }
        }
    }
    if (!is_string($token) || $token === '' || !hash_equals(csrf_token(), $token)) {
        json(['error' => 'Token CSRF tidak valid. Muat ulang halaman lalu coba lagi.'], 419);
    }
}

function cfg()
{
    static $config;

    return $config ??= require __DIR__ . '/../config/config.php';
}

function db(): PDO
{
    static $connection;

    if ($connection) {
        return $connection;
    }

    $config = cfg()['db'];
    $sslmode = $config['sslmode'] ?? 'require';

    $dsn = "pgsql:"
        . "host={$config['host']};"
        . "port={$config['port']};"
        . "dbname={$config['name']};"
        . "sslmode={$sslmode}";

    return $connection = new PDO(
        $dsn,
        $config['user'],
        $config['pass'],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
}

function q(string $sql, array $params = []): PDOStatement
{
    // Siapkan SQL lalu ikat parameter agar nilai input tidak digabung langsung ke query.
    $statement = db()->prepare($sql);
    $statement->execute($params);

    return $statement;
}

function json($data, int $code = 200): void
{
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($data, JSON_NUMERIC_CHECK);
    exit;
}

function body(): array
{
    return json_decode(file_get_contents('php://input'), true) ?: $_POST;
}

function me()
{
    return $_SESSION['user'] ?? null;
}

function require_role(string $role, bool $api = true)
{
    $user = me();
    if (!$user || $user['role'] !== $role) {
        if ($api) {
            json(['error' => 'Unauthorized'], 401);
        }

        header('Location: ../login.php');
        exit;
    }

    return $user;
}

function require_owner(bool $api = true)
{
    $user = me();
    if (!$user || $user['role'] !== 'owner') {
        if ($api) {
            json(['error' => 'Unauthorized'], 401);
        }

        header('Location: ../login.php');
        exit;
    }

    return $user;
}
