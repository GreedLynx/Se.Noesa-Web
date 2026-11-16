<?php
// Helper bersama notifikasi perubahan menu.
// Target selalu dihitung dari detail_pesanan, bukan dari ID yang dikirim browser.

const MENU_NOTICE_ACTIVE_STATUSES = ['menunggu_pembayaran', 'menunggu_validasi', 'diproses'];

function menu_notice_active_status_sql(): string
{
    return "('" . implode("','", MENU_NOTICE_ACTIVE_STATUSES) . "')";
}

function menu_notice_default_options(): array
{
    return [
        ['kode_opsi' => 'menu_pengganti', 'label' => 'Pilih menu pengganti'],
        ['kode_opsi' => 'hubungi_admin', 'label' => 'Hubungi Admin'],
        ['kode_opsi' => 'tunggu_informasi', 'label' => 'Tunggu informasi'],
    ];
}

function menu_notice_product(int $productId): ?array
{
    $product = q(
        'SELECT id, kode, nama, kuota_harian FROM produk WHERE id = ?',
        [$productId]
    )->fetch();

    return $product ?: null;
}

/**
 * Pesanan aktif yang memuat produk pada tanggal kirim dengan kuota efektif 0.
 *
 * Parameter $lockOrders dipakai saat pembuatan notifikasi agar status pesanan
 * dan baris detail tidak berubah sampai transaksi selesai.
 */
function menu_notice_targets(int $productId, bool $lockOrders = false): array
{
    $activeStatuses = menu_notice_active_status_sql();
    $lock = $lockOrders ? ' FOR SHARE OF p, d' : '';

    return q(
        "SELECT d.id AS detail_pesanan_id, d.qty, d.harga,
            p.id AS pesanan_id, p.kode AS kode_pesanan, p.nama_pemesan,
            p.status AS status_pesanan, p.sumber, p.tgl_kirim,
            p.telepon_penerima, p.user_id,
            u.username, u.email, u.phone,
            (p.tgl_kirim AT TIME ZONE 'Asia/Jakarta')::date AS tanggal_kirim,
            COALESCE(k.kuota, pr.kuota_harian) AS kuota_efektif,
            EXISTS (
                SELECT 1
                FROM pemberitahuan_perubahan_menu_pesanan aktif
                WHERE aktif.detail_pesanan_id = d.id
                  AND aktif.status_respons IN (
                      'menunggu_respons', 'menunggu_konfirmasi', 'menunggu_tindak_lanjut'
                  )
            ) AS sudah_dinotifikasi
         FROM detail_pesanan d
         JOIN pesanan p ON p.id = d.pesanan_id
         JOIN produk pr ON pr.id = d.produk_id
         LEFT JOIN users u ON u.id = p.user_id
         LEFT JOIN kuota_harian k
            ON k.produk_id = d.produk_id
           AND k.tanggal = (p.tgl_kirim AT TIME ZONE 'Asia/Jakarta')::date
         WHERE d.produk_id = ?
           AND p.status IN $activeStatuses
           AND p.tgl_kirim IS NOT NULL
           AND COALESCE(k.kuota, pr.kuota_harian) <= 0
         ORDER BY tanggal_kirim, p.id, d.id$lock",
        [$productId]
    )->fetchAll();
}

/** Jumlah notifikasi berbeda yang belum dibaca oleh pelanggan. */
function menu_notice_customer_unread_count(int $userId): int
{
    return (int) q(
        "SELECT COUNT(DISTINCT r.pemberitahuan_id)
         FROM pemberitahuan_perubahan_menu_pesanan r
         JOIN detail_pesanan d ON d.id = r.detail_pesanan_id
         JOIN pesanan p ON p.id = d.pesanan_id
         WHERE p.user_id = ?
           AND p.sumber = 'online'
           AND r.dibaca_at IS NULL",
        [$userId]
    )->fetchColumn();
}

