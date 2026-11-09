<?php
session_start();
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
