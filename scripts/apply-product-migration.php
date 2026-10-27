<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__ . '/../app/bootstrap.php';

$migration = file_get_contents(__DIR__ . '/../database/migrations/001_product_min_order.sql');
if ($migration === false) {
  fwrite(STDERR, "Migration file could not be read.\n");
  exit(1);
}

try {
  db()->exec($migration);
  fwrite(STDOUT, "Product migration applied successfully.\n");
} catch (Throwable $error) {
  fwrite(STDERR, "Migration failed: " . $error->getMessage() . "\n");
  exit(1);
}
