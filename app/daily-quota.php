<?php
// Dipakai setelah bootstrap.php. Seluruh pemakaian dihitung dari transaksi.

// Minimum per menu dan maksimum per pesanan tidak menentukan kapasitas dapur harian.
const SENOESA_MIN_PACKAGE_QTY = 4;
const SENOESA_MAX_ORDER_QTY = 50;

/** Minimum efektif untuk satu produk pada snapshot kuota. */
function daily_product_min_qty(array $product): int
{
    return max(SENOESA_MIN_PACKAGE_QTY, (int) ($product['min_pesan'] ?? 0));
}

/**
 * Satu-satunya aturan ketersediaan produk: sisa stok harus mencapai minimum
 * efektif. Dipakai katalog, availability, dan validasi agar tidak ada logika
 * ganda di frontend/API.
 */
function daily_product_available(array $product, bool $holiday = false): bool
{
    if ($holiday) {
        return false;
    }
    return (int) ($product['sisa'] ?? 0) >= daily_product_min_qty($product);
}

function daily_capacity_status(int $remaining, int $capacity, bool $holiday = false): string
{
    if ($holiday) return 'closed';
    if ($remaining <= 0 || $capacity <= 0) return 'full';
    return $remaining * 5 <= $capacity * 2 ? 'nearly_full' : 'available';
}

function daily_kitchen_capacity(array $items): int
{
    return intdiv((int) array_sum(array_column($items, 'kuota')) * 4, 5);
}

function daily_calendar_status(array $snapshot, int $availableItems): string
{
    $status = daily_capacity_status((int) $snapshot['dailyRemaining'], (int) $snapshot['dailyCapacity'], !empty($snapshot['tanggal_libur']));
    if ($status === 'closed' || $status === 'full') return $status;
    if ($availableItems === 0) return 'menu_empty';
    return $status;
}

/**
 * Klaim SQL untuk pesanan yang masih memakai kapasitas.
 * Menunggu pembayaran hanya menahan kuota selama tenggat 1 jam masih aktif;
 * ditolak/dibatalkan (termasional hasil expire) tidak menahan kuota.
 */
function daily_reserved_clause(string $alias = 'p'): string
{
    return "($alias.status IN ('menunggu_validasi', 'diproses', 'selesai') "
        . "OR ($alias.status = 'menunggu_pembayaran' "
        . "AND NULLIF(BTRIM($alias.bukti_bayar), '') IS NULL "
        . "AND $alias.created_at + interval '1 hour' > now()))";
}

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

    $isHoliday = (bool) q('SELECT CASE WHEN EXISTS(
        SELECT 1 FROM tanggal_libur_operasional
        WHERE tanggal_mulai <= ? AND tanggal_selesai >= ?
    ) THEN 1 ELSE 0 END', [$date, $date])->fetchColumn();

    $fields = 'pr.id, pr.kode, pr.nama, pr.kategori, pr.harga, pr.gambar, '
        . 'GREATEST(' . SENOESA_MIN_PACKAGE_QTY . ', pr.min_pesan) AS min_pesan, pr.kuota_harian AS kuota_default';
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
        // Pesanan yang masih aktif menahan stok menu; dibatalkan/ditolak dan
        // unpaid yang sudah lewat tenggat tidak lagi menahan kuota.
        $reserved = daily_reserved_clause('p');
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
                WHERE p.tgl_kirim >= ? AND p.tgl_kirim < ? AND $reserved
                GROUP BY d.produk_id
            ) used ON used.produk_id = pr.id
            ORDER BY pr.id",
            [$date, $start->format('Y-m-d H:i:sP'), $start->modify('+1 day')->format('Y-m-d H:i:sP')]
        )->fetchAll();
    }

    if ($isHoliday) {
        foreach ($items as &$item) {
            $item['kuota'] = 0;
            $item['kuota_harian'] = 0;
            $item['sisa'] = 0;
        }
        unset($item);
    }

    // Kuota efektif sudah menimpa default dengan override dan nol saat libur.
    $maximum = daily_kitchen_capacity($items);
    $rangeParams = [$start->format('Y-m-d H:i:sP'), $start->modify('+1 day')->format('Y-m-d H:i:sP')];
    $dailyUsed = $isHoliday ? 0 : (int) q(
        "SELECT COALESCE(SUM(d.qty), 0)
        FROM detail_pesanan d
        JOIN pesanan p ON p.id = d.pesanan_id
        WHERE p.tgl_kirim >= ? AND p.tgl_kirim < ? AND " . daily_reserved_clause('p'),
        $rangeParams
    )->fetchColumn();

    $used = $dailyUsed;
    $dailyRemaining = max(0, $maximum - $used);
    $dailyStatus = daily_capacity_status($dailyRemaining, $maximum, $isHoliday);

    return [
        'tanggal' => $date,
        'tanggal_libur' => $isHoliday,
        'items' => $items,
        'maxKitchen' => $maximum,
        'kitchenUsed' => $used,
        'kitchenRemaining' => $dailyRemaining,
        'dailyCapacity' => $maximum,
        'dailyUsed' => $used,
        'dailyRemaining' => $dailyRemaining,
        'dailyStatus' => $dailyStatus,
    ];
}

