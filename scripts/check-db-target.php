<?php

require __DIR__ . '/../app/bootstrap.php';

try {
    $pdo = db();

    $result = $pdo->query("
        SELECT
            current_database() AS database_name,
            current_user AS database_user,
            inet_server_addr()::text AS server_address
    ")->fetch(PDO::FETCH_ASSOC);

    echo "Database connection: SUCCESS\n";
    echo "Database: " . $result['database_name'] . "\n";
    echo "User: " . $result['database_user'] . "\n";
    echo "Server: " . ($result['server_address'] ?? 'unknown') . "\n";

} catch (Throwable $e) {
    echo "Database connection: FAILED\n";
    echo $e->getMessage() . "\n";
    exit(1);
}