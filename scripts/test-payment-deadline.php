<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit("Not found\n");
}

require __DIR__ . '/../app/bootstrap.php';
require __DIR__ . '/../app/daily-quota.php';
require __DIR__ . '/../app/order-expiration.php';

$db = db();
$db->beginTransaction();

try {
    q("CREATE TEMP TABLE pesanan (
        id INT PRIMARY KEY,
        status VARCHAR(24) NOT NULL CHECK (status IN ('menunggu_pembayaran', 'menunggu_validasi', 'dibatalkan')),
        status_updated_at TIMESTAMPTZ,
        created_at TIMESTAMPTZ NOT NULL,
        tgl_kirim TIMESTAMPTZ NOT NULL
    ) ON COMMIT DROP");
    q('CREATE TEMP TABLE detail_pesanan (pesanan_id INT NOT NULL, produk_id INT NOT NULL, qty INT NOT NULL) ON COMMIT DROP');
    $productId = (int) q('SELECT id FROM produk ORDER BY id LIMIT 1')->fetchColumn();
    $delivery = '2999-12-30 12:00:00+07';
    q("INSERT INTO pesanan (id, status, created_at, tgl_kirim) VALUES
        (1, 'menunggu_pembayaran', now() - interval '61 minutes', ?),
        (2, 'menunggu_pembayaran', now() - interval '59 minutes', ?),
        (3, 'menunggu_validasi', now() - interval '2 hours', ?)",
        [$delivery, $delivery, $delivery]);
    q('INSERT INTO detail_pesanan (pesanan_id, produk_id, qty) VALUES (1, ?, 3), (2, ?, 4), (3, ?, 5)', [
        $productId,
        $productId,
        $productId,
    ]);

    $quotaBefore = daily_quota_snapshot('2999-12-30');
    $expiredCount = expire_unpaid_orders();
    $statuses = q('SELECT id, status FROM pesanan ORDER BY id')->fetchAll(PDO::FETCH_KEY_PAIR);
    $quotaAfter = daily_quota_snapshot('2999-12-30');
    if ($expiredCount !== 1
        || $statuses[1] !== 'dibatalkan'
        || $statuses[2] !== 'menunggu_pembayaran'
        || $statuses[3] !== 'menunggu_validasi'
        || (int) $quotaBefore['items'][0]['terpakai'] !== 12
        || (int) $quotaAfter['items'][0]['terpakai'] !== 9) {
        throw new RuntimeException('Automatic expiration did not cancel only overdue unpaid orders.');
    }

    $quotaSource = file_get_contents(__DIR__ . '/../app/daily-quota.php');
    $migration = file_get_contents(__DIR__ . '/../database/migrations/018_payment_deadline.sql');
    $customerJs = file_get_contents(__DIR__ . '/../public/pelanggan/assets/customer.js');
    $checkoutLayout = file_get_contents(__DIR__ . '/../public/pelanggan/partials/layout.php');
    if (!str_contains($quotaSource, "p.status NOT IN ('ditolak', 'dibatalkan')")
        || !str_contains($migration, "p.status NOT IN ('ditolak', 'dibatalkan')")
        || !str_contains($customerJs, 'Batas maksimal pembayaran 1 jam')
        || !str_contains($checkoutLayout, 'Batas maksimal pembayaran: 1 jam')) {
        throw new RuntimeException('Cancelled orders must be excluded from daily quota usage.');
    }

    echo "PASS: Only unpaid orders older than one hour are canceled; canceled orders release quota.\n";
} finally {
    $db->rollBack();
}
