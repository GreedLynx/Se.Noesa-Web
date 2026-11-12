<?php
require __DIR__ . '/../../../app/bootstrap.php';
require_role('admin');
require_once __DIR__ . '/../../../app/daily-quota.php';

$today = (new DateTimeImmutable('today'))->format('Y-m-d');
// COUNT dengan FILTER menghitung pesanan hari ini dan yang menunggu validasi dalam satu query.
$stats = q(
    "SELECT COUNT(*) FILTER (WHERE created_at::date = ?) AS orders,
        COUNT(*) FILTER (WHERE status = 'menunggu_validasi') AS pending
    FROM pesanan",
    [$today]
)->fetch();
$date = $_GET['tgl_kirim'] ?? (new DateTimeImmutable('now', new DateTimeZone('Asia/Jakarta')))->format('Y-m-d');
// Ambil ringkasan kapasitas dan pemakaian kuota untuk tanggal pengiriman yang diminta.
try {
    $quota = daily_quota_snapshot($date, true);
} catch (DomainException $e) {
    json(['error' => $e->getMessage()], 422);
}

$productionRows = q(
    'SELECT produk_id, menu, pesanan_id, kode, status, porsi
    FROM v_rekap_produksi_harian
    WHERE tanggal = ?
    ORDER BY menu, produk_id, pesanan_id',
    [$quota['tanggal']]
)->fetchAll();
$production = [];
foreach ($productionRows as $row) {
    $productId = (int) $row['produk_id'];
    if (!isset($production[$productId])) {
        $production[$productId] = [
            'produk_id' => $productId,
            'menu' => $row['menu'],
            'total_porsi' => 0,
            'pesanan' => [],
        ];
    }
    $production[$productId]['total_porsi'] += (int) $row['porsi'];
    $production[$productId]['pesanan'][] = [
        'id' => (int) $row['pesanan_id'],
        'kode' => $row['kode'],
        'status' => $row['status'],
        'porsi' => (int) $row['porsi'],
    ];
}

$capacity = ['kapasitas' => $quota['maxKitchen'], 'pakai' => $quota['kitchenUsed']];
$critical = count(array_filter(
    $quota['items'],
    static fn($product) => (int) $product['kuota'] === 0 || (int) $product['sisa'] <= (int) $product['kuota'] * 0.2
));
if ($quota['tanggal_libur']) {
    $critical = 0;
}
// LEFT JOIN ke rentang tujuh hari dan COUNT FILTER menghasilkan grafik pesanan selesai per hari.
$chart = q(
    "SELECT d::date AS hari, to_char(d, 'Dy') AS label,
        COUNT(p.id) FILTER (WHERE p.status = 'selesai') AS jumlah
    FROM generate_series(current_date - 6, current_date, '1 day') d
    LEFT JOIN pesanan p ON p.created_at::date = d
    GROUP BY d
    ORDER BY d"
)->fetchAll();
// SELECT mengambil lima pesanan tertua yang menunggu validasi untuk dijadikan prioritas.
$priority = q(
    "SELECT p.id, p.kode, p.nama_pemesan, p.keterangan, p.pax, p.total, p.status, p.tgl_kirim, p.created_at
    FROM pesanan p
    WHERE p.status = 'menunggu_validasi'
    ORDER BY p.created_at ASC
    LIMIT 5"
)->fetchAll();
// SELECT mengambil enam pesanan terbaru tanpa membatasi status.
$recent = q(
    'SELECT p.id, p.kode, p.nama_pemesan, p.total, p.status, p.created_at
    FROM pesanan p
    ORDER BY p.created_at DESC
    LIMIT 6'
)->fetchAll();

json([
    'orders' => (int) $stats['orders'],
    'pending' => (int) $stats['pending'],
    'capacity' => (int) $capacity['kapasitas'],
    'used' => (int) $capacity['pakai'],
    'production_date' => $quota['tanggal'],
    'production' => array_values($production),
    'tanggal_libur' => $quota['tanggal_libur'],
    'critical' => $critical,
    'chart' => $chart,
    'priority' => $priority,
    'recent' => $recent,
]);
