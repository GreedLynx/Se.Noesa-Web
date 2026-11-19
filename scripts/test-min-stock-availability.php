<?php
// Regression test untuk H1: ketersediaan produk mengikuti minimum pemesanan.
// Murni unit test terhadap helper bersama; tidak membuka koneksi database.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../app/daily-quota.php';

function check(bool $ok, string $message): void
{
    if (!$ok) throw new RuntimeException('FAIL: ' . $message);
    echo 'PASS: ' . $message . PHP_EOL;
}

$cases = [
    ['sisa' => 0,  'min' => 10, 'expected' => false, 'label' => 'stok 0 / minimum 10 tidak tersedia'],
    ['sisa' => 2,  'min' => 10, 'expected' => false, 'label' => 'stok 2 / minimum 10 tidak tersedia'],
    ['sisa' => 9,  'min' => 10, 'expected' => false, 'label' => 'stok 9 / minimum 10 tidak tersedia'],
    ['sisa' => 10, 'min' => 10, 'expected' => true,  'label' => 'stok 10 / minimum 10 tersedia'],
    ['sisa' => 15, 'min' => 10, 'expected' => true,  'label' => 'stok 15 / minimum 10 tersedia'],
    ['sisa' => 50, 'min' => 10, 'expected' => true,  'label' => 'stok 50 / minimum 10 tersedia'],
    ['sisa' => 9,  'min' => 4,  'expected' => false, 'label' => 'minimum lama 4 tetap dibatasi 10'],
    ['sisa' => 11, 'min' => 12, 'expected' => false, 'label' => 'minimum produk lebih tinggi dihormati'],
    ['sisa' => 12, 'min' => 12, 'expected' => true,  'label' => 'stok mencapai minimum produk lebih tinggi'],
];
foreach ($cases as $c) {
    $product = ['id' => 1, 'nama' => 'Menu Uji', 'sisa' => $c['sisa'], 'min_pesan' => $c['min'], 'kuota' => 50, 'kuota_harian' => 50];
    check(daily_product_available($product) === $c['expected'], $c['label']);
}
check(daily_product_available(['sisa' => 50, 'min_pesan' => 10], true) === false, 'tanggal libur menutup produk');
check(daily_product_min_qty(['min_pesan' => 4]) === 10, 'minimum efektif minimal 10');

// Backend tetap menolak pesanan untuk produk yang tidak tersedia.
$snapshot = [
    'tanggal_libur' => false,
    'dailyCapacity' => 50,
    'dailyUsed' => 0,
    'items' => [[
        'id' => 1, 'nama' => 'Menu Uji', 'min_pesan' => 10,
        'kuota_harian' => 50, 'sisa' => 9,
    ]],
];
try {
    daily_quota_validate($snapshot, [1 => 10]);
    throw new RuntimeException('FAIL: backend menerima stok 9 di bawah minimum 10');
} catch (DomainException $e) {
    check(str_contains($e->getMessage(), 'Stok'), 'backend menolak stok 9 / minimum 10 (' . $e->getMessage() . ')');
}
$snapshot['items'][0]['sisa'] = 10;
check(count(daily_quota_validate($snapshot, [1 => 10])) === 1, 'backend menerima stok tepat minimum');

echo 'Seluruh pengujian ketersediaan stok lulus.' . PHP_EOL;
