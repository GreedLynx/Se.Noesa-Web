<?php
require_once __DIR__ . '/daily-quota.php';

function create_offline_order(array $request): array
{
    $jenisPesanan = ($request['jenis_pesanan'] ?? 'delivery') === 'dine_in' ? 'dine_in' : 'delivery';
    $isDineIn = $jenisPesanan === 'dine_in';

    $name = trim((string) ($request['nama_pemesan'] ?? ''));
    $recipient = trim((string) ($request['nama_penerima'] ?? $name));
    $phone = trim((string) ($request['telepon'] ?? ''));
    $address = $isDineIn ? 'Dine-in di tempat' : trim((string) ($request['alamat'] ?? ''));
    $rawNote = trim((string) ($request['keterangan'] ?? ''));
    $date = $request['tgl_kirim'] ?? '';
    $time = trim((string) ($request['waktu_kirim'] ?? '12:00'));
    $items = $request['items'] ?? null;

    $invalidAddress = !$isDineIn and ($address === '' or strlen($address) > 2000);
    if ($name === '' or strlen($name) > 100 or $recipient === '' or strlen($recipient) > 100
        or !preg_match('/^[0-9+() .-]{8,20}$/', $phone)
        or $invalidAddress
        or strlen($rawNote) > 100 or !is_array($items) or !$items) {
        throw new DomainException('Lengkapi nama pemesan, nomor HP (8-20 digit), alamat (jika diantar), dan menu pesanan dengan benar.');
    }

    $prefixLabel = $isDineIn ? '[Dine-in]' : '[Delivery]';
    $note = $rawNote !== '' ? "{$prefixLabel} {$rawNote}" : $prefixLabel;
    if (strlen($note) > 120) {
        $note = substr($note, 0, 120);
    }

    $zone = new DateTimeZone('Asia/Jakarta');
    if (!is_string($date) or !is_string($time)
        or !preg_match('/^\d{4}-\d{2}-\d{2}$/D', $date)
        or !preg_match('/^\d{2}:\d{2}$/D', $time)) {
        throw new DomainException('Tanggal dan waktu pengiriman tidak valid atau sudah lewat.');
    }
    $delivery = DateTimeImmutable::createFromFormat('!Y-m-d H:i', $date . ' ' . $time, $zone);
    $dateErrors = DateTimeImmutable::getLastErrors();
    $now = new DateTimeImmutable('now', $zone);
    $hasDateError = $dateErrors and ($dateErrors['warning_count'] or $dateErrors['error_count']);
    if (!$delivery or $hasDateError
        or $delivery->format('Y-m-d') !== $date or $delivery->format('H:i') !== $time
        or $delivery < $now) {
        throw new DomainException('Tanggal dan waktu pengiriman tidak valid atau sudah lewat.');
    }
    if (count($items) > 50) {
        throw new DomainException('Maksimal 50 jenis menu per pesanan.');
    }

    $quantities = [];
    foreach ($items as $item) {
        if (!is_array($item)) {
            throw new DomainException('Pilihan menu tidak valid.');
        }
        $productId = filter_var($item['id'] ?? null, FILTER_VALIDATE_INT);
        $quantity = filter_var($item['qty'] ?? null, FILTER_VALIDATE_INT);
        if (!$productId or $productId < 1 or !$quantity or $quantity < 1 or $quantity > SENOESA_MAX_ORDER_QTY) {
            throw new DomainException('Jumlah menu tidak valid.');
        }
        $quantities[$productId] = ($quantities[$productId] ?? 0) + $quantity;
        if (array_sum($quantities) > SENOESA_MAX_ORDER_QTY) {
            throw new DomainException('Satu pesanan maksimal ' . SENOESA_MAX_ORDER_QTY . ' porsi.');
        }
    }
    ksort($quantities);

    $db = db();
    $ownsTransaction = !$db->inTransaction();
    if ($ownsTransaction) {
        $db->beginTransaction();
    }

    try {
        daily_quota_lock_tables();
        q('SELECT id FROM kuota_harian WHERE tanggal = ? ORDER BY produk_id FOR UPDATE', [$date])->fetchAll();

        $snapshot = daily_quota_snapshot($date);

        if (!empty($snapshot['tanggal_libur'])) {
            throw new DomainException('Tanggal ini merupakan hari libur operasional. Silakan pilih tanggal lain.');
        }

        $totalPortions = array_sum($quantities);
        if (!$isDineIn and $totalPortions < SENOESA_MIN_PACKAGE_QTY) {
            throw new DomainException('Minimal pesanan diantar adalah ' . SENOESA_MIN_PACKAGE_QTY . ' porsi.');
        }
        if ($totalPortions > SENOESA_MAX_ORDER_QTY) {
            throw new DomainException('Satu pesanan maksimal ' . SENOESA_MAX_ORDER_QTY . ' porsi.');
        }

        $capacity = (int) ($snapshot['dailyCapacity'] ?? $snapshot['maxKitchen'] ?? 0);
        $used = (int) ($snapshot['dailyUsed'] ?? $snapshot['kitchenUsed'] ?? 0);
        if ($used + $totalPortions > $capacity) {
            throw new DomainException('Kuota harian untuk tanggal ini tidak mencukupi. Sisa ' . max(0, $capacity - $used) . ' porsi.');
        }

        $products = array_column($snapshot['items'], null, 'id');
        $lines = [];
        foreach ($quantities as $id => $qty) {
            $product = $products[$id] ?? null;
            if (!$product or (int) $product['kuota_harian'] <= 0) {
                throw new DomainException('Salah satu menu sudah tidak tersedia.');
            }
            if ($qty > (int) $product['sisa']) {
                throw new DomainException('Stok ' . $product['nama'] . ' tidak mencukupi. Sisa ' . (int) $product['sisa'] . ' porsi.');
            }
            $minQty = $isDineIn ? 1 : (int) $product['min_pesan'];
            if ($qty < $minQty) {
                throw new DomainException($product['nama'] . ' minimal dipesan ' . $minQty . ' porsi.');
            }
            $lines[] = ['product' => $product, 'qty' => $qty];
        }

        $total = 0;
        $portions = 0;
        foreach ($lines as $line) {
            $total += (int) $line['product']['harga'] * $line['qty'];
            $portions += $line['qty'];
            if ($total > 2147483647) {
                throw new DomainException('Total pesanan melebihi batas transaksi.');
            }
        }

        $scheduleId = q('SELECT id FROM jadwal_po WHERE aktif ORDER BY tgl_buka LIMIT 1 FOR UPDATE')->fetchColumn();
        $code = 'SN-' . (new DateTimeImmutable('now', $zone))->format('ymd')
            . '-OF-' . strtoupper(bin2hex(random_bytes(3)));
        $orderId = (int) q(
            "INSERT INTO pesanan
                (kode, jadwal_id, nama_pemesan, keterangan, pax, total, status, sumber,
                 status_updated_at, tgl_kirim, alamat_pengiriman, telepon_penerima)
            VALUES (?, ?, ?, ?, ?, ?, 'diproses', 'offline', now(), ?, ?, ?)
            RETURNING id",
            [
                $code,
                $scheduleId ?: null,
                $recipient !== $name ? "{$name} (Penerima: {$recipient})" : $name,
                $note ?: null,
                $portions,
                $total,
                $delivery->format('Y-m-d H:i:sP'),
                $address,
                $phone,
            ]
        )->fetchColumn();

        foreach ($lines as $line) {
            q(
                'INSERT INTO detail_pesanan (pesanan_id, produk_id, qty, harga) VALUES (?, ?, ?, ?)',
                [$orderId, $line['product']['id'], $line['qty'], $line['product']['harga']]
            );
        }

        if ($ownsTransaction) {
            $db->commit();
        }
        return ['id' => $orderId, 'kode' => $code, 'total' => $total, 'status' => 'diproses'];
    } catch (Throwable $error) {
        if ($ownsTransaction and $db->inTransaction()) {
            $db->rollBack();
        }
        throw $error;
    }
}