<?php
// Dipakai setelah bootstrap.php. Seluruh pemakaian dihitung dari transaksi.
function daily_quota_snapshot($day, bool $report = false): array {
    if (!is_string($day) || !preg_match('/^\d{4}-\d{2}-\d{2}$/D', $day)) {
        throw new DomainException('Tanggal kuota tidak valid. Gunakan YYYY-MM-DD.');
    }
    $zone = new DateTimeZone('Asia/Jakarta');
    $start = DateTimeImmutable::createFromFormat('!Y-m-d', $day, $zone);
    if (!$start || $start->format('Y-m-d') !== $day) {
        throw new DomainException('Tanggal kuota tidak valid. Gunakan YYYY-MM-DD.');
    }
    $fields = 'pr.id,pr.kode,pr.nama,pr.kategori,pr.harga,pr.gambar,GREATEST(4,pr.min_pesan) min_pesan';
    if ($report) {
        $items = q("SELECT $fields,COALESCE(v.kuota,0) kuota_harian,
            COALESCE(v.kuota,0) kuota,COALESCE(v.terpakai,0) terpakai,
            COALESCE(v.sisa,0) sisa FROM produk pr
            LEFT JOIN v_kuota_harian v ON v.produk_id=pr.id AND v.tanggal=? ORDER BY pr.id",[$day])->fetchAll();
    } else {
    $items = q("SELECT $fields,COALESCE(k.kuota,0) kuota_harian,
        COALESCE(k.kuota,0) kuota,COALESCE(used.qty,0) terpakai,
        GREATEST(0,COALESCE(k.kuota,0)-COALESCE(used.qty,0)) sisa
        FROM produk pr LEFT JOIN kuota_harian k ON k.produk_id=pr.id AND k.tanggal=? LEFT JOIN (
            SELECT d.produk_id,SUM(d.qty) qty FROM detail_pesanan d
            JOIN pesanan p ON p.id=d.pesanan_id
            WHERE p.tgl_kirim>=? AND p.tgl_kirim<? AND p.status<>'ditolak'
            GROUP BY d.produk_id
        ) used ON used.produk_id=pr.id ORDER BY pr.id",
        [$day,$start->format('Y-m-d H:i:sP'), $start->modify('+1 day')->format('Y-m-d H:i:sP')])->fetchAll();
    }
    $capacity = 0;
    $used = 0;
    foreach ($items as $item) {
        $capacity += max(0, (int)$item['kuota_harian']);
        // Pesanan produk yang kemudian dinonaktifkan tetap memakai kapasitas.
        $used += (int)$item['terpakai'];
    }
    $maximum = intdiv($capacity * 4, 5);
    return ['tanggal'=>$day, 'items'=>$items, 'maxKitchen'=>$maximum,
        'kitchenUsed'=>$used, 'kitchenRemaining'=>max(0, $maximum-$used)];
}

function daily_quota_validate(array $snapshot, array $quantities): array {
    $products = array_column($snapshot['items'], null, 'id');
    $lines = [];
    foreach ($quantities as $id=>$qty) {
        $product = $products[$id] ?? null;
        if (!$product || (int)$product['kuota_harian'] <= 0) {
            throw new DomainException('Salah satu menu sudah tidak tersedia.');
        }
        // Periksa sisa lebih dahulu agar kekurangan kuota tetap informatif.
        if ($qty > (int)$product['sisa']) {
            throw new DomainException('Kuota harian '.$product['nama'].' tidak mencukupi. Sisa kuota: '.(int)$product['sisa'].' porsi.');
        }
        if ($qty < (int)$product['min_pesan']) {
            throw new DomainException($product['nama'].' minimal dipesan '.$product['min_pesan'].' porsi.');
        }
        $lines[] = ['product'=>$product, 'qty'=>$qty];
    }
    if ($snapshot['kitchenUsed'] + array_sum($quantities) > $snapshot['maxKitchen']) {
        throw new DomainException('Kuota operasional dapur untuk tanggal pengiriman telah mencapai batas maksimal (80%).');
    }
    return $lines;
}
