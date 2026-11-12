<?php
function create_offline_order(array $request): array
{
    $name = trim((string) ($request['nama_pemesan'] ?? ''));
    $recipient = trim((string) ($request['nama_penerima'] ?? ''));
    $phone = trim((string) ($request['telepon'] ?? ''));
    $address = trim((string) ($request['alamat'] ?? ''));
    $note = trim((string) ($request['keterangan'] ?? ''));
    $date = $request['tgl_kirim'] ?? '';
    $time = trim((string) ($request['waktu_kirim'] ?? '12:00'));
    $items = $request['items'] ?? null;

    if ($name === '' || strlen($name) > 100 || $recipient === '' || strlen($recipient) > 100
        || !preg_match('/^[0-9+() .-]{8,20}$/', $phone) || $address === '' || strlen($address) > 2000
        || strlen($note) > 120 || !is_array($items) || !$items) {
        throw new DomainException('Lengkapi nama, penerima, nomor HP, alamat, dan menu pesanan dengan benar.');
    }

    $zone = new DateTimeZone('Asia/Jakarta');
    if (!is_string($date) || !is_string($time)
        || !preg_match('/^\d{4}-\d{2}-\d{2}$/D', $date)
        || !preg_match('/^\d{2}:\d{2}$/D', $time)) {
        throw new DomainException('Tanggal dan waktu pengiriman tidak valid atau sudah lewat.');
    }
    $delivery = DateTimeImmutable::createFromFormat('!Y-m-d H:i', $date . ' ' . $time, $zone);
    $dateErrors = DateTimeImmutable::getLastErrors();
    $now = new DateTimeImmutable('now', $zone);
    if (!$delivery || ($dateErrors && ($dateErrors['warning_count'] || $dateErrors['error_count']))
        || $delivery->format('Y-m-d') !== $date || $delivery->format('H:i') !== $time
        || $delivery < $now) {
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
        if (!$productId || $productId < 1 || !$quantity || $quantity < 1 || $quantity > 500) {
            throw new DomainException('Jumlah menu tidak valid.');
        }
        $quantities[$productId] = ($quantities[$productId] ?? 0) + $quantity;
        if ($quantities[$productId] > 500) {
            throw new DomainException('Maksimal 500 porsi per menu.');
        }
    }
    ksort($quantities);

    $db = db();
    $ownsTransaction = !$db->inTransaction();
    if ($ownsTransaction) {
        $db->beginTransaction();
    }

    try {
        q('LOCK TABLE produk IN SHARE ROW EXCLUSIVE MODE');
        q('LOCK TABLE kuota_harian IN SHARE ROW EXCLUSIVE MODE');
        q('SELECT id FROM kuota_harian WHERE tanggal = ? ORDER BY produk_id FOR UPDATE', [$date])->fetchAll();

        $snapshot = daily_quota_snapshot($date);
        $lines = daily_quota_validate($snapshot, $quantities);
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
                $name,
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
        if ($ownsTransaction && $db->inTransaction()) {
            $db->rollBack();
        }
        throw $error;
    }
}
