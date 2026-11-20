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
    ['sisa' => 0,  'min' => 4, 'expected' => false, 'label' => 'stok 0 / minimum 4 tidak tersedia'],
    ['sisa' => 2,  'min' => 4, 'expected' => false, 'label' => 'stok 2 / minimum 4 tidak tersedia'],
    ['sisa' => 3,  'min' => 4, 'expected' => false, 'label' => 'stok 3 / minimum 4 tidak tersedia'],
    ['sisa' => 4,  'min' => 4, 'expected' => true,  'label' => 'stok 4 / minimum 4 tersedia'],
    ['sisa' => 9,  'min' => 10, 'expected' => false, 'label' => 'minimum khusus 10 tetap berlaku'],
    ['sisa' => 10, 'min' => 10, 'expected' => true,  'label' => 'stok 10 / minimum khusus 10 tersedia'],
    ['sisa' => 15, 'min' => 10, 'expected' => true,  'label' => 'stok 15 / minimum 10 tersedia'],
    ['sisa' => 50, 'min' => 10, 'expected' => true,  'label' => 'stok 50 / minimum 10 tersedia'],
    ['sisa' => 11, 'min' => 12, 'expected' => false, 'label' => 'minimum produk lebih tinggi dihormati'],
    ['sisa' => 12, 'min' => 12, 'expected' => true,  'label' => 'stok mencapai minimum produk lebih tinggi'],
];
foreach ($cases as $c) {
    $product = ['id' => 1, 'nama' => 'Menu Uji', 'sisa' => $c['sisa'], 'min_pesan' => $c['min'], 'kuota' => 50, 'kuota_harian' => 50];
    check(daily_product_available($product) === $c['expected'], $c['label']);
}
check(daily_product_available(['sisa' => 50, 'min_pesan' => 10], true) === false, 'tanggal libur menutup produk');
check(daily_product_min_qty(['min_pesan' => 4]) === 4, 'minimum efektif minimal 4');

// Backend tetap menolak pesanan untuk produk yang tidak tersedia.
$snapshot = [
    'tanggal_libur' => false,
    'dailyCapacity' => 50,
    'dailyUsed' => 0,
    'items' => [[
        'id' => 1, 'nama' => 'Menu Uji', 'min_pesan' => 4,
        'kuota_harian' => 50, 'sisa' => 3,
    ]],
];
try {
    daily_quota_validate($snapshot, [1 => 4]);
    throw new RuntimeException('FAIL: backend menerima stok 3 di bawah minimum 4');
} catch (DomainException $e) {
    check(str_contains($e->getMessage(), 'Stok'), 'backend menolak stok 3 / minimum 4 (' . $e->getMessage() . ')');
}
$snapshot['items'][0]['sisa'] = 4;
check(count(daily_quota_validate($snapshot, [1 => 4])) === 1, 'backend menerima pesanan tepat 4 porsi');

echo 'Seluruh pengujian ketersediaan stok lulus.' . PHP_EOL;
