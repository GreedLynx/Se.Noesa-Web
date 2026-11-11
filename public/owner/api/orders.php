<?php
// GET ?tab=validasi|proses&q= | GET ?id= | GET ?tgl_kirim=YYYY-MM-DD
require __DIR__ . '/../../../app/bootstrap.php';
require_owner();
require_once __DIR__ . '/../../../app/daily-quota.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json(['error' => 'Method tidak didukung'], 405);
}

if (isset($_GET['id'])) {
    // SELECT membaca satu pesanan berdasarkan ID.
    $order = q('SELECT * FROM pesanan WHERE id = ?', [$_GET['id']])->fetch() ?: json(['error' => 'Tidak ditemukan'], 404);
    if (!empty($order['bukti_bayar']) && !preg_match('~^(https?://|/)~i', $order['bukti_bayar'])) {
        $order['bukti_bayar'] = '../' . $order['bukti_bayar'];
    }

    // JOIN detail dengan produk agar nama menu dan subtotal per item ikut dikirim.
    $order['menu'] = q(
        'SELECT pr.nama, d.qty, d.harga, d.qty * d.harga AS sub
        FROM detail_pesanan d
        JOIN produk pr ON pr.id = d.produk_id
        WHERE d.pesanan_id = ?',
        [$order['id']]
    )->fetchAll();
    json($order);
}

$statusFilter = ($_GET['tab'] ?? 'validasi') === 'proses' ? "('diproses','selesai')" : "('menunggu_validasi')";
$search = '%' . ($_GET['q'] ?? '') . '%';
// SELECT memfilter pesanan berdasarkan tab dan kata kunci, lalu mengurutkan yang terbaru.
$orders = q(
    "SELECT id, kode, nama_pemesan, keterangan, total, status,
        to_char(COALESCE(status_updated_at, created_at), 'HH24:MI') AS jam
    FROM pesanan
    WHERE status IN $statusFilter AND (nama_pemesan ILIKE ? OR kode ILIKE ?)
    ORDER BY created_at DESC",
    [$search, $search]
)->fetchAll();
// COUNT FILTER menghitung jumlah pesanan menunggu validasi dan pesanan dalam proses/selesai.
$counts = q(
    "SELECT COUNT(*) FILTER (WHERE status = 'menunggu_validasi') AS v,
        COUNT(*) FILTER (WHERE status IN ('diproses', 'selesai')) AS p
    FROM pesanan"
)->fetch();
$date = $_GET['tgl_kirim'] ?? (new DateTimeImmutable('now', new DateTimeZone('Asia/Jakarta')))->format('Y-m-d');
try {
    $quota = daily_quota_snapshot($date, true);
} catch (DomainException $e) {
    json(['error' => $e->getMessage()], 422);
}
$capacity = ['kapasitas' => $quota['maxKitchen'], 'pakai' => $quota['kitchenUsed']];

json(['orders' => $orders, 'count' => $counts, 'kapasitas' => $capacity]);
