<?php
// Proof stream khusus Admin. File tidak pernah disajikan lewat URL publik langsung.
require __DIR__ . '/../../../app/bootstrap.php';
require_role('admin');
require_once __DIR__ . '/../../../app/proof-storage.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') json(['error' => 'Method tidak didukung'], 405);

$lateId = isset($_GET['late']) ? filter_var($_GET['late'], FILTER_VALIDATE_INT) : null;
if ($lateId && $lateId > 0) {
    $row = q('SELECT bukti_path FROM pembayaran_terlambat WHERE id = ?', [$lateId])->fetch();
    if (!$row) json(['error' => 'Bukti tidak ditemukan'], 404);
    proof_stream($row['bukti_path']);
}

$orderId = isset($_GET['order']) ? filter_var($_GET['order'], FILTER_VALIDATE_INT) : null;
if ($orderId && $orderId > 0) {
    $row = q('SELECT bukti_bayar FROM pesanan WHERE id = ?', [$orderId])->fetch();
    if (!$row || empty($row['bukti_bayar'])) json(['error' => 'Bukti tidak ditemukan'], 404);
    proof_stream($row['bukti_bayar']);
}
json(['error' => 'Parameter tidak valid'], 422);