function menu_notice_order_notifications(int $orderId, int $userId): array
{
    return q(
        "SELECT n.id, n.kode_produk_snapshot, n.nama_produk_snapshot,
                n.catatan_admin, n.created_at, r.id AS penerima_id,
                r.status_respons, r.status_penyelesaian_selisih,
                r.kode_produk_lama_snapshot, r.nama_produk_lama_snapshot,
                r.kode_produk_pengganti_snapshot, r.nama_produk_pengganti_snapshot,
                r.selisih_total
         FROM pemberitahuan_perubahan_menu_pesanan r
         JOIN pemberitahuan_perubahan_menu n ON n.id = r.pemberitahuan_id
         JOIN detail_pesanan d ON d.id = r.detail_pesanan_id
         JOIN pesanan p ON p.id = d.pesanan_id
         WHERE p.id = ? AND p.user_id = ? AND p.sumber = 'online'
         ORDER BY n.created_at DESC, n.id DESC",
        [$orderId, $userId]
    )->fetchAll();
}

/**
 * Menu pengganti yang kapasitas efektifnya masih tersedia pada tanggal kirim.
 * Kecukupan porsi tetap divalidasi ulang oleh Admin saat persetujuan.
 */
function menu_notice_available_products(string $date, int $excludeProductId, int $requiredQty = 1): array
{
    require_once __DIR__ . '/daily-quota.php';
    $snapshot = daily_quota_snapshot($date);
    $products = array_filter(
        $snapshot['items'],
        static fn(array $product): bool =>
            (int) $product['id'] !== $excludeProductId
            && (int) $product['kuota_harian'] > 0
            && (int) $product['sisa'] >= $requiredQty
            && $requiredQty >= (int) $product['min_pesan']
    );

    return array_map(static function (array $product): array {
        return [
            'id' => (int) $product['id'],
            'kode' => $product['kode'],
            'nama' => $product['nama'],
            'kategori' => $product['kategori'],
            'harga' => (int) $product['harga'],
            'gambar' => $product['gambar'],
            'kuota_efektif' => (int) $product['kuota_harian'],
            'sisa' => (int) $product['sisa'],
            'min_pesan' => (int) $product['min_pesan'],
        ];
    }, array_values($products));
}

