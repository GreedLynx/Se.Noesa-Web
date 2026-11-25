<?php
// Proof stream khusus pelanggan pemilik pesanan. Tidak ada tautan publik langsung.
require __DIR__ . '/../../../app/bootstrap.php';
require_role('pelanggan');
require_once __DIR__ . '/../../../app/proof-storage.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') json(['error' => 'Method tidak didukung'], 405);

$orderId = isset($_GET['order']) ? filter_var($_GET['order'], FILTER_VALIDATE_INT) : null;
if (!$orderId || $orderId < 1) json(['error' => 'ID pesanan tidak valid'], 422);
$row = q('SELECT bukti_bayar FROM pesanan WHERE id = ? AND user_id = ?',
    [$orderId, me()['id']])->fetch();
if (!$row || empty($row['bukti_bayar'])) json(['error' => 'Bukti tidak ditemukan'], 404);
proof_stream($row['bukti_bayar']);
