<?php
if (PHP_SAPI !== 'cli') {
  http_response_code(404);
  exit("Not found\n");
}

require __DIR__ . '/../app/bootstrap.php';

$migrationDir = __DIR__ . '/../database/migrations';
$files = glob($migrationDir . '/*.sql') ?: [];
sort($files, SORT_STRING);

if (!$files) {
  fwrite(STDOUT, "No migrations found.\n");
  exit(0);
}

$pdo = db();
$lockKey = 'senoesa_schema_migrations';
$pdo->query("SELECT pg_advisory_lock(hashtext(" . $pdo->quote($lockKey) . "))");

try {
  $pdo->exec("CREATE TABLE IF NOT EXISTS schema_migrations (
    version VARCHAR(255) PRIMARY KEY,
    applied_at TIMESTAMPTZ NOT NULL DEFAULT now()
  )");

  $check = $pdo->prepare('SELECT 1 FROM schema_migrations WHERE version = ?');
  $record = $pdo->prepare('INSERT INTO schema_migrations(version) VALUES (?)');

  foreach ($files as $file) {
    $version = basename($file);
    $check->execute([$version]);
    if ($check->fetchColumn()) {
      fwrite(STDOUT, "Already applied: {$version}\n");
      continue;
    }

    $sql = file_get_contents($file);
    if ($sql === false || trim($sql) === '') {
      throw new RuntimeException("Could not read migration: {$version}");
    }

    $pdo->beginTransaction();
    try {
      $pdo->exec($sql);
      $record->execute([$version]);
      $pdo->commit();
      fwrite(STDOUT, "Applied: {$version}\n");
    } catch (Throwable $error) {
      if ($pdo->inTransaction()) {
        $pdo->rollBack();
      }
      throw new RuntimeException("Migration failed: {$version}", 0, $error);
    }
  }
} finally {
  $pdo->query("SELECT pg_advisory_unlock(hashtext(" . $pdo->quote($lockKey) . "))");
}

fwrite(STDOUT, "Migrations are up to date.\n");