function menu_notice_confirm_replacement(int $recipientId, int $adminId): array
{
    require_once __DIR__ . '/daily-quota.php';
    $db = db();
    $ownsTransaction = !$db->inTransaction();
    if ($ownsTransaction) {
        $db->beginTransaction();
    }

    try {
        q('LOCK TABLE produk IN SHARE ROW EXCLUSIVE MODE');
        q('LOCK TABLE kuota_harian IN SHARE ROW EXCLUSIVE MODE');

        $row = q(
            "SELECT r.id, r.status_respons, r.produk_lama_id,
                    r.detail_pesanan_id,
                    r.qty_lama, r.harga_lama, r.produk_pengganti_id,
                    r.kode_produk_pengganti_snapshot, r.nama_produk_pengganti_snapshot,
                    r.qty_pengganti, r.harga_pengganti, o.kode_opsi,
                    d.produk_id AS current_produk_id, d.qty AS current_qty,
                    d.harga AS current_harga, p.id AS pesanan_id,
                    p.status AS status_pesanan, p.sumber, p.total AS total_pesanan,
                    (p.tgl_kirim AT TIME ZONE 'Asia/Jakarta')::date AS tanggal_kirim
             FROM pemberitahuan_perubahan_menu_pesanan r
             JOIN detail_pesanan d ON d.id = r.detail_pesanan_id
             JOIN pesanan p ON p.id = d.pesanan_id
             LEFT JOIN pemberitahuan_perubahan_menu_opsi o ON o.id = r.opsi_id
             WHERE r.id = ?
             FOR UPDATE OF r, d, p",
            [$recipientId]
        )->fetch();

        if (!$row) {
            throw new DomainException('Respons notifikasi tidak ditemukan.', 404);
        }
        if ($row['status_respons'] !== 'menunggu_konfirmasi' || $row['kode_opsi'] !== 'menu_pengganti') {
            throw new DomainException('Respons ini tidak sedang menunggu konfirmasi penggantian.', 409);
        }
        if (!in_array($row['status_pesanan'], MENU_NOTICE_ACTIVE_STATUSES, true)) {
            throw new DomainException('Pesanan sudah tidak aktif dan tidak dapat diubah.', 409);
        }
        if ($row['sumber'] !== 'online') {
            throw new DomainException('Pesanan offline harus ditindaklanjuti secara manual.', 409);
        }
        if ($row['produk_lama_id'] === null
            || (int) $row['current_produk_id'] !== (int) $row['produk_lama_id']
            || (int) $row['current_qty'] !== (int) $row['qty_lama']
            || (int) $row['current_harga'] !== (int) $row['harga_lama']) {
            throw new DomainException('Detail pesanan sudah berubah sejak notifikasi dibuat.', 409);
        }
        if ($row['produk_pengganti_id'] === null
            || $row['harga_pengganti'] === null
            || $row['qty_pengganti'] === null
            || (int) $row['qty_pengganti'] !== (int) $row['qty_lama']) {
            throw new DomainException('Snapshot menu pengganti tidak lengkap atau jumlahnya berubah.', 409);
        }

        $replacement = q(
            'SELECT id
             FROM produk
             WHERE id = ?
             FOR SHARE',
            [(int) $row['produk_pengganti_id']]
        )->fetch();
        if (!$replacement) {
            throw new DomainException('Menu pengganti sudah tidak tersedia.', 409);
        }
        $available = menu_notice_available_products(
            (string) $row['tanggal_kirim'],
            (int) $row['produk_lama_id'],
            (int) $row['qty_lama']
        );
        $isAvailable = false;
        foreach ($available as $product) {
            if ((int) $product['id'] === (int) $replacement['id']) {
                $isAvailable = true;
                break;
            }
        }
        if (!$isAvailable) {
            throw new DomainException('Kuota menu pengganti tidak mencukupi atau jumlahnya di bawah minimum.', 409);
        }

        $oldLineTotal = (int) $row['qty_lama'] * (int) $row['harga_lama'];
        $newLineTotal = (int) $row['qty_pengganti'] * (int) $row['harga_pengganti'];
        $difference = $newLineTotal - $oldLineTotal;
        $oldOrderTotal = (int) $row['total_pesanan'];
        $newOrderTotal = $oldOrderTotal + $difference;
        if ($newOrderTotal < 0 || $newOrderTotal > 2147483647) {
            throw new DomainException('Total pesanan setelah perubahan berada di luar batas yang diizinkan.', 409);
        }

        q(
            'UPDATE detail_pesanan
             SET produk_id = ?, harga = ?, sudah_dicek = FALSE
             WHERE id = ?',
            [
                (int) $replacement['id'],
                (int) $row['harga_pengganti'],
                (int) $row['detail_pesanan_id'],
            ]
        );
        q(
            'UPDATE pesanan
             SET total = ?
             WHERE id = ?',
            [$newOrderTotal, (int) $row['pesanan_id']]
        );

        $settlementStatus = match (true) {
            $difference > 0 => 'menunggu_tagihan',
            $difference < 0 => 'menunggu_pengembalian',
            default => 'tidak_ada',
        };
        $responseStatus = $difference === 0 ? 'selesai' : 'dikonfirmasi';

        q(
            'UPDATE pemberitahuan_perubahan_menu_pesanan
             SET status_respons = ?,
                 selisih_total = ?,
                 status_penyelesaian_selisih = ?,
                 total_pesanan_lama = ?,
                 total_pesanan_baru = ?,
                 confirmed_by = ?,
                 confirmed_at = now()
             WHERE id = ?',
            [
                $responseStatus,
                $difference,
                $settlementStatus,
                $oldOrderTotal,
                $newOrderTotal,
                $adminId,
                $recipientId,
            ]
        );

        if ($ownsTransaction) {
            $db->commit();
        }

        return [
            'id' => $recipientId,
            'pesanan_id' => (int) $row['pesanan_id'],
            'status_respons' => $responseStatus,
            'status_penyelesaian_selisih' => $settlementStatus,
            'selisih_total' => $difference,
            'total_pesanan_lama' => $oldOrderTotal,
            'total_pesanan_baru' => $newOrderTotal,
            'status_pesanan' => $row['status_pesanan'],
        ];
    } catch (Throwable $error) {
        if ($ownsTransaction && $db->inTransaction()) {
            $db->rollBack();
        }
        throw $error;
    }
}

