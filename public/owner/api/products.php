<?php
// GET ?kategori=makanan|minuman|snack|kritis&q=
require __DIR__ . '/../../../app/bootstrap.php';
require_owner();
require_once __DIR__ . '/../../../app/daily-quota.php';

$query = trim($_GET['q'] ?? '');
$category = $_GET['kategori'] ?? '';
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json(['error' => 'Method tidak didukung'], 405);
}

$date = $_GET['tgl_kirim'] ?? (new DateTimeImmutable('now', new DateTimeZone('Asia/Jakarta')))->format('Y-m-d');
try {
    $snapshot = daily_quota_snapshot($date, true);
} catch (DomainException $e) {
    json(['error' => $e->getMessage()], 422);
}

$critical = static fn($product) => !$snapshot['tanggal_libur'] && ((int) $product['kuota'] === 0 || (int) $product['sisa'] <= (int) $product['kuota'] * 0.2);
$items = array_values(array_filter($snapshot['items'], static function ($product) use ($query, $category, $critical) {
    return (!in_array($category, ['makanan', 'minuman', 'snack'], true) || $product['kategori'] === $category)
        && ($category !== 'kritis' || $critical($product))
        && ($query === '' || stripos($product['nama'], $query) !== false || stripos($product['kode'], $query) !== false);
}));

json([
    'items' => $items,
    'count' => [
        'semua' => count($snapshot['items']),
        'kritis' => count(array_filter($snapshot['items'], $critical)),
    ],
    'tanggal' => $date,
    'tanggal_libur' => $snapshot['tanggal_libur'],
    'maxKitchen' => $snapshot['maxKitchen'],
    'kitchenUsed' => $snapshot['kitchenUsed'],
    'kitchenRemaining' => $snapshot['kitchenRemaining'],
]);
