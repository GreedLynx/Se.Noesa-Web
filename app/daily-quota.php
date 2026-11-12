<?php
// Dipakai setelah bootstrap.php. Seluruh pemakaian dihitung dari transaksi.
function daily_quota_snapshot($date, bool $report = false): array
{
    if (!is_string($date) || !preg_match('/^\d{4}-\d{2}-\d{2}$/D', $date) || substr($date, 0, 4) === '0000') {
        throw new DomainException('Tanggal kuota tidak valid. Gunakan YYYY-MM-DD.');
    }

    $zone = new DateTimeZone('Asia/Jakarta');
    $start = DateTimeImmutable::createFromFormat('!Y-m-d', $date, $zone);
    if (!$start || $start->format('Y-m-d') !== $date) {
        throw new DomainException('Tanggal kuota tidak valid. Gunakan YYYY-MM-DD.');
    }

    $fields = 'pr.id, pr.kode, pr.nama, pr.kategori, pr.harga, pr.gambar, GREATEST(4, pr.min_pesan) AS min_pesan, pr.kuota_harian AS kuota_default';
    if ($report) {
        // LEFT JOIN menjaga semua produk tetap tampil, termasuk yang belum memiliki konfigurasi kuota.
        $items = q(
            "SELECT $fields, COALESCE(v.kuota, pr.kuota_harian) AS kuota_harian,
                COALESCE(v.kuota, pr.kuota_harian) AS kuota, COALESCE(v.terpakai, 0) AS terpakai,
                GREATEST(0, COALESCE(v.kuota, pr.kuota_harian) - COALESCE(v.terpakai, 0)) AS sisa
            FROM produk pr
            LEFT JOIN v_kuota_harian v ON v.produk_id = pr.id AND v.tanggal = ?
            ORDER BY pr.id",
            [$date]
        )->fetchAll();
    } else {
        // LEFT JOIN mengambil kuota; SUM menghitung pesanan yang belum ditolak atau dibatalkan.
        $items = q(
            "SELECT $fields, COALESCE(k.kuota, pr.kuota_harian) AS kuota_harian,
                COALESCE(k.kuota, pr.kuota_harian) AS kuota, COALESCE(used.qty, 0) AS terpakai,
                GREATEST(0, COALESCE(k.kuota, pr.kuota_harian) - COALESCE(used.qty, 0)) AS sisa
            FROM produk pr
            LEFT JOIN kuota_harian k ON k.produk_id = pr.id AND k.tanggal = ?
            LEFT JOIN (
                SELECT d.produk_id, SUM(d.qty) AS qty
                FROM detail_pesanan d
                JOIN pesanan p ON p.id = d.pesanan_id
                WHERE p.tgl_kirim >= ? AND p.tgl_kirim < ? AND p.status NOT IN ('ditolak', 'dibatalkan')
                GROUP BY d.produk_id
            ) used ON used.produk_id = pr.id
            ORDER BY pr.id",
            [$date, $start->format('Y-m-d H:i:sP'), $start->modify('+1 day')->format('Y-m-d H:i:sP')]
        )->fetchAll();
    }

    $capacity = 0;
    $used = 0;
    foreach ($items as $item) {
        $capacity += max(0, (int) $item['kuota_harian']);
        // Pesanan produk yang kemudian dinonaktifkan tetap memakai kapasitas.
        $used += (int) $item['terpakai'];
    }

    $maximum = intdiv($capacity * 4, 5);

    return [
        'tanggal' => $date,
        'items' => $items,
        'maxKitchen' => $maximum,
        'kitchenUsed' => $used,
        'kitchenRemaining' => max(0, $maximum - $used),
    ];
}

function daily_quota_validate(array $snapshot, array $quantities): array
{
    $products = array_column($snapshot['items'], null, 'id');
    $lines = [];
    foreach ($quantities as $id => $qty) {
        $product = $products[$id] ?? null;
        if (!$product || (int) $product['kuota_harian'] <= 0) {
            throw new DomainException('Salah satu menu sudah tidak tersedia.');
        }
        // Periksa sisa lebih dahulu agar kekurangan kuota tetap informatif.
        if ($qty > (int) $product['sisa']) {
            throw new DomainException('Kuota harian ' . $product['nama'] . ' tidak mencukupi. Sisa kuota: ' . (int) $product['sisa'] . ' porsi.');
        }
        if ($qty < (int) $product['min_pesan']) {
            throw new DomainException($product['nama'] . ' minimal dipesan ' . $product['min_pesan'] . ' porsi.');
        }
        $lines[] = ['product' => $product, 'qty' => $qty];
    }

    if ($snapshot['kitchenUsed'] + array_sum($quantities) > $snapshot['maxKitchen']) {
        throw new DomainException('Kuota operasional dapur untuk tanggal pengiriman telah mencapai batas maksimal (80%).');
    }

    return $lines;
}

function daily_quota_range($startDate, $endDate): array
{
    $dates = [];
    foreach ([$startDate, $endDate] as $date) {
        if (!is_string($date) || !preg_match('/^\d{4}-\d{2}-\d{2}$/D', $date) || substr($date, 0, 4) === '0000') {
            throw new DomainException('Tanggal harus berformat YYYY-MM-DD.');
        }
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date, new DateTimeZone('Asia/Jakarta'));
        if (!$parsed || $parsed->format('Y-m-d') !== $date) {
            throw new DomainException('Tanggal tidak valid.');
        }
        $dates[] = $parsed;
    }
    if ($dates[1] < $dates[0] || $dates[0]->diff($dates[1])->days > 365) {
        throw new DomainException('Rentang harus berurutan dan maksimal 366 tanggal inklusif.');
    }
    return [$startDate, $endDate];
}

// Pemanggil memegang transaksi dan lock kuota sampai seluruh rentang tersimpan.
function daily_quota_write_range(int $productId, string $start, string $end, ?int $quota): int
{
    if ($quota === null) {
        return q('DELETE FROM kuota_harian WHERE produk_id = ? AND tanggal BETWEEN ? AND ?', [$productId, $start, $end])->rowCount();
    }
    return q("INSERT INTO kuota_harian (produk_id, tanggal, kuota)
        SELECT ?, day::date, ? FROM generate_series(?::timestamp, ?::timestamp, interval '1 day') day
        ON CONFLICT (produk_id, tanggal) DO UPDATE SET kuota = EXCLUDED.kuota",
        [$productId, $quota, $start, $end])->rowCount();
}