function menu_notice_settle_difference(int $recipientId, int $adminId): array
{
    $db = db();
    $ownsTransaction = !$db->inTransaction();
    if ($ownsTransaction) {
        $db->beginTransaction();
    }

    try {
        $row = q(
            "SELECT id, status_respons, status_penyelesaian_selisih
             FROM pemberitahuan_perubahan_menu_pesanan
             WHERE id = ?
             FOR UPDATE",
            [$recipientId]
        )->fetch();
        if (!$row) {
            throw new DomainException('Respons notifikasi tidak ditemukan.', 404);
        }
        if ($row['status_respons'] !== 'dikonfirmasi'
            || !in_array($row['status_penyelesaian_selisih'], ['menunggu_tagihan', 'menunggu_pengembalian'], true)) {
            throw new DomainException('Tidak ada selisih harga yang menunggu penyelesaian.', 409);
        }

        q(
            "UPDATE pemberitahuan_perubahan_menu_pesanan
             SET status_respons = 'selesai',
                 status_penyelesaian_selisih = 'selesai',
                 selisih_diselesaikan_oleh = ?,
                 selisih_diselesaikan_at = now()
             WHERE id = ?",
            [$adminId, $recipientId]
        );

        if ($ownsTransaction) {
            $db->commit();
        }

        return ['id' => $recipientId, 'status_respons' => 'selesai', 'status_penyelesaian_selisih' => 'selesai'];
    } catch (Throwable $error) {
        if ($ownsTransaction && $db->inTransaction()) {
            $db->rollBack();
        }
        throw $error;
    }
}

function menu_notice_complete_follow_up(int $recipientId, int $adminId, string $note): array
{
    $db = db();
    $ownsTransaction = !$db->inTransaction();
    if ($ownsTransaction) {
        $db->beginTransaction();
    }

    try {
        $row = q(
            "SELECT id, status_respons, opsi_id
             FROM pemberitahuan_perubahan_menu_pesanan
             WHERE id = ?
             FOR UPDATE",
            [$recipientId]
        )->fetch();
        if (!$row) {
            throw new DomainException('Respons notifikasi tidak ditemukan.', 404);
        }
        $optionCode = q(
            'SELECT kode_opsi FROM pemberitahuan_perubahan_menu_opsi WHERE id = ?',
            [(int) $row['opsi_id']]
        )->fetchColumn();
        if ($row['status_respons'] !== 'menunggu_tindak_lanjut'
            || !in_array($optionCode, ['hubungi_admin', 'tunggu_informasi'], true)) {
            throw new DomainException('Respons ini tidak sedang menunggu tindak lanjut manual.', 409);
        }
        if (trim($note) === '') {
            throw new DomainException('Catatan tindak lanjut wajib diisi.', 422);
        }

        q(
            "UPDATE pemberitahuan_perubahan_menu_pesanan
             SET status_respons = 'selesai',
                 tindak_lanjut_oleh = ?,
                 tindak_lanjut_at = now(),
                 catatan_tindak_lanjut = ?
             WHERE id = ?",
            [$adminId, trim($note), $recipientId]
        );

        if ($ownsTransaction) {
            $db->commit();
        }

        return ['id' => $recipientId, 'status_respons' => 'selesai', 'catatan_tindak_lanjut' => trim($note)];
    } catch (Throwable $error) {
        if ($ownsTransaction && $db->inTransaction()) {
            $db->rollBack();
        }
        throw $error;
    }
}
