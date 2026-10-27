<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__ . '/../app/bootstrap.php';

foreach (['min_pesan', 'gambar', 'kategori'] as $column) {
  $exists = q(
    'SELECT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema = current_schema() AND table_name = ? AND column_name = ?)',
    ['produk', $column]
  )->fetchColumn();
  fwrite(STDOUT, $column . ': ' . ($exists ? 'OK' : 'MISSING') . PHP_EOL);
}

$count = q('SELECT COUNT(*) FROM produk')->fetchColumn();
fwrite(STDOUT, 'produk rows: ' . $count . PHP_EOL);
foreach (q('SELECT kategori, COUNT(*) jumlah, COUNT(*) FILTER (WHERE kuota > terpakai) tersedia FROM produk GROUP BY kategori ORDER BY kategori')->fetchAll() as $row) {
  fwrite(STDOUT, $row['kategori'] . ': ' . $row['jumlah'] . ' produk, ' . $row['tersedia'] . ' tersedia untuk pelanggan' . PHP_EOL);
}
