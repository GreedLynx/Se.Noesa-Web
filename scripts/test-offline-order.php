<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit("Not found\n");
}

require __DIR__ . '/../app/bootstrap.php';
require __DIR__ . '/../app/daily-quota.php';
require __DIR__ . '/../app/offline-orders.php';

$db = db();
$db->beginTransaction();

try {
    $now = new DateTimeImmutable('now', new DateTimeZone('Asia/Jakarta'));
    $fixture = null;
    for ($offset = 1; $offset <= 60 && !$fixture; $offset++) {
        $deliveryDate = $now->modify("+$offset days")->format('Y-m-d');
        $snapshot = daily_quota_snapshot($deliveryDate);
        foreach ($snapshot['items'] as $product) {
            $quantity = (int) $product['min_pesan'];
            if ((int) $product['sisa'] >= $quantity && $snapshot['kitchenRemaining'] >= $quantity) {
                $fixture = [$deliveryDate, $product, $quantity];
                break;
            }
        }
    }
    if (!$fixture) {
        throw new RuntimeException('No product/date is available for the offline-order test.');
    }

    [$deliveryDate, $product, $quantity] = $fixture;
    $result = create_offline_order([
        'nama_pemesan' => 'Tes Pesanan Offline',
        'nama_penerima' => 'Penerima Tes',
        'telepon' => '08123456789',
        'alamat' => 'Alamat pengujian',
        'tgl_kirim' => $deliveryDate,
        'waktu_kirim' => '12:00',
        'items' => [['id' => (int) $product['id'], 'qty' => $quantity]],
    ]);

    $savedOrder = q('SELECT status, sumber FROM pesanan WHERE id = ?', [$result['id']])->fetch();
    $savedItem = q(
        'SELECT qty FROM detail_pesanan WHERE pesanan_id = ? AND produk_id = ?',
        [$result['id'], $product['id']]
    )->fetchColumn();
    $after = daily_quota_snapshot($deliveryDate);
    $productAfter = array_column($after['items'], null, 'id')[$product['id']];

    if ($savedOrder['status'] !== 'diproses'
        || $savedOrder['sumber'] !== 'offline'
        || (int) $savedItem !== $quantity
        || (int) $productAfter['terpakai'] !== (int) $product['terpakai'] + $quantity) {
        throw new RuntimeException('Offline order did not save as processing or update the selected date quota.');
    }

    try {
        create_offline_order([
            'nama_pemesan' => 'Tes Kuota Penuh',
            'nama_penerima' => 'Penerima Tes',
            'telepon' => '08123456789',
            'alamat' => 'Alamat pengujian',
            'tgl_kirim' => $deliveryDate,
            'waktu_kirim' => '12:00',
            'items' => [['id' => (int) $product['id'], 'qty' => (int) $product['kuota'] + 1]],
        ]);
        throw new RuntimeException('Offline order exceeding product quota was accepted.');
    } catch (DomainException) {
    }

    echo "PASS: Offline order saves in processing, consumes only its delivery-date quota, and rejects over-quota quantities.\n";
} finally {
    $db->rollBack();
}
