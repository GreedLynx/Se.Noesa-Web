<?php
require __DIR__ . '/../../../app/bootstrap.php';
require_role('admin');
require_once __DIR__ . '/../../../app/daily-quota.php';
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
    if (!(error_reporting() & $severity)) {
        return false;
    }
    throw new ErrorException($message, 0, $severity, $file, $line);
});
set_exception_handler(static function (Throwable $error): void {
    if (db()->inTransaction()) {
        db()->rollBack();
    }
    error_log('[Se.Noesa kuota-harian API] ' . $error);
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
    }
    echo json_encode(['error' => 'Kuota harian belum dapat disimpan'], JSON_UNESCAPED_UNICODE);
});

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $request = body();
    $action = $request['action'] ?? 'save';
    if (!in_array($action, ['save', 'reset', 'delete'], true)) {
        json(['error' => 'Aksi tidak valid'], 422);
    }

    if ($action === 'delete') {
        $id = filter_var($request['id'] ?? null, FILTER_VALIDATE_INT);
        if ($id === false || $id < 1) {
            json(['error' => 'ID tidak valid'], 422);
        }
        db()->beginTransaction();
        // Kunci tabel mencegah perubahan kuota bersamaan dengan checkout.
        q('LOCK TABLE kuota_harian IN SHARE ROW EXCLUSIVE MODE');
        // DELETE menghapus konfigurasi kuota harian berdasarkan ID.
        $affectedRows = q('DELETE
            FROM kuota_harian
            WHERE id = ?', [$id])->rowCount();
        db()->commit();
        json($affectedRows ? ['ok' => true] : ['error' => 'Kuota harian tidak ditemukan'], $affectedRows ? 200 : 404);
    }

    $produkId = filter_var($request['produk_id'] ?? null, FILTER_VALIDATE_INT);
    $tanggal = $request['tanggal_mulai'] ?? $request['tanggal'] ?? '';
    $tanggalSelesai = $request['tanggal_selesai'] ?? $tanggal;
    $kuota = filter_var($request['kuota'] ?? null, FILTER_VALIDATE_INT);

    if ($produkId === false || $produkId < 1) {
        json(['error' => 'produk_id tidak valid'], 422);
    }
    try {
        [$tanggal, $tanggalSelesai] = daily_quota_range($tanggal, $tanggalSelesai);
    } catch (DomainException $e) {
        json(['error' => $e->getMessage()], 422);
    }
    if ($action === 'save' && ($kuota === false || $kuota < 0 || $kuota > 2147483647)) {
        json(['error' => 'kuota harus angka 0 sampai 2147483647'], 422);
    }
    // Pastikan produk yang akan diberi kuota memang terdaftar.
    if (!q('SELECT id
        FROM produk
        WHERE id = ?', [$produkId])->fetchColumn()) {
        json(['error' => 'Produk tidak ditemukan'], 404);
    }
    db()->beginTransaction();
    // Serialisasi penulisan kuota agar tidak berbenturan dengan proses checkout.
    q('LOCK TABLE kuota_harian IN SHARE ROW EXCLUSIVE MODE');

    $affected = daily_quota_write_range($produkId, $tanggal, $tanggalSelesai, $action === 'reset' ? null : $kuota);
    db()->commit();
    json(['ok' => true, 'jumlah_tanggal' => $affected]);
}

$produkId = $_GET['produk_id'] ?? null;
$tanggal = $_GET['tanggal'] ?? null;
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json(['error' => 'Method tidak didukung'], 405);
}
if ($tanggal !== null) {
    try {
        daily_quota_snapshot($tanggal);
    } catch (DomainException $e) {
        json(['error' => $e->getMessage()], 422);
    }
}

if ($produkId !== null && $tanggal !== null) {
    // Ambil satu konfigurasi kuota untuk kombinasi produk dan tanggal.
    $row = q('SELECT id, produk_id, tanggal, kuota
        FROM kuota_harian
        WHERE produk_id = ? AND tanggal = ?', [(int) $produkId, $tanggal])->fetch();
    json($row ?: null);
}

if ($produkId !== null) {
    // Tampilkan seluruh konfigurasi kuota untuk produk, dimulai dari tanggal terbaru.
    $items = q('SELECT id, produk_id, tanggal, kuota
        FROM kuota_harian
        WHERE produk_id = ?
        ORDER BY tanggal DESC', [(int) $produkId])->fetchAll();
    json(['items' => $items]);
}

json(['items' => []]);
