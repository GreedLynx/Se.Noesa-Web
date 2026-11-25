<?php
require __DIR__ . '/../../../app/bootstrap.php';
require_role('admin');
require_once __DIR__ . '/../../../app/late-payment.php';

header('Cache-Control: no-store');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    $request = body();
    $action = (string) ($request['action'] ?? '');
    try {
        if ($action === 'record') {
            $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
            if (!$id || $id < 1) json(['error' => 'ID pesanan tidak valid'], 422);
            $file = $_FILES['proof'] ?? null;
            if (!$file) json(['error' => 'Pilih file bukti pembayaran'], 422);
            $row = late_payment_record($id, $request, $file, (int) me()['id']);
            json(['ok' => true, 'id' => (int) ($row['id'] ?? 0)]);
        }
        if ($action === 'verify') {
            $id = filter_var($request['id'] ?? null, FILTER_VALIDATE_INT);
            if (!$id || $id < 1) json(['error' => 'ID rekonsiliasi tidak valid'], 422);
            $row = late_payment_verify($id, (string) ($request['status'] ?? ''),
                (string) ($request['hasil_catatan'] ?? ''), (int) me()['id']);
            json(['ok' => true, 'status' => $row['status_rekonsiliasi'] ?? null]);
        }
        if ($action === 'follow_up') {
            $id = filter_var($request['id'] ?? null, FILTER_VALIDATE_INT);
            if (!$id || $id < 1) json(['error' => 'ID rekonsiliasi tidak valid'], 422);
            late_payment_follow_up($id, $request, (int) me()['id']);
            json(['ok' => true]);
        }
        json(['error' => 'Aksi tidak valid'], 422);
    } catch (DomainException $e) {
        json(['error' => $e->getMessage()], 422);
    } catch (Throwable $e) {
        error_log('[Se.Noesa late payment] ' . $e->getMessage());
        json(['error' => 'Rekonsiliasi gagal disimpan. Coba lagi.'], 500);
    }
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') json(['error' => 'Method tidak didukung'], 405);

if (!empty($_GET['eligible'])) {
    if (!late_payment_table_available()) json(['items' => [], 'feature_available' => false]);
    json(['items' => q("SELECT p.id, p.kode, p.nama_pemesan, p.telepon_penerima, p.total,
            p.created_at, p.tgl_kirim, p.status
        FROM pesanan p
        WHERE p.status = 'dibatalkan'
          AND NOT EXISTS (SELECT 1 FROM pembayaran_terlambat r WHERE r.pesanan_id = p.id)
        ORDER BY p.created_at DESC LIMIT 100")->fetchAll(), 'feature_available' => true]);
}

$id = isset($_GET['id']) ? filter_var($_GET['id'], FILTER_VALIDATE_INT) : null;
if ($id !== null && $id > 0) {
    $row = late_payment_get($id);
    if (!$row) json(['error' => 'Catatan rekonsiliasi tidak ditemukan'], 404);
    $row['bukti_url'] = 'api/proof.php?late=' . (int) $row['id'];
    json(['item' => $row, 'logs' => late_payment_logs($id)]);
}
$available = late_payment_table_available();
json(['items' => $available ? late_payment_list() : [], 'feature_available' => $available]);
