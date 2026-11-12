<?php
require __DIR__ . '/../../../app/bootstrap.php';
require_role('pelanggan');
require_once __DIR__ . '/../../../app/daily-quota.php';
require_once __DIR__ . '/../../../app/order-expiration.php';
expire_unpaid_orders();

header('Cache-Control: no-store');
$date = $_GET['tgl_kirim'] ?? (new DateTimeImmutable('now', new DateTimeZone('Asia/Jakarta')))->modify('+7 days')->format('Y-m-d');

try {
    $snapshot = daily_quota_snapshot($date);
} catch (DomainException $e) {
    json(['error' => $e->getMessage()], 422);
}

$category = $_GET['kategori'] ?? '';
$query = trim($_GET['q'] ?? '');
$items = array_values(array_filter($snapshot['items'], static function ($product) use ($category, $query) {
    return (int) $product['kuota_harian'] > 0
        && (!in_array($category, ['makanan', 'minuman', 'snack'], true) || $product['kategori'] === $category)
        && ($query === '' || stripos($product['nama'], $query) !== false || stripos($product['kode'], $query) !== false);
}));
// SELECT memilih satu jadwal PO aktif yang tanggal bukanya paling awal.
$schedule = q('SELECT id, tgl_buka, tgl_tutup, nama FROM jadwal_po WHERE aktif ORDER BY tgl_buka LIMIT 1')->fetch();

json([
    'items' => $items,
    'schedule' => $schedule,
    'tanggal' => $snapshot['tanggal'],
    'maxKitchen' => $snapshot['maxKitchen'],
    'kitchenUsed' => $snapshot['kitchenUsed'],
    'kitchenRemaining' => $snapshot['kitchenRemaining'],
]);
