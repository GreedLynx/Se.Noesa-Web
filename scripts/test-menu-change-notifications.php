<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit("Not found\n");
}

require __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/menu-notifications.php';

$pdo = db();
$pdo->beginTransaction();

try {
    $schema = 'test_menu_notice_' . bin2hex(random_bytes(6));
    $pdo->exec('CREATE SCHEMA "' . $schema . '"');
    $pdo->exec('SET LOCAL search_path TO "' . $schema . '"');
    $pdo->exec('CREATE TABLE users (
        id SERIAL PRIMARY KEY,
        username VARCHAR(50) NOT NULL,
        email VARCHAR(100),
        phone VARCHAR(20)
    )');
    $pdo->exec('CREATE TABLE produk (
        id SERIAL PRIMARY KEY,
        kode VARCHAR(10) NOT NULL,
        nama VARCHAR(100) NOT NULL,
        kategori VARCHAR(20) NOT NULL DEFAULT \'makanan\',
        harga INT NOT NULL,
        gambar VARCHAR(200),
        min_pesan INT NOT NULL DEFAULT 1,
        kuota_harian INT NOT NULL DEFAULT 0
    )');
    $pdo->exec('CREATE TABLE pesanan (
        id SERIAL PRIMARY KEY,
        kode VARCHAR(20) NOT NULL UNIQUE,
        user_id INT REFERENCES users(id),
        status VARCHAR(24) NOT NULL,
        sumber VARCHAR(10) NOT NULL,
        total INT NOT NULL,
        tgl_kirim TIMESTAMPTZ NOT NULL,
        nama_pemesan VARCHAR(100),
        telepon_penerima VARCHAR(20)
    )');
    $pdo->exec('CREATE TABLE detail_pesanan (
        id SERIAL PRIMARY KEY,
        pesanan_id INT REFERENCES pesanan(id) ON DELETE CASCADE,
        produk_id INT REFERENCES produk(id),
        qty INT NOT NULL,
        harga INT NOT NULL,
        sudah_dicek BOOLEAN NOT NULL DEFAULT FALSE
    )');
    $pdo->exec('CREATE TABLE kuota_harian (
        id SERIAL PRIMARY KEY,
        produk_id INT NOT NULL REFERENCES produk(id),
        tanggal DATE NOT NULL,
        kuota INT NOT NULL,
        UNIQUE (produk_id, tanggal)
    )');

    $insertId = static function (string $sql, array $params = []) use ($pdo): int {
        $statement = $pdo->prepare($sql);
        $statement->execute($params);
        return (int) $statement->fetchColumn();
    };

    $adminId = $insertId("INSERT INTO users (username) VALUES ('admin-test') RETURNING id");
    $customerId = $insertId("INSERT INTO users (username) VALUES ('customer-test') RETURNING id");
    $otherCustomerId = $insertId("INSERT INTO users (username) VALUES ('other-customer-test') RETURNING id");
    $productId = $insertId(
        "INSERT INTO produk (kode, nama, harga, kuota_harian)
         VALUES ('OLD-01', 'Menu Lama', 12000, 0) RETURNING id"
    );
    $replacementId = $insertId(
        "INSERT INTO produk (kode, nama, harga, kuota_harian)
         VALUES ('ALT-01', 'Menu Pengganti', 15000, 10) RETURNING id"
    );
    $outOfStockId = $insertId(
        "INSERT INTO produk (kode, nama, harga, kuota_harian)
         VALUES ('OUT-01', 'Menu Habis', 14000, 4) RETURNING id"
    );
    $highMinimumId = $insertId(
        "INSERT INTO produk (kode, nama, harga, min_pesan, kuota_harian)
         VALUES ('MIN-01', 'Minimum Tinggi', 13000, 8, 10) RETURNING id"
    );
    $deliveryDate = '2026-11-15 12:00:00+07';
    $onlineOrderId = $insertId(
        "INSERT INTO pesanan (kode, user_id, status, sumber, total, tgl_kirim)
         VALUES ('SN-TEST-ONLINE', ?, 'diproses', 'online', 48000, ?) RETURNING id",
        [$customerId, $deliveryDate]
    );
    $onlineDetailId = $insertId(
        'INSERT INTO detail_pesanan (pesanan_id, produk_id, qty, harga) VALUES (?, ?, 4, 12000) RETURNING id',
        [$onlineOrderId, $productId]
    );
    $offlineOrderId = $insertId(
        "INSERT INTO pesanan (kode, user_id, status, sumber, total, tgl_kirim)
         VALUES ('SN-TEST-OFFLINE', NULL, 'diproses', 'offline', 36000, ?) RETURNING id",
        [$deliveryDate]
    );
    $offlineDetailId = $insertId(
        'INSERT INTO detail_pesanan (pesanan_id, produk_id, qty, harga) VALUES (?, ?, 3, 12000) RETURNING id',
        [$offlineOrderId, $productId]
    );
    $usedOrderId = $insertId(
        "INSERT INTO pesanan (kode, user_id, status, sumber, total, tgl_kirim)
         VALUES ('SN-TEST-USED', NULL, 'diproses', 'offline', 56000, ?) RETURNING id",
        [$deliveryDate]
    );
    $insertId(
        'INSERT INTO detail_pesanan (pesanan_id, produk_id, qty, harga) VALUES (?, ?, 4, 14000) RETURNING id',
        [$usedOrderId, $outOfStockId]
    );
    $ordersBefore = (int) $pdo->query('SELECT COUNT(*) FROM pesanan')->fetchColumn();
    $detailsBefore = (int) $pdo->query('SELECT COUNT(*) FROM detail_pesanan')->fetchColumn();

    $migration = file_get_contents(__DIR__ . '/../database/migrations/021_menu_change_notifications.sql');
    if ($migration === false || trim($migration) === '') {
        throw new RuntimeException('Migration 021 is empty or unreadable.');
    }
    $pdo->exec($migration);
    $pdo->exec($migration);
    $confirmationMigration = file_get_contents(
        __DIR__ . '/../database/migrations/022_menu_change_confirmation_audit.sql'
    );
    if ($confirmationMigration === false || trim($confirmationMigration) === '') {
        throw new RuntimeException('Migration 022 is empty or unreadable.');
    }
    $pdo->exec($confirmationMigration);
    $pdo->exec($confirmationMigration);

    if ((int) $pdo->query('SELECT COUNT(*) FROM pesanan')->fetchColumn() !== $ordersBefore
        || (int) $pdo->query('SELECT COUNT(*) FROM detail_pesanan')->fetchColumn() !== $detailsBefore) {
        throw new RuntimeException('Migration changed existing order or detail rows.');
    }

    $finishedOrderId = $insertId(
        "INSERT INTO pesanan (kode, user_id, status, sumber, total, tgl_kirim)
         VALUES ('SN-TEST-FINISHED', ?, 'selesai', 'online', 48000, ?) RETURNING id",
        [$customerId, $deliveryDate]
    );
    $insertId(
        'INSERT INTO detail_pesanan (pesanan_id, produk_id, qty, harga) VALUES (?, ?, 4, 12000) RETURNING id',
        [$finishedOrderId, $productId]
    );

    $targets = menu_notice_targets($productId);
    if (count($targets) !== 2
        || (int) $targets[0]['detail_pesanan_id'] !== $onlineDetailId
        || (int) $targets[0]['kuota_efektif'] !== 0
        || !empty($targets[0]['sudah_dinotifikasi'])
        || (int) $targets[1]['detail_pesanan_id'] !== $offlineDetailId) {
        throw new RuntimeException('Target selection did not include active online/offline orders with zero quota only.');
    }

    $pdo->prepare('INSERT INTO kuota_harian (produk_id, tanggal, kuota) VALUES (?, ?, 5)')
        ->execute([$productId, '2026-11-15']);
    if (menu_notice_targets($productId)) {
        throw new RuntimeException('Positive date override should make the menu available and remove targets.');
    }
    $pdo->prepare('DELETE FROM kuota_harian WHERE produk_id = ? AND tanggal = ?')
        ->execute([$productId, '2026-11-15']);

    $available = menu_notice_available_products('2026-11-15', $productId, 4);
    if (array_column($available, 'id') !== [$replacementId]) {
        throw new RuntimeException('Replacement availability did not enforce remaining quota and minimum quantity.');
    }

    $noticeId = $insertId(
        'INSERT INTO pemberitahuan_perubahan_menu
            (produk_id, kode_produk_snapshot, nama_produk_snapshot, catatan_admin, created_by)
         VALUES (?, ?, ?, ?, ?) RETURNING id',
        [$productId, 'OLD-01', 'Menu Lama', 'Menu sedang tidak tersedia.', $adminId]
    );
    $optionId = $insertId(
        'INSERT INTO pemberitahuan_perubahan_menu_opsi (pemberitahuan_id, kode_opsi, label)
         VALUES (?, ?, ?) RETURNING id',
        [$noticeId, 'menu_pengganti', 'Pilih menu pengganti']
    );
    $insertRecipient = static function (
        int $notificationId,
        int $detailId,
        int $quantity = 4,
        int $price = 12000
    ) use ($pdo, $productId): int {
        $statement = $pdo->prepare(
            'INSERT INTO pemberitahuan_perubahan_menu_pesanan
                (pemberitahuan_id, detail_pesanan_id, produk_lama_id,
                 kode_produk_lama_snapshot, nama_produk_lama_snapshot, qty_lama, harga_lama)
             VALUES (?, ?, ?, ?, ?, ?, ?) RETURNING id'
        );
        $statement->execute([
            $notificationId,
            $detailId,
            $productId,
            'OLD-01',
            'Menu Lama',
            $quantity,
            $price,
        ]);
        return (int) $statement->fetchColumn();
    };
    $recipientId = $insertRecipient($noticeId, $onlineDetailId);
    $offlineRecipientId = $insertRecipient($noticeId, $offlineDetailId, 3);
    if (count(menu_notice_order_notifications($onlineOrderId, $customerId)) !== 1
        || menu_notice_order_notifications($onlineOrderId, $otherCustomerId)
        || menu_notice_order_notifications($offlineOrderId, $customerId)) {
        throw new RuntimeException('Order-detail notifications were not isolated to the owning online customer.');
    }
    if (menu_notice_customer_unread_count($customerId) !== 1
        || menu_notice_customer_unread_count($otherCustomerId) !== 0) {
        throw new RuntimeException('Unread notification count was not limited to the owning online customer.');
    }
    foreach (menu_notice_targets($productId) as $target) {
        if (empty($target['sudah_dinotifikasi'])) {
            throw new RuntimeException('An existing active response was not detected as already notified.');
        }
    }
    $pdo->prepare('UPDATE pemberitahuan_perubahan_menu_pesanan SET dibaca_at = now() WHERE id = ?')
        ->execute([$recipientId]);
    if (menu_notice_customer_unread_count($customerId) !== 0) {
        throw new RuntimeException('Marking a notification as read did not clear the unread count.');
    }

    $secondNoticeId = $insertId(
        'INSERT INTO pemberitahuan_perubahan_menu
            (produk_id, kode_produk_snapshot, nama_produk_snapshot, catatan_admin, created_by)
         VALUES (?, ?, ?, ?, ?) RETURNING id',
        [$productId, 'OLD-01', 'Menu Lama', 'Pemberitahuan kedua.', $adminId]
    );
    $otherOptionId = $insertId(
        'INSERT INTO pemberitahuan_perubahan_menu_opsi (pemberitahuan_id, kode_opsi, label)
         VALUES (?, ?, ?) RETURNING id',
        [$secondNoticeId, 'hubungi_admin', 'Hubungi Admin']
    );
    $followUpOptionId = $insertId(
        'INSERT INTO pemberitahuan_perubahan_menu_opsi (pemberitahuan_id, kode_opsi, label)
         VALUES (?, ?, ?) RETURNING id',
        [$noticeId, 'hubungi_admin', 'Hubungi Admin']
    );

    $expectConstraint = static function (string $expectedState, string $description, callable $action) use ($pdo): void {
        $pdo->exec('SAVEPOINT menu_notice_constraint_test');
        try {
            $action();
        } catch (PDOException $error) {
            $actualState = $error->errorInfo[0] ?? $error->getCode();
            $pdo->exec('ROLLBACK TO SAVEPOINT menu_notice_constraint_test');
            $pdo->exec('RELEASE SAVEPOINT menu_notice_constraint_test');
            if ((string) $actualState !== $expectedState) {
                throw new RuntimeException($description . ' failed with SQLSTATE ' . $actualState, 0, $error);
            }
            return;
        }
        $pdo->exec('ROLLBACK TO SAVEPOINT menu_notice_constraint_test');
        $pdo->exec('RELEASE SAVEPOINT menu_notice_constraint_test');
        throw new RuntimeException($description . ' was not rejected by its constraint.');
    };

    $expectConstraint('23505', 'Duplicate notification/detail pair', static function () use ($insertRecipient, $noticeId, $onlineDetailId): void {
        $insertRecipient($noticeId, $onlineDetailId);
    });
    $expectConstraint('23514', 'Unknown response status', static function () use ($pdo, $recipientId): void {
        $statement = $pdo->prepare(
            "UPDATE pemberitahuan_perubahan_menu_pesanan SET status_respons = 'status_tidak_valid' WHERE id = ?"
        );
        $statement->execute([$recipientId]);
    });
    $selectOwnOption = $pdo->prepare(
        "UPDATE pemberitahuan_perubahan_menu_pesanan
         SET opsi_id = ?, status_respons = 'menunggu_konfirmasi'
         WHERE id = ?"
    );
    $selectOwnOption->execute([$optionId, $recipientId]);
    $clearOption = $pdo->prepare(
        "UPDATE pemberitahuan_perubahan_menu_pesanan
         SET opsi_id = NULL, status_respons = 'menunggu_respons'
         WHERE id = ?"
    );
    $clearOption->execute([$recipientId]);
    $expectConstraint('23503', 'Option from another notification', static function () use ($pdo, $recipientId, $otherOptionId): void {
        $statement = $pdo->prepare(
            'UPDATE pemberitahuan_perubahan_menu_pesanan SET opsi_id = ? WHERE id = ?'
        );
        $statement->execute([$otherOptionId, $recipientId]);
    });

    $selectReplacement = $pdo->prepare(
        "UPDATE pemberitahuan_perubahan_menu_pesanan
         SET opsi_id = ?,
             status_respons = 'menunggu_konfirmasi',
             produk_pengganti_id = ?,
             kode_produk_pengganti_snapshot = 'ALT-01',
             nama_produk_pengganti_snapshot = 'Menu Pengganti',
             qty_pengganti = 4,
             harga_pengganti = 15000,
             responded_at = now()
         WHERE id = ?"
    );
    $selectReplacement->execute([$optionId, $replacementId, $recipientId]);
    $confirmation = menu_notice_confirm_replacement($recipientId, $adminId);
    if ($confirmation['selisih_total'] !== 12000
        || $confirmation['total_pesanan_lama'] !== 48000
        || $confirmation['total_pesanan_baru'] !== 60000
        || $confirmation['status_pesanan'] !== 'diproses') {
        throw new RuntimeException('Admin confirmation did not record the correct totals and price difference.');
    }
    $updatedDetail = $pdo->prepare('SELECT produk_id, harga, sudah_dicek FROM detail_pesanan WHERE id = ?');
    $updatedDetail->execute([$onlineDetailId]);
    if ($updatedDetail->fetch(PDO::FETCH_NUM) !== [$replacementId, 15000, false]) {
        throw new RuntimeException('Admin confirmation did not replace the order detail correctly.');
    }
    $updatedTotal = $pdo->prepare('SELECT total, status FROM pesanan WHERE id = ?');
    $updatedTotal->execute([$onlineOrderId]);
    if ($updatedTotal->fetch(PDO::FETCH_NUM) !== [60000, 'diproses']) {
        throw new RuntimeException('Admin confirmation changed the order total or status incorrectly.');
    }

    $settlementResult = menu_notice_settle_difference($recipientId, $adminId);
    if ($settlementResult['status_penyelesaian_selisih'] !== 'selesai') {
        throw new RuntimeException('Manual price-difference settlement was not recorded.');
    }

    $setOfflineFollowUp = $pdo->prepare(
        "UPDATE pemberitahuan_perubahan_menu_pesanan
         SET opsi_id = ?, status_respons = 'menunggu_tindak_lanjut', responded_at = now()
         WHERE id = ?"
    );
    $setOfflineFollowUp->execute([$followUpOptionId, $offlineRecipientId]);
    $followUp = menu_notice_complete_follow_up($offlineRecipientId, $adminId, 'Pelanggan sudah dihubungi.');
    if ($followUp['status_respons'] !== 'selesai') {
        throw new RuntimeException('Manual follow-up was not recorded.');
    }

    $expectConstraint('23503', 'Delete order with notification history', static function () use ($pdo, $onlineOrderId): void {
        $statement = $pdo->prepare('DELETE FROM pesanan WHERE id = ?');
        $statement->execute([$onlineOrderId]);
    });

    $orderStatus = $pdo->prepare('SELECT status FROM pesanan WHERE id = ?');
    $orderStatus->execute([$onlineOrderId]);
    if ($orderStatus->fetchColumn() !== 'diproses') {
        throw new RuntimeException('The migration or its notification data changed the order status.');
    }
    echo "PASS: migrations 021/022 are idempotent; target selection, ownership, unread, capacity, confirmation, price difference, manual follow-up, and history checks pass.\n";
} finally {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
}
