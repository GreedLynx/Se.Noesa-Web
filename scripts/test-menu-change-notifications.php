<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit("Not found\n");
}

require __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/daily-quota.php';
require_once __DIR__ . '/../app/menu-notifications.php';
require_once __DIR__ . '/../app/offline-orders.php';

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
    $pdo->exec('CREATE TABLE jadwal_po (
        id SERIAL PRIMARY KEY,
        nama VARCHAR(80),
        tgl_buka DATE,
        tgl_tutup DATE,
        kapasitas INT NOT NULL DEFAULT 0,
        aktif BOOLEAN NOT NULL DEFAULT TRUE
    )');
    $pdo->exec('CREATE TABLE pesanan (
        id SERIAL PRIMARY KEY,
        kode VARCHAR(20) NOT NULL UNIQUE,
        user_id INT REFERENCES users(id),
        jadwal_id INT REFERENCES jadwal_po(id),
        status VARCHAR(24) NOT NULL,
        sumber VARCHAR(10) NOT NULL,
        total INT NOT NULL,
        tgl_kirim TIMESTAMPTZ NOT NULL,
        nama_pemesan VARCHAR(100),
        keterangan VARCHAR(120),
        pax INT NOT NULL DEFAULT 0,
        status_updated_at TIMESTAMPTZ DEFAULT now(),
        alamat_pengiriman TEXT,
        telepon_penerima VARCHAR(20),
        bukti_bayar TEXT,
        nama_pembayar VARCHAR(100)
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
    $pdo->exec('CREATE TABLE tanggal_libur_operasional (
        id SERIAL PRIMARY KEY,
        tanggal_mulai DATE NOT NULL,
        tanggal_selesai DATE NOT NULL,
        CHECK (tanggal_selesai >= tanggal_mulai)
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
         VALUES ('ALT-01', 'Menu Pengganti', 15000, 100) RETURNING id"
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
    $closureMigration = file_get_contents(
        __DIR__ . '/../database/migrations/023_menu_change_closure_audit.sql'
    );
    if ($closureMigration === false || trim($closureMigration) === '') {
        throw new RuntimeException('Migration 023 is empty or unreadable.');
    }
    $pdo->exec($closureMigration);
    $pdo->exec($closureMigration);
    $priceAuditMigration = file_get_contents(
        __DIR__ . '/../database/migrations/024_menu_change_price_audit.sql'
    );
    if ($priceAuditMigration === false || trim($priceAuditMigration) === '') {
        throw new RuntimeException('Migration 024 is empty or unreadable.');
    }
    $pdo->exec($priceAuditMigration);
    $pdo->exec($priceAuditMigration);

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

    $holidayDate = '2027-01-01';
    $normalDate = '2027-01-03';
    $holidayTestProductId = $insertId(
        "INSERT INTO produk (kode, nama, harga, kuota_harian)
         VALUES ('HOL-01', 'Menu Libur', 15000, 20) RETURNING id"
    );
    $pdo->prepare(
        'INSERT INTO tanggal_libur_operasional (tanggal_mulai, tanggal_selesai) VALUES (?, ?)'
    )->execute(['2026-12-31', '2027-01-02']);
    // Override positif pada tanggal libur tetap harus dianggap tidak tersedia.
    $pdo->prepare('INSERT INTO kuota_harian (produk_id, tanggal, kuota) VALUES (?, ?, 30)')
        ->execute([$holidayTestProductId, $holidayDate]);
    $pdo->prepare('INSERT INTO kuota_harian (produk_id, tanggal, kuota) VALUES (?, ?, 12)')
        ->execute([$holidayTestProductId, $normalDate]);

    $holidayOrderId = $insertId(
        "INSERT INTO pesanan (kode, user_id, status, sumber, total, tgl_kirim)
         VALUES ('SN-TEST-HOLIDAY', ?, 'diproses', 'online', 30000, ?) RETURNING id",
        [$customerId, $holidayDate . ' 12:00:00+07']
    );
    $holidayDetailId = $insertId(
        'INSERT INTO detail_pesanan (pesanan_id, produk_id, qty, harga) VALUES (?, ?, 2, 15000) RETURNING id',
        [$holidayOrderId, $holidayTestProductId]
    );
    $normalOrderId = $insertId(
        "INSERT INTO pesanan (kode, user_id, status, sumber, total, tgl_kirim)
         VALUES ('SN-TEST-NORMAL', ?, 'diproses', 'online', 15000, ?) RETURNING id",
        [$customerId, $normalDate . ' 12:00:00+07']
    );
    $normalDetailId = $insertId(
        'INSERT INTO detail_pesanan (pesanan_id, produk_id, qty, harga) VALUES (?, ?, 1, 15000) RETURNING id',
        [$normalOrderId, $holidayTestProductId]
    );
    $inactiveHolidayOrderId = $insertId(
        "INSERT INTO pesanan (kode, user_id, status, sumber, total, tgl_kirim)
         VALUES ('SN-TEST-HOL-INACTIVE', ?, 'selesai', 'online', 15000, ?) RETURNING id",
        [$customerId, $holidayDate . ' 12:00:00+07']
    );
    $inactiveHolidayDetailId = $insertId(
        'INSERT INTO detail_pesanan (pesanan_id, produk_id, qty, harga) VALUES (?, ?, 1, 15000) RETURNING id',
        [$inactiveHolidayOrderId, $holidayTestProductId]
    );

    $holidaySnapshot = daily_quota_snapshot($holidayDate);
    $holidayProduct = array_column($holidaySnapshot['items'], null, 'id')[$holidayTestProductId];
    if (empty($holidaySnapshot['tanggal_libur'])
        || (int) $holidayProduct['kuota_harian'] !== 0
        || (int) $holidayProduct['kuota'] !== 0
        || (int) $holidayProduct['sisa'] !== 0) {
        throw new RuntimeException('daily_quota_snapshot did not treat the holiday range as unavailable.');
    }
    $normalSnapshot = daily_quota_snapshot($normalDate);
    $normalProduct = array_column($normalSnapshot['items'], null, 'id')[$holidayTestProductId];
    if (!empty($normalSnapshot['tanggal_libur']) || (int) $normalProduct['kuota_harian'] !== 12) {
        throw new RuntimeException('daily_quota_snapshot did not keep the normal date available.');
    }

    $holidayTargets = menu_notice_targets($holidayTestProductId);
    $holidayTargetIds = array_map(
        static fn(array $target): int => (int) $target['detail_pesanan_id'],
        $holidayTargets
    );
    $holidayMatches = array_values(array_filter(
        $holidayTargets,
        static fn(array $target): bool => (int) $target['detail_pesanan_id'] === $holidayDetailId
    ));
    if (count($holidayMatches) !== 1 || (int) $holidayMatches[0]['kuota_efektif'] !== 0) {
        throw new RuntimeException('An active holiday order was not selected as a notification target with zero effective quota.');
    }
    if (count($holidayTargetIds) !== count(array_unique($holidayTargetIds))) {
        throw new RuntimeException('Holiday handling produced duplicate notification targets.');
    }
    if (in_array($normalDetailId, $holidayTargetIds, true)
        || in_array($inactiveHolidayDetailId, $holidayTargetIds, true)) {
        throw new RuntimeException('Normal-date or inactive orders were incorrectly selected as notification targets.');
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
    $expectConstraint = static function (string|array $expectedStates, string $description, callable $action) use ($pdo): void {
        $expectedStates = array_map('strval', (array) $expectedStates);
        $pdo->exec('SAVEPOINT menu_notice_constraint_test');
        try {
            $action();
        } catch (PDOException $error) {
            $actualState = (string) ($error->errorInfo[0] ?? $error->getCode());
            $pdo->exec('ROLLBACK TO SAVEPOINT menu_notice_constraint_test');
            $pdo->exec('RELEASE SAVEPOINT menu_notice_constraint_test');
            if (!in_array($actualState, $expectedStates, true)) {
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

    $zone = new DateTimeZone('Asia/Jakarta');
    $offlineFlowDate = null;
    for ($offset = 7; $offset <= 37 && $offlineFlowDate === null; $offset++) {
        $candidate = (new DateTimeImmutable('+' . $offset . ' days', $zone))->format('Y-m-d');
        $isHoliday = (bool) q(
            'SELECT EXISTS(
                SELECT 1 FROM tanggal_libur_operasional
                WHERE tanggal_mulai <= ? AND tanggal_selesai >= ?
            )',
            [$candidate, $candidate]
        )->fetchColumn();
        $hasOrders = (bool) q(
            "SELECT EXISTS(
                SELECT 1 FROM pesanan
                WHERE (tgl_kirim AT TIME ZONE 'Asia/Jakarta')::date = ?
            )",
            [$candidate]
        )->fetchColumn();
        if (!$isHoliday && !$hasOrders) {
            $offlineFlowDate = $candidate;
        }
    }
    if ($offlineFlowDate === null) {
        throw new RuntimeException('No available future date was found for the offline-order follow-up test.');
    }
    $pdo->prepare('INSERT INTO kuota_harian (produk_id, tanggal, kuota) VALUES (?, ?, 10)')
        ->execute([$productId, $offlineFlowDate]);
    $createdOfflineOrder = create_offline_order([
        'nama_pemesan' => 'Pelanggan Offline Tahap 1',
        'nama_penerima' => 'Penerima Offline Tahap 1',
        'telepon' => '081234567890',
        'alamat' => 'Alamat pengujian offline',
        'tgl_kirim' => $offlineFlowDate,
        'waktu_kirim' => '12:00',
        'items' => [['id' => $productId, 'qty' => 4]],
    ]);
    $createdOfflineOrderStatement = $pdo->prepare('SELECT status, sumber, user_id FROM pesanan WHERE id = ?');
    $createdOfflineOrderStatement->execute([(int) $createdOfflineOrder['id']]);
    $createdOfflineOrderRow = $createdOfflineOrderStatement->fetch(PDO::FETCH_ASSOC);
    $createdOfflineDetailStatement = $pdo->prepare(
        'SELECT id FROM detail_pesanan WHERE pesanan_id = ? AND produk_id = ?'
    );
    $createdOfflineDetailStatement->execute([(int) $createdOfflineOrder['id'], $productId]);
    $createdOfflineDetailId = (int) $createdOfflineDetailStatement->fetchColumn();
    if ($createdOfflineOrderRow['status'] !== 'diproses'
        || $createdOfflineOrderRow['sumber'] !== 'offline'
        || $createdOfflineOrderRow['user_id'] !== null
        || $createdOfflineDetailId < 1) {
        throw new RuntimeException('The offline-order creation flow did not produce an active offline order.');
    }

    $offlineNoticeId = $insertId(
        'INSERT INTO pemberitahuan_perubahan_menu
            (produk_id, kode_produk_snapshot, nama_produk_snapshot, catatan_admin, created_by)
         VALUES (?, ?, ?, ?, ?) RETURNING id',
        [$productId, 'OLD-01', 'Menu Lama', 'Pemberitahuan offline.', $adminId]
    );
    $offlineFlowRecipientId = $insertRecipient($offlineNoticeId, $createdOfflineDetailId, 4);
    $initialOfflineResponse = $pdo->prepare(
        'SELECT status_respons, opsi_id FROM pemberitahuan_perubahan_menu_pesanan WHERE id = ?'
    );
    $initialOfflineResponse->execute([$offlineFlowRecipientId]);
    $initialOfflineResponse = $initialOfflineResponse->fetch(PDO::FETCH_ASSOC);
    if ($initialOfflineResponse['status_respons'] !== 'menunggu_respons'
        || $initialOfflineResponse['opsi_id'] !== null) {
        throw new RuntimeException('A new offline recipient did not start in menunggu_respons without an option.');
    }

    $followUp = menu_notice_complete_follow_up(
        $offlineFlowRecipientId,
        $adminId,
        'Pelanggan offline sudah dihubungi.'
    );
    $savedFollowUp = $pdo->prepare(
        'SELECT status_respons, catatan_tindak_lanjut, tindak_lanjut_oleh, tindak_lanjut_at
         FROM pemberitahuan_perubahan_menu_pesanan WHERE id = ?'
    );
    $savedFollowUp->execute([$offlineFlowRecipientId]);
    $savedFollowUp = $savedFollowUp->fetch(PDO::FETCH_ASSOC);
    if ($followUp['status_respons'] !== 'selesai'
        || $savedFollowUp['status_respons'] !== 'selesai'
        || $savedFollowUp['catatan_tindak_lanjut'] !== 'Pelanggan offline sudah dihubungi.'
        || (int) $savedFollowUp['tindak_lanjut_oleh'] !== $adminId
        || $savedFollowUp['tindak_lanjut_at'] === null) {
        throw new RuntimeException('Offline manual follow-up was not recorded with its audit fields.');
    }

    $onlinePendingRecipientId = $insertRecipient($secondNoticeId, $onlineDetailId);
    try {
        menu_notice_complete_follow_up($onlinePendingRecipientId, $adminId, 'Tidak boleh.');
        throw new RuntimeException('An online recipient without an eligible follow-up state was not rejected.');
    } catch (DomainException $error) {
        if ($error->getCode() !== 409) {
            throw new RuntimeException('Online follow-up rejection used an unexpected status.', 0, $error);
        }
    }

    $expectDomain = static function (int $expectedCode, string $description, callable $action): void {
        try {
            $action();
        } catch (DomainException $error) {
            if ($error->getCode() !== $expectedCode) {
                throw new RuntimeException(
                    $description . ' failed with status ' . $error->getCode(),
                    0,
                    $error
                );
            }
            return;
        }
        throw new RuntimeException($description . ' was not rejected.');
    };

    // C3: Admin rejects a replacement without changing detail, total, or payment evidence.
    $rejectOrderId = $insertId(
        "INSERT INTO pesanan (kode, user_id, status, sumber, total, tgl_kirim, bukti_bayar, nama_pembayar)
         VALUES ('SN-TEST-REJECT', ?, 'diproses', 'online', 48000, ?, 'uploads/payments/reject.png', 'Penguji')
         RETURNING id",
        [$customerId, $deliveryDate]
    );
    $rejectDetailId = $insertId(
        'INSERT INTO detail_pesanan (pesanan_id, produk_id, qty, harga) VALUES (?, ?, 4, 12000) RETURNING id',
        [$rejectOrderId, $productId]
    );
    $rejectNoticeId = $insertId(
        'INSERT INTO pemberitahuan_perubahan_menu
            (produk_id, kode_produk_snapshot, nama_produk_snapshot, catatan_admin, created_by)
         VALUES (?, ?, ?, ?, ?) RETURNING id',
        [$productId, 'OLD-01', 'Menu Lama', 'Pengganti perlu ditolak.', $adminId]
    );
    $rejectOptionId = $insertId(
        "INSERT INTO pemberitahuan_perubahan_menu_opsi (pemberitahuan_id, kode_opsi, label)
         VALUES (?, 'menu_pengganti', 'Pilih menu pengganti') RETURNING id",
        [$rejectNoticeId]
    );
    $makeReplacementChoice = static function (int $recipientId, int $optionId) use ($pdo, $replacementId): void {
        $statement = $pdo->prepare(
            "UPDATE pemberitahuan_perubahan_menu_pesanan
             SET opsi_id = ?, status_respons = 'menunggu_konfirmasi',
                 produk_pengganti_id = ?,
                 kode_produk_pengganti_snapshot = 'ALT-01',
                 nama_produk_pengganti_snapshot = 'Menu Pengganti',
                 qty_pengganti = 4, harga_pengganti = 15000, responded_at = now()
             WHERE id = ?"
        );
        $statement->execute([$optionId, $replacementId, $recipientId]);
    };
    $rejectRecipientId = $insertRecipient($rejectNoticeId, $rejectDetailId);
    $makeReplacementChoice($rejectRecipientId, $rejectOptionId);

    $rejection = menu_notice_reject_replacement(
        $rejectRecipientId,
        $adminId,
        'Stok menu pengganti habis.'
    );
    $rejectedRow = q(
        'SELECT status_respons, jenis_penutupan, alasan_penutupan, ditutup_oleh, ditutup_at,
                confirmed_by, confirmed_at, catatan_tindak_lanjut
         FROM pemberitahuan_perubahan_menu_pesanan WHERE id = ?',
        [$rejectRecipientId]
    )->fetch();
    $rejectedDetail = q('SELECT produk_id, qty, harga FROM detail_pesanan WHERE id = ?', [$rejectDetailId])->fetch();
    $rejectedOrder = q('SELECT total, status, bukti_bayar, nama_pembayar FROM pesanan WHERE id = ?', [$rejectOrderId])->fetch();
    if ($rejection['status_respons'] !== 'selesai'
        || $rejection['jenis_penutupan'] !== 'penolakan_pengganti'
        || $rejection['alasan_penutupan'] !== 'Stok menu pengganti habis.'
        || $rejectedRow['status_respons'] !== 'selesai'
        || $rejectedRow['jenis_penutupan'] !== 'penolakan_pengganti'
        || $rejectedRow['alasan_penutupan'] !== 'Stok menu pengganti habis.'
        || (int) $rejectedRow['ditutup_oleh'] !== $adminId
        || $rejectedRow['ditutup_at'] === null
        || $rejectedRow['confirmed_by'] !== null
        || $rejectedRow['confirmed_at'] !== null
        || $rejectedRow['catatan_tindak_lanjut'] !== null) {
        throw new RuntimeException('Admin rejection was not recorded with its own closure audit fields.');
    }
    if ((int) $rejectedDetail['produk_id'] !== $productId
        || (int) $rejectedDetail['qty'] !== 4
        || (int) $rejectedDetail['harga'] !== 12000
        || (int) $rejectedOrder['total'] !== 48000
        || $rejectedOrder['status'] !== 'diproses'
        || $rejectedOrder['bukti_bayar'] !== 'uploads/payments/reject.png'
        || $rejectedOrder['nama_pembayar'] !== 'Penguji') {
        throw new RuntimeException('Admin rejection changed the order detail, total, status, or payment evidence.');
    }
    $expectDomain(409, 'A second rejection', static function () use ($rejectRecipientId, $adminId): void {
        menu_notice_reject_replacement($rejectRecipientId, $adminId, 'Penolakan kedua.');
    });

    $rejectOrder2Id = $insertId(
        "INSERT INTO pesanan (kode, user_id, status, sumber, total, tgl_kirim)
         VALUES ('SN-TEST-REJECT-2', ?, 'diproses', 'online', 48000, ?) RETURNING id",
        [$customerId, $deliveryDate]
    );
    $rejectDetail2Id = $insertId(
        'INSERT INTO detail_pesanan (pesanan_id, produk_id, qty, harga) VALUES (?, ?, 4, 12000) RETURNING id',
        [$rejectOrder2Id, $productId]
    );
    $rejectRecipient2Id = $insertRecipient($rejectNoticeId, $rejectDetail2Id);
    $makeReplacementChoice($rejectRecipient2Id, $rejectOptionId);
    $expectDomain(422, 'Rejection without a reason', static function () use ($rejectRecipient2Id, $adminId): void {
        menu_notice_reject_replacement($rejectRecipient2Id, $adminId, "   \n ");
    });
    $untouched = q(
        'SELECT status_respons, jenis_penutupan FROM pemberitahuan_perubahan_menu_pesanan WHERE id = ?',
        [$rejectRecipient2Id]
    )->fetch();
    if ($untouched['status_respons'] !== 'menunggu_konfirmasi' || $untouched['jenis_penutupan'] !== null) {
        throw new RuntimeException('A rejection without a reason changed the response state.');
    }

    $otherRejectOptionId = $insertId(
        "INSERT INTO pemberitahuan_perubahan_menu_opsi (pemberitahuan_id, kode_opsi, label)
         VALUES (?, 'hubungi_admin', 'Hubungi Admin') RETURNING id",
        [$rejectNoticeId]
    );
    q(
        "UPDATE pemberitahuan_perubahan_menu_pesanan
         SET opsi_id = ?, status_respons = 'menunggu_konfirmasi'
         WHERE id = ?",
        [$otherRejectOptionId, $rejectRecipient2Id]
    );
    $expectDomain(409, 'Rejection of a non-replacement option', static function () use ($rejectRecipient2Id, $adminId): void {
        menu_notice_reject_replacement($rejectRecipient2Id, $adminId, 'Bukan pengganti.');
    });

    $inactiveRejectOrderId = $insertId(
        "INSERT INTO pesanan (kode, user_id, status, sumber, total, tgl_kirim)
         VALUES ('SN-REJ-INACTIVE', ?, 'dibatalkan', 'online', 48000, ?) RETURNING id",
        [$customerId, $deliveryDate]
    );
    $inactiveRejectDetailId = $insertId(
        'INSERT INTO detail_pesanan (pesanan_id, produk_id, qty, harga) VALUES (?, ?, 4, 12000) RETURNING id',
        [$inactiveRejectOrderId, $productId]
    );
    $inactiveRejectRecipientId = $insertRecipient($rejectNoticeId, $inactiveRejectDetailId);
    $makeReplacementChoice($inactiveRejectRecipientId, $rejectOptionId);
    $expectDomain(409, 'Rejection on an inactive order', static function () use ($inactiveRejectRecipientId, $adminId): void {
        menu_notice_reject_replacement($inactiveRejectRecipientId, $adminId, 'Pesanan sudah batal.');
    });
    $expectDomain(409, 'Confirmation on an inactive order', static function () use ($inactiveRejectRecipientId, $adminId): void {
        menu_notice_confirm_replacement($inactiveRejectRecipientId, $adminId);
    });

    // C4: cancellation/expiration closes only open recipients and keeps history.
    $closureFixture = static function (string $status, string $orderStatus = 'diproses') use (
        $insertId,
        $insertRecipient,
        $productId,
        $customerId,
        $adminId,
        $deliveryDate
    ): array {
        $orderId = $insertId(
            "INSERT INTO pesanan (kode, user_id, status, sumber, total, tgl_kirim, bukti_bayar, nama_pembayar)
             VALUES (?, ?, ?, 'online', 36000, ?, 'uploads/payments/closure.png', 'Penguji') RETURNING id",
            ['SN-CLOSE-' . bin2hex(random_bytes(4)), $customerId, $orderStatus, $deliveryDate]
        );
        $detailId = $insertId(
            'INSERT INTO detail_pesanan (pesanan_id, produk_id, qty, harga) VALUES (?, ?, 3, 12000) RETURNING id',
            [$orderId, $productId]
        );
        $noticeId = $insertId(
            'INSERT INTO pemberitahuan_perubahan_menu
                (produk_id, kode_produk_snapshot, nama_produk_snapshot, catatan_admin, created_by)
             VALUES (?, ?, ?, ?, ?) RETURNING id',
            [$productId, 'OLD-01', 'Menu Lama', 'Uji penutupan otomatis.', $adminId]
        );
        $recipientId = $insertRecipient($noticeId, $detailId, 3);
        q('UPDATE pemberitahuan_perubahan_menu_pesanan SET status_respons = ? WHERE id = ?', [$status, $recipientId]);
        return [$orderId, $detailId, $noticeId, $recipientId];
    };

    $closedCounts = [];
    foreach (['menunggu_respons', 'menunggu_konfirmasi', 'menunggu_tindak_lanjut'] as $openStatus) {
        [$closureOrderId, $closureDetailId, , $closureRecipientId] = $closureFixture($openStatus);
        q("UPDATE pesanan SET status = 'dibatalkan' WHERE id = ?", [$closureOrderId]);
        $closedCounts[$openStatus] = menu_notice_close_for_order(
            $closureOrderId,
            'pembatalan_pesanan',
            'Pesanan dibatalkan oleh pelanggan.',
            $customerId
        );
        $closedRow = q(
            'SELECT status_respons, jenis_penutupan, alasan_penutupan, ditutup_oleh, ditutup_at
             FROM pemberitahuan_perubahan_menu_pesanan WHERE id = ?',
            [$closureRecipientId]
        )->fetch();
        $closedDetail = q('SELECT produk_id, qty, harga FROM detail_pesanan WHERE id = ?', [$closureDetailId])->fetch();
        $closedOrder = q('SELECT total, bukti_bayar, nama_pembayar FROM pesanan WHERE id = ?', [$closureOrderId])->fetch();
        if ($closedCounts[$openStatus] !== 1
            || $closedRow['status_respons'] !== 'selesai'
            || $closedRow['jenis_penutupan'] !== 'pembatalan_pesanan'
            || $closedRow['alasan_penutupan'] !== 'Pesanan dibatalkan oleh pelanggan.'
            || (int) $closedRow['ditutup_oleh'] !== $customerId
            || $closedRow['ditutup_at'] === null
            || (int) $closedDetail['produk_id'] !== $productId
            || (int) $closedDetail['qty'] !== 3
            || (int) $closedDetail['harga'] !== 12000
            || (int) $closedOrder['total'] !== 36000
            || $closedOrder['bukti_bayar'] !== 'uploads/payments/closure.png'
            || $closedOrder['nama_pembayar'] !== 'Penguji') {
            throw new RuntimeException('Cancellation did not close the ' . $openStatus . ' recipient correctly.');
        }
        if ((int) q(
            'SELECT COUNT(*) FROM pemberitahuan_perubahan_menu_pesanan WHERE id = ?',
            [$closureRecipientId]
        )->fetchColumn() !== 1) {
            throw new RuntimeException('Cancellation removed notification history.');
        }
    }

    foreach (['selesai', 'dikonfirmasi'] as $completedStatus) {
        [$completedOrderId, , , $completedRecipientId] = $closureFixture($completedStatus);
        q("UPDATE pesanan SET status = 'dibatalkan' WHERE id = ?", [$completedOrderId]);
        if (menu_notice_close_for_order(
            $completedOrderId,
            'pembatalan_pesanan',
            'Tidak boleh menimpa.',
            $customerId
        ) !== 0) {
            throw new RuntimeException('Cancellation closed a ' . $completedStatus . ' notification.');
        }
        $completedRow = q(
            'SELECT status_respons, jenis_penutupan, alasan_penutupan FROM pemberitahuan_perubahan_menu_pesanan WHERE id = ?',
            [$completedRecipientId]
        )->fetch();
        if ($completedRow['status_respons'] !== $completedStatus
            || $completedRow['jenis_penutupan'] !== null
            || $completedRow['alasan_penutupan'] !== null) {
            throw new RuntimeException('A completed notification was overwritten by cancellation.');
        }
    }

    [$expiredOrderId, , , $expiredRecipientId] = $closureFixture('menunggu_respons');
    q("UPDATE pesanan SET status = 'dibatalkan' WHERE id = ?", [$expiredOrderId]);
    if (menu_notice_close_for_order(
        $expiredOrderId,
        'kedaluwarsa_pesanan',
        'Pesanan dibatalkan otomatis karena kedaluwarsa pembayaran.'
    ) !== 1) {
        throw new RuntimeException('Expiration did not close an open notification.');
    }
    $expiredRow = q(
        'SELECT status_respons, jenis_penutupan, ditutup_oleh, ditutup_at
         FROM pemberitahuan_perubahan_menu_pesanan WHERE id = ?',
        [$expiredRecipientId]
    )->fetch();
    if ($expiredRow['status_respons'] !== 'selesai'
        || $expiredRow['jenis_penutupan'] !== 'kedaluwarsa_pesanan'
        || $expiredRow['ditutup_oleh'] !== null
        || $expiredRow['ditutup_at'] === null) {
        throw new RuntimeException('Automatic expiration closure was not recorded without an actor.');
    }

    $expectDomain(422, 'Order closure without a reason', static function () use ($closureFixture): void {
        [$orderId] = $closureFixture('menunggu_respons');
        menu_notice_close_for_order($orderId, 'pembatalan_pesanan', '  ', null);
    });
    $expectDomain(422, 'Order closure with an unknown type', static function () use ($closureFixture): void {
        [$orderId] = $closureFixture('menunggu_respons');
        menu_notice_close_for_order($orderId, 'jenis_tidak_valid', 'Alasan.', null);
    });

    [$atomicOrderId, , , $atomicRecipientId] = $closureFixture('menunggu_respons');
    $pdo->exec('SAVEPOINT menu_notice_closure_atomic');
    try {
        q("UPDATE pesanan SET status = 'dibatalkan' WHERE id = ?", [$atomicOrderId]);
        try {
            menu_notice_close_for_order($atomicOrderId, 'jenis_tidak_valid', 'Gagal.', null);
            throw new RuntimeException('The invalid closure type did not fail.');
        } catch (DomainException $error) {
            if ($error->getCode() !== 422) {
                throw $error;
            }
        }
    } finally {
        $pdo->exec('ROLLBACK TO SAVEPOINT menu_notice_closure_atomic');
        $pdo->exec('RELEASE SAVEPOINT menu_notice_closure_atomic');
    }
    if (q('SELECT status FROM pesanan WHERE id = ?', [$atomicOrderId])->fetchColumn() !== 'diproses'
        || q(
            'SELECT status_respons FROM pemberitahuan_perubahan_menu_pesanan WHERE id = ?',
            [$atomicRecipientId]
        )->fetchColumn() !== 'menunggu_respons') {
        throw new RuntimeException('A failed closure left a partially cancelled order or a modified notification.');
    }

    // PostgreSQL 18 memakai SQLSTATE 23001 untuk ON DELETE RESTRICT; versi lama 23503.
    $expectConstraint(['23503', '23001'], 'Delete order with notification history', static function () use ($pdo, $onlineOrderId): void {
        $statement = $pdo->prepare('DELETE FROM pesanan WHERE id = ?');
        $statement->execute([$onlineOrderId]);
    });

    $orderStatus = $pdo->prepare('SELECT status FROM pesanan WHERE id = ?');
    $orderStatus->execute([$onlineOrderId]);
    if ($orderStatus->fetchColumn() !== 'diproses') {
        throw new RuntimeException('The migration or its notification data changed the order status.');
    }

    // ---- D2: harga penggantian terkunci saat pelanggan memilih ----
    $priceInfoSame = menu_notice_price_info(15000, 15000);
    if ($priceInfoSame === null
        || $priceInfoSame['perbedaan_harga_katalog'] !== 0
        || $priceInfoSame['kebijakan_harga'] !== MENU_NOTICE_PRICE_POLICY) {
        throw new RuntimeException('Informasi harga snapshot tidak konsisten dengan kebijakan harga.');
    }
    if (menu_notice_price_info(null, null) !== null) {
        throw new RuntimeException('Informasi harga seharusnya kosong bila belum ada menu pengganti.');
    }

    $paymentMatrix = [
        ['menunggu_pembayaran', null, 'online', 'belum_membayar', false],
        ['menunggu_validasi', 'uploads/payments/a.png', 'online', 'menunggu_verifikasi', false],
        ['diproses', 'uploads/payments/a.png', 'online', 'terverifikasi', true],
        ['selesai', 'uploads/payments/a.png', 'online', 'terverifikasi', true],
        ['diproses', null, 'online', 'tidak_ada_bukti', false],
        ['diproses', null, 'offline', 'manual_offline', false],
        ['dibatalkan', 'uploads/payments/a.png', 'online', 'tidak_berlaku', false],
    ];
    foreach ($paymentMatrix as [$paymentStatus, $paymentProof, $paymentSource, $expectedStage, $expectedPaid]) {
        $summary = menu_notice_payment_summary([
            'status_pesanan' => $paymentStatus,
            'sumber' => $paymentSource,
            'bukti_bayar' => $paymentProof,
            'nama_pembayar' => $paymentProof === null ? null : 'Penguji',
            'total_pesanan' => 48000,
            'total_pesanan_lama' => null,
            'total_pesanan_baru' => null,
            'selisih_total' => null,
            'status_penyelesaian_selisih' => 'belum_dihitung',
        ]);
        if ($summary['status_pembayaran'] !== $expectedStage
            || $summary['sudah_membayar'] !== $expectedPaid
            || $summary['nominal_tagihan'] !== 48000
            || $summary['bukti_bayar'] !== $paymentProof
            || $summary['sumber'] !== $paymentSource) {
            throw new RuntimeException(
                'Ringkasan pembayaran salah untuk ' . $paymentStatus . '/' . $paymentSource . '.'
            );
        }
    }

    $createPriceCase = static function (string $suffix) use (
        $insertId,
        $insertRecipient,
        $customerId,
        $productId,
        $adminId,
        $deliveryDate
    ): array {
        $orderId = $insertId(
            "INSERT INTO pesanan (kode, user_id, status, sumber, total, tgl_kirim)
             VALUES (?, ?, 'diproses', 'online', 48000, ?) RETURNING id",
            ['SN-D2-' . $suffix, $customerId, $deliveryDate]
        );
        $detailId = $insertId(
            'INSERT INTO detail_pesanan (pesanan_id, produk_id, qty, harga) VALUES (?, ?, 4, 12000) RETURNING id',
            [$orderId, $productId]
        );
        $noticeId = $insertId(
            'INSERT INTO pemberitahuan_perubahan_menu
                (produk_id, kode_produk_snapshot, nama_produk_snapshot, catatan_admin, created_by)
             VALUES (?, ?, ?, ?, ?) RETURNING id',
            [$productId, 'OLD-01', 'Menu Lama', 'Uji kebijakan harga.', $adminId]
        );
        $optionId = $insertId(
            "INSERT INTO pemberitahuan_perubahan_menu_opsi (pemberitahuan_id, kode_opsi, label)
             VALUES (?, 'menu_pengganti', 'Pilih menu pengganti') RETURNING id",
            [$noticeId]
        );
        $recipientId = $insertRecipient($noticeId, $detailId);

        return [$orderId, $detailId, $noticeId, $optionId, $recipientId];
    };
    $pendingReplacementAt = static function (int $recipientId, int $optionId, int $price) use (
        $pdo,
        $replacementId
    ): void {
        $statement = $pdo->prepare(
            "UPDATE pemberitahuan_perubahan_menu_pesanan
             SET opsi_id = ?, status_respons = 'menunggu_konfirmasi',
                 produk_pengganti_id = ?,
                 kode_produk_pengganti_snapshot = 'ALT-01',
                 nama_produk_pengganti_snapshot = 'Menu Pengganti',
                 qty_pengganti = 4, harga_pengganti = ?, responded_at = now()
             WHERE id = ?"
        );
        $statement->execute([$optionId, $replacementId, $price, $recipientId]);
    };

    [$d2aOrderId, $d2aDetailId, , $d2aOptionId, $d2aRecipientId] = $createPriceCase('A');
    $pendingReplacementAt($d2aRecipientId, $d2aOptionId, 15000);
    $d2a = menu_notice_confirm_replacement($d2aRecipientId, $adminId);
    if ($d2a['selisih_total'] !== 12000
        || $d2a['total_pesanan_lama'] !== 48000
        || $d2a['total_pesanan_baru'] !== 60000
        || $d2a['perbedaan_harga_katalog'] !== 0) {
        throw new RuntimeException('Konfirmasi tanpa perubahan harga katalog tidak sesuai.');
    }
    if ((int) q('SELECT total FROM pesanan WHERE id = ?', [$d2aOrderId])->fetchColumn() !== 60000
        || (int) q('SELECT harga FROM detail_pesanan WHERE id = ?', [$d2aDetailId])->fetchColumn() !== 15000) {
        throw new RuntimeException('Total pesanan atau harga detail tidak mengikuti snapshot pelanggan.');
    }

    [$d2bOrderId, $d2bDetailId, , $d2bOptionId, $d2bRecipientId] = $createPriceCase('B');
    $pendingReplacementAt($d2bRecipientId, $d2bOptionId, 15000);
    q('UPDATE produk SET harga = 18000 WHERE id = ?', [$replacementId]);
    $d2b = menu_notice_confirm_replacement($d2bRecipientId, $adminId);
    if ($d2b['total_pesanan_baru'] !== 60000
        || $d2b['selisih_total'] !== 12000
        || $d2b['perbedaan_harga_katalog'] !== 3000
        || $d2b['harga_katalog_saat_konfirmasi'] !== 18000) {
        throw new RuntimeException('Kenaikan harga katalog tidak dicatat sebagai audit tanpa mengubah total.');
    }
    $persistedPrice = q(
        'SELECT harga_katalog_saat_konfirmasi, perbedaan_harga_katalog
         FROM pemberitahuan_perubahan_menu_pesanan WHERE id = ?',
        [$d2bRecipientId]
    )->fetch();
    if ((int) $persistedPrice['harga_katalog_saat_konfirmasi'] !== 18000
        || (int) $persistedPrice['perbedaan_harga_katalog'] !== 3000
        || (int) q('SELECT total FROM pesanan WHERE id = ?', [$d2bOrderId])->fetchColumn() !== 60000
        || (int) q('SELECT harga FROM detail_pesanan WHERE id = ?', [$d2bDetailId])->fetchColumn() !== 15000) {
        throw new RuntimeException('Audit harga katalog tidak tersimpan atau total berubah dari snapshot.');
    }
    q('UPDATE produk SET harga = 15000 WHERE id = ?', [$replacementId]);

    [$d2cOrderId, $d2cDetailId, , $d2cOptionId, $d2cRecipientId] = $createPriceCase('C');
    $pendingReplacementAt($d2cRecipientId, $d2cOptionId, 15000);
    q('UPDATE produk SET harga = 13000 WHERE id = ?', [$replacementId]);
    $d2c = menu_notice_confirm_replacement($d2cRecipientId, $adminId);
    if ($d2c['total_pesanan_baru'] !== 60000
        || $d2c['selisih_total'] !== 12000
        || $d2c['perbedaan_harga_katalog'] !== -2000) {
        throw new RuntimeException('Penurunan harga katalog tidak dicatat sebagai audit tanpa mengubah total.');
    }
    q('UPDATE produk SET harga = 15000 WHERE id = ?', [$replacementId]);

    // ---- D3: transparansi pembayaran dan penyelesaian selisih tanpa mutasi otomatis ----
    [$d3PosOrderId, , , $d3PosOptionId, $d3PosRecipientId] = $createPriceCase('SETTLE-POS');
    $pendingReplacementAt($d3PosRecipientId, $d3PosOptionId, 15000);
    $d3PosBeforeConfirm = q(
        'SELECT status, bukti_bayar, nama_pembayar FROM pesanan WHERE id = ?',
        [$d3PosOrderId]
    )->fetch();
    $d3PosConfirm = menu_notice_confirm_replacement($d3PosRecipientId, $adminId);
    $d3PosAfterConfirm = q(
        'SELECT status, bukti_bayar, nama_pembayar FROM pesanan WHERE id = ?',
        [$d3PosOrderId]
    )->fetch();
    if ($d3PosConfirm['status_penyelesaian_selisih'] !== 'menunggu_tagihan'
        || $d3PosBeforeConfirm !== $d3PosAfterConfirm) {
        throw new RuntimeException('Konfirmasi penggantian mengubah status atau bukti pembayaran.');
    }
    $d3PosBeforeSettle = q(
        'SELECT status, total, bukti_bayar, nama_pembayar FROM pesanan WHERE id = ?',
        [$d3PosOrderId]
    )->fetch();
    $d3PosSettle = menu_notice_settle_difference($d3PosRecipientId, $adminId);
    $d3PosAfterSettle = q(
        'SELECT status, total, bukti_bayar, nama_pembayar FROM pesanan WHERE id = ?',
        [$d3PosOrderId]
    )->fetch();
    if ($d3PosSettle['status_penyelesaian_selisih'] !== 'selesai'
        || $d3PosBeforeSettle !== $d3PosAfterSettle) {
        throw new RuntimeException('Penyelesaian selisih positif memakai mekanisme pembayaran otomatis.');
    }

    [$d3NegOrderId, , , $d3NegOptionId, $d3NegRecipientId] = $createPriceCase('SETTLE-NEG');
    $pendingReplacementAt($d3NegRecipientId, $d3NegOptionId, 10000);
    $d3NegConfirm = menu_notice_confirm_replacement($d3NegRecipientId, $adminId);
    $d3NegBeforeSettle = q(
        'SELECT status, total, bukti_bayar, nama_pembayar FROM pesanan WHERE id = ?',
        [$d3NegOrderId]
    )->fetch();
    $d3NegSettle = menu_notice_settle_difference($d3NegRecipientId, $adminId);
    $d3NegAfterSettle = q(
        'SELECT status, total, bukti_bayar, nama_pembayar FROM pesanan WHERE id = ?',
        [$d3NegOrderId]
    )->fetch();
    if ($d3NegConfirm['selisih_total'] !== -8000
        || $d3NegConfirm['total_pesanan_baru'] !== 40000
        || $d3NegConfirm['status_penyelesaian_selisih'] !== 'menunggu_pengembalian'
        || $d3NegSettle['status_penyelesaian_selisih'] !== 'selesai'
        || $d3NegBeforeSettle !== $d3NegAfterSettle) {
        throw new RuntimeException('Selisih negatif atau pengembalian mengubah pembayaran pesanan.');
    }

    [$d3ZeroOrderId, , , $d3ZeroOptionId, $d3ZeroRecipientId] = $createPriceCase('SETTLE-ZERO');
    $pendingReplacementAt($d3ZeroRecipientId, $d3ZeroOptionId, 12000);
    $d3ZeroConfirm = menu_notice_confirm_replacement($d3ZeroRecipientId, $adminId);
    if ($d3ZeroConfirm['selisih_total'] !== 0
        || $d3ZeroConfirm['total_pesanan_baru'] !== 48000
        || $d3ZeroConfirm['status_penyelesaian_selisih'] !== 'tidak_ada') {
        throw new RuntimeException('Penggantian tanpa selisih tidak ditandai dengan benar.');
    }
    $expectDomain(409, 'Settlement without a price difference', static function () use ($d3ZeroRecipientId, $adminId): void {
        menu_notice_settle_difference($d3ZeroRecipientId, $adminId);
    });

    // ---- C6: urutan locking kanonik dan kontrak pemanggil ----
    $quotaSource = file_get_contents(__DIR__ . '/../app/daily-quota.php');
    $lockFunctionStart = strpos($quotaSource, 'function daily_quota_lock_tables');
    if ($lockFunctionStart === false) {
        throw new RuntimeException('Helper locking kanonik tidak ditemukan.');
    }
    $lockFunctionBody = substr($quotaSource, $lockFunctionStart);
    $lockPositions = [
        strpos($lockFunctionBody, 'LOCK TABLE produk'),
        strpos($lockFunctionBody, 'LOCK TABLE kuota_harian'),
        strpos($lockFunctionBody, 'LOCK TABLE tanggal_libur_operasional'),
    ];
    if (in_array(false, $lockPositions, true)
        || $lockPositions[0] >= $lockPositions[1]
        || $lockPositions[1] >= $lockPositions[2]) {
        throw new RuntimeException('Urutan lock kanonik harus produk, kuota_harian, lalu tanggal libur.');
    }
    foreach ([
        __DIR__ . '/../public/pelanggan/api/orders.php',
        __DIR__ . '/../app/offline-orders.php',
        __DIR__ . '/../app/menu-notifications.php',
        __DIR__ . '/../public/pelanggan/api/menu-notifications.php',
    ] as $lockCaller) {
        if (!str_contains((string) file_get_contents($lockCaller), 'daily_quota_lock_tables(')) {
            throw new RuntimeException('Pemanggil lock kanonik belum memakai helper bersama: ' . basename($lockCaller));
        }
    }
    $pdo->exec('SAVEPOINT mn_c6_lock_order');
    daily_quota_lock_tables();
    $lockedTables = q(
        "SELECT c.relname
          FROM pg_locks l
          JOIN pg_class c ON c.oid = l.relation
          WHERE l.locktype = 'relation' AND l.mode = 'ShareRowExclusiveLock'
            AND l.granted AND l.pid = pg_backend_pid()
            AND c.relname IN ('produk', 'kuota_harian', 'tanggal_libur_operasional')
          ORDER BY c.relname"
    )->fetchAll(PDO::FETCH_COLUMN);
    $pdo->exec('ROLLBACK TO SAVEPOINT mn_c6_lock_order');
    $pdo->exec('RELEASE SAVEPOINT mn_c6_lock_order');
    if ($lockedTables !== ['kuota_harian', 'produk', 'tanggal_libur_operasional']) {
        throw new RuntimeException('Helper locking kanonik tidak mengambil ketiga lock tabel.');
    }

    echo "PASS: migrations 021/022/023/024 are idempotent; target selection, holiday availability, ownership, unread, capacity, confirmation, rejection, automatic closure, price policy and catalog drift audit, payment transparency, difference settlement without automatic charge/refund, canonical lock order, online/offline manual follow-up, atomic failure, and history checks pass.\n";
} finally {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
}
