<?php
require __DIR__ . '/../../../app/bootstrap.php';
require_role('pelanggan');
require_once __DIR__ . '/../../../app/daily-quota.php';

header('Cache-Control: no-store');
$date = $_GET['tgl_kirim'] ?? (new DateTimeImmutable('now', new DateTimeZone('Asia/Jakarta')))->modify('+7 days')->format('Y-m-d');

try {
    $snapshot = daily_quota_snapshot($date);
} catch (DomainException $e) {
    json(['error' => $e->getMessage()], 422);
}

$category = $_GET['kategori'] ?? '';
$query = trim($_GET['q'] ?? '');
$matchesView = static function (array $product) use ($category, $query): bool {
    return (!in_array($category, ['makanan', 'minuman', 'snack'], true) || $product['kategori'] === $category)
        && ($query === '' || stripos($product['nama'], $query) !== false || stripos($product['kode'], $query) !== false);
};
$items = array_values(array_filter($snapshot['items'], static function ($product) use ($matchesView) {
    return (int) $product['sisa'] > 0 && $matchesView($product);
}));
// Menu dengan sisa kuota 0 tetap diinformasikan agar Pelanggan memahami
// produk mana yang penuh pada tanggal pengiriman yang dipilih (IMG-CUS-01).
$habis = array_map(static function (array $product): array {
    return [
        'id' => (int) $product['id'],
        'nama' => $product['nama'],
        'kode' => $product['kode'],
        'sisa' => (int) $product['sisa'],
    ];
}, array_values(array_filter($snapshot['items'], static function ($product) use ($matchesView) {
    return (int) $product['sisa'] <= 0 && $matchesView($product);
})));
// SELECT memilih satu jadwal PO aktif yang tanggal bukanya paling awal.
$schedule = q('SELECT id, tgl_buka, tgl_tutup, nama FROM jadwal_po WHERE aktif ORDER BY tgl_buka LIMIT 1')->fetch();

json([
    'items' => $items,
    'habis' => $habis,
    'schedule' => $schedule,
    'tanggal' => $snapshot['tanggal'],
    'tanggal_libur' => $snapshot['tanggal_libur'],
    'maxKitchen' => $snapshot['maxKitchen'],
    'kitchenUsed' => $snapshot['kitchenUsed'],
    'kitchenRemaining' => $snapshot['kitchenRemaining'],
]);
