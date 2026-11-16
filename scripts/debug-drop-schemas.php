<?php
if (PHP_SAPI !== 'cli') { exit("Not found\n"); }
$port = getenv('SENOESA_TEST_PGPORT');
if (!$port) { fwrite(STDERR, "SENOESA_TEST_PGPORT wajib\n"); exit(1); }
$pdo = new PDO(
    'pgsql:host=127.0.0.1;port=' . $port . ';dbname=senoesa_cancellation_test;sslmode=disable',
    getenv('SENOESA_TEST_PGUSER') ?: 'postgres',
    getenv('SENOESA_TEST_PGPASSWORD') ?: ''
);
$rows = $pdo->query(
    "SELECT nspname FROM pg_namespace
      WHERE nspname LIKE 'mnconc_test_%' OR nspname LIKE 'dbg_%'"
)->fetchAll(PDO::FETCH_COLUMN);
foreach ($rows as $schema) {
    $pdo->exec('DROP SCHEMA IF EXISTS "' . $schema . '" CASCADE');
    echo 'DROP ' . $schema . PHP_EOL;
}
echo 'done' . PHP_EOL;