function daily_quota_validate(array $snapshot, array $quantities): array
{
    if (!empty($snapshot['tanggal_libur'])) {
        throw new DomainException('Tanggal pengiriman ini merupakan hari libur. Silakan pilih tanggal lain.');
    }

    $total = array_sum($quantities);
    if ($total < SENOESA_MIN_PACKAGE_QTY) {
        throw new DomainException('Minimal pesanan adalah ' . SENOESA_MIN_PACKAGE_QTY . ' porsi.');
    }
    if ($total > SENOESA_MAX_ORDER_QTY) {
        throw new DomainException('Satu pesanan maksimal ' . SENOESA_MAX_ORDER_QTY . ' porsi.');
    }

    $capacity = (int) ($snapshot['dailyCapacity'] ?? $snapshot['maxKitchen'] ?? 0);
    $used = (int) ($snapshot['dailyUsed'] ?? $snapshot['kitchenUsed'] ?? 0);
    if ($used + $total > $capacity) {
        throw new DomainException('Kuota harian untuk tanggal ini tidak mencukupi. Sisa ' . max(0, $capacity - $used) . ' porsi.');
    }

    $products = array_column($snapshot['items'], null, 'id');
    $lines = [];
    foreach ($quantities as $id => $qty) {
        $product = $products[$id] ?? null;
        if (!$product || (int) $product['kuota_harian'] <= 0) {
            throw new DomainException('Salah satu menu sudah tidak tersedia.');
        }
        if ($qty > (int) $product['sisa']) {
            throw new DomainException('Stok ' . $product['nama'] . ' tidak mencukupi. Sisa ' . (int) $product['sisa'] . ' porsi.');
        }
        if ($qty < (int) $product['min_pesan']) {
            throw new DomainException($product['nama'] . ' minimal dipesan ' . $product['min_pesan'] . ' porsi.');
        }
        $lines[] = ['product' => $product, 'qty' => $qty];
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

/**
 * Lock global urut untuk transaksi yang membaca kuota dan tanggal operasional.
 *
 * Urutan produk -> kuota_harian -> tanggal_libur_operasional dipakai bersama
 * oleh checkout, pesanan offline, respons pelanggan, dan konfirmasi penggantian
 * agar tidak ada deadlock serta pengecekan libur selalu dalam transaksi yang
 * sama dengan pengecekan kuota.
 */
function daily_quota_lock_tables(): void
{
    q('LOCK TABLE produk IN SHARE ROW EXCLUSIVE MODE');
    q('LOCK TABLE kuota_harian IN SHARE ROW EXCLUSIVE MODE');
    q('LOCK TABLE tanggal_libur_operasional IN SHARE ROW EXCLUSIVE MODE');
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
