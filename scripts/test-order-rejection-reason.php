<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit("Not found\n");
}

require __DIR__ . '/../app/bootstrap.php';

$db = db();
$db->beginTransaction();

try {
    $hasColumn = q(
        "SELECT EXISTS (
            SELECT 1 FROM information_schema.columns
            WHERE table_schema = current_schema()
              AND table_name = 'pesanan'
              AND column_name = 'alasan_penolakan'
        )"
    )->fetchColumn();
    if (!$hasColumn) {
        throw new RuntimeException('Migration 020 has not added pesanan.alasan_penolakan.');
    }

    $code = 'SN-TEST-REJ-' . strtoupper(bin2hex(random_bytes(3)));
    $id = q(
        "INSERT INTO pesanan (kode, nama_pemesan, status)
         VALUES (?, 'Tes alasan penolakan', 'menunggu_validasi')
         RETURNING id",
        [$code]
    )->fetchColumn();
    $reason = 'Bukti transfer tidak menunjukkan nominal yang sesuai.';
    q(
        "UPDATE pesanan
         SET status = 'ditolak', status_updated_at = now(), alasan_penolakan = ?
         WHERE id = ?",
        [$reason, $id]
    );
    $savedOrder = q(
        'SELECT status, alasan_penolakan FROM pesanan WHERE id = ?',
        [$id]
    )->fetch();
    if ($savedOrder['status'] !== 'ditolak' || $savedOrder['alasan_penolakan'] !== $reason) {
        throw new RuntimeException('Rejection reason was not saved with the rejected order.');
    }

    $customerApi = file_get_contents(__DIR__ . '/../public/pelanggan/api/orders.php');
    $customerUi = file_get_contents(__DIR__ . '/../public/pelanggan/assets/customer.js');
    $adminUi = file_get_contents(__DIR__ . '/../public/admin/assets/admin.js');
    if (!str_contains($customerApi, 'alasan_penolakan')
        || !str_contains($customerUi, 'esc(d.alasan_penolakan)')
        || !str_contains($adminUi, 'esc(o.alasan_penolakan)')) {
        throw new RuntimeException('Rejection reason is not passed to both customer and admin details.');
    }

    echo "PASS: Rejection reason is stored with the declined order and exposed to escaped customer/admin detail views.\n";
} finally {
    $db->rollBack();
}
