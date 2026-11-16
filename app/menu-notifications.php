<?php
// Helper bersama notifikasi perubahan menu.
// Target selalu dihitung dari detail_pesanan, bukan dari ID yang dikirim browser.

const MENU_NOTICE_ACTIVE_STATUSES = ['menunggu_pembayaran', 'menunggu_validasi', 'diproses'];
const MENU_NOTICE_OPEN_STATUSES = ['menunggu_respons', 'menunggu_konfirmasi', 'menunggu_tindak_lanjut'];
const MENU_NOTICE_ORDER_CLOSURE_TYPES = ['pembatalan_pesanan', 'kedaluwarsa_pesanan'];

// Kebijakan harga: total pesanan memakai snapshot yang dikunci saat pelanggan
// memilih. Perubahan harga katalog setelahnya hanya dicatat sebagai audit.
const MENU_NOTICE_PRICE_POLICY = 'snapshot_saat_pelanggan_memilih';

// Namespace key advisory dua-int (int4, int4). PostgreSQL memisahkan key space
// dua-int dari key bigint yang dipakai scripts/migrate.php, sehingga tidak ada
// tabrakan dengan advisory lock migrasi.
const MENU_NOTICE_CREATE_LOCK_NAMESPACE = 0x4D4E4F54; // "MNOT"

/**
 * Serialisasi pembuatan notifikasi untuk satu produk.
 *
 * Advisory lock transaksi dilepas otomatis saat commit atau rollback. Lock ini
 * diambil sebelum target dihitung ulang sehingga dua Admin tidak dapat membaca
 * target lama yang sama lalu menyisipkan penerima duplikat.
 */
function menu_notice_lock_creation(int $productId): void
{
    // Lock transaksi di luar BEGIN akan dilepas saat statement selesai dan tidak
    // melindungi pengecekan target; tolak pemakaian yang salah sejak awal.
    if (!db()->inTransaction()) {
        throw new LogicException('Lock pembuatan notifikasi harus dipanggil di dalam transaksi.');
    }

    q('SELECT pg_advisory_xact_lock(?::int, ?::int)', [
        MENU_NOTICE_CREATE_LOCK_NAMESPACE,
        $productId,
    ]);
}


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

/**
 * Informasi harga penggantian untuk pelanggan dan Admin.
 *
 * Harga snapshot yang dipakai pada total pesanan; harga katalog hanya untuk
 * transparansi dan audit perubahan harga setelah pelanggan memilih.
 */
function menu_notice_price_info(?int $snapshotPrice, ?int $catalogPrice): ?array
{
    if ($snapshotPrice === null) {
        return null;
    }

    return [
        'harga_snapshot' => $snapshotPrice,
        'harga_katalog_sekarang' => $catalogPrice,
        'perbedaan_harga_katalog' => $catalogPrice === null ? null : $catalogPrice - $snapshotPrice,
        'kebijakan_harga' => MENU_NOTICE_PRICE_POLICY,
        'catatan_harga' => 'Harga penggantian dikunci pada saat pelanggan memilih.',
    ];
}

/** Tahap pembayaran yang bisa dibaca Admin dari status pesanan dan bukti. */
function menu_notice_payment_stage(string $status, string $source, bool $hasProof): array
{
    if ($source === 'offline') {
        return ['manual_offline', 'Pesanan offline — pembayaran ditangani manual', false];
    }
    if ($status === 'menunggu_pembayaran') {
        return ['belum_membayar', 'Belum mengunggah bukti pembayaran', false];
    }
    if ($status === 'ditolak') {
        return ['tidak_berlaku', 'Pesanan ditolak; pembayaran tidak diproses', false];
    }
    if ($status === 'dibatalkan') {
        return ['tidak_berlaku', 'Pesanan dibatalkan; refund ditangani Admin di luar aplikasi', false];
    }
    if (!$hasProof) {
        return ['tidak_ada_bukti', 'Belum ada bukti pembayaran yang tersimpan', false];
    }
    if ($status === 'menunggu_validasi') {
        return ['menunggu_verifikasi', 'Bukti terkirim; menunggu verifikasi Admin', false];
    }

    return ['terverifikasi', 'Bukti pembayaran terverifikasi Admin', true];
}

/**
 * Ringkasan pembayaran per penerima notifikasi.
 *
 * Sumber nominal adalah total pesanan; bukti dan nama pembayar berasal dari
 * pesanan. Helper ini tidak membuat status pembayaran baru dan tidak memindahkan
 * uang.
 */
function menu_notice_payment_summary(array $row): array
{
    $status = (string) ($row['status_pesanan'] ?? '');
    $source = (string) ($row['sumber'] ?? 'online');
    $proof = isset($row['bukti_bayar']) ? trim((string) $row['bukti_bayar']) : '';
    $payer = isset($row['nama_pembayar']) ? trim((string) $row['nama_pembayar']) : '';
    [$stage, $label, $paid] = menu_notice_payment_stage($status, $source, $proof !== '');

    $integerOrNull = static function ($value): ?int {
        return $value === null || $value === '' ? null : (int) $value;
    };

    return [
        'status_pembayaran' => $stage,
        'tahap_pembayaran' => $label,
        'sudah_membayar' => $paid,
        'nominal_tagihan' => (int) ($row['total_pesanan'] ?? 0),
        'total_pesanan_lama' => $integerOrNull($row['total_pesanan_lama'] ?? null),
        'total_pesanan_baru' => $integerOrNull($row['total_pesanan_baru'] ?? null),
        'selisih_total' => $integerOrNull($row['selisih_total'] ?? null),
        'status_penyelesaian_selisih' => (string) ($row['status_penyelesaian_selisih'] ?? 'belum_dihitung'),
        'bukti_bayar' => $proof === '' ? null : $proof,
        'nama_pembayar' => $payer === '' ? null : $payer,
        'sumber' => $source,
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
            CASE WHEN EXISTS (
                SELECT 1
                FROM tanggal_libur_operasional libur
                WHERE libur.tanggal_mulai <= (p.tgl_kirim AT TIME ZONE 'Asia/Jakarta')::date
                  AND libur.tanggal_selesai >= (p.tgl_kirim AT TIME ZONE 'Asia/Jakarta')::date
            ) THEN 0 ELSE COALESCE(k.kuota, pr.kuota_harian) END AS kuota_efektif,
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
           AND (
               COALESCE(k.kuota, pr.kuota_harian) <= 0
               OR EXISTS (
                   SELECT 1
                   FROM tanggal_libur_operasional libur
                   WHERE libur.tanggal_mulai <= (p.tgl_kirim AT TIME ZONE 'Asia/Jakarta')::date
                     AND libur.tanggal_selesai >= (p.tgl_kirim AT TIME ZONE 'Asia/Jakarta')::date
               )
           )
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
                r.selisih_total,
                r.jenis_penutupan, r.alasan_penutupan, r.ditutup_oleh, r.ditutup_at
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
        // Urutan lock kanonik yang sama dengan checkout: produk, kuota_harian,
        // lalu tanggal libur operasional. Pengecukupan kuota/libur diperiksa
        // setelah semua lock ini diperoleh.
        daily_quota_lock_tables();

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
            'SELECT id, harga
             FROM produk
             WHERE id = ?
             FOR SHARE',
            [(int) $row['produk_pengganti_id']]
        )->fetch();
        if (!$replacement) {
            throw new DomainException('Menu pengganti sudah tidak tersedia.', 409);
        }
        $catalogPrice = (int) $replacement['harga'];
        $catalogDifference = $catalogPrice - (int) $row['harga_pengganti'];
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
                 harga_katalog_saat_konfirmasi = ?,
                 perbedaan_harga_katalog = ?,
                 confirmed_by = ?,
                 confirmed_at = now()
             WHERE id = ?',
            [
                $responseStatus,
                $difference,
                $settlementStatus,
                $oldOrderTotal,
                $newOrderTotal,
                $catalogPrice,
                $catalogDifference,
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
            'info_harga' => menu_notice_price_info((int) $row['harga_pengganti'], $catalogPrice),
            'perbedaan_harga_katalog' => $catalogDifference,
            'harga_katalog_saat_konfirmasi' => $catalogPrice,
            'kebijakan_harga' => MENU_NOTICE_PRICE_POLICY,
        ];
    } catch (Throwable $error) {
        if ($ownsTransaction && $db->inTransaction()) {
            $db->rollBack();
        }
        throw $error;
    }
}

/**
 * Admin menolak pilihan menu pengganti. Hanya status respons dan audit penutupan
 * yang berubah; detail pesanan, total, dan pembayaran tidak disentuh.
 */
function menu_notice_reject_replacement(int $recipientId, int $adminId, string $reason): array
{
    $reason = trim($reason);
    if ($reason === '') {
        throw new DomainException('Alasan penolakan penggantian wajib diisi.', 422);
    }
    if (mb_strlen($reason) > 1000) {
        throw new DomainException('Alasan penolakan maksimal 1000 karakter.', 422);
    }

    $db = db();
    $ownsTransaction = !$db->inTransaction();
    if ($ownsTransaction) {
        $db->beginTransaction();
    }

    try {
        $row = q(
            "SELECT r.id, r.status_respons, o.kode_opsi,
                    p.id AS pesanan_id, p.status AS status_pesanan, p.sumber
             FROM pemberitahuan_perubahan_menu_pesanan r
             JOIN detail_pesanan d ON d.id = r.detail_pesanan_id
             JOIN pesanan p ON p.id = d.pesanan_id
             LEFT JOIN pemberitahuan_perubahan_menu_opsi o ON o.id = r.opsi_id
             WHERE r.id = ?
             FOR UPDATE OF r, p",
            [$recipientId]
        )->fetch();

        if (!$row) {
            throw new DomainException('Respons notifikasi tidak ditemukan.', 404);
        }
        if ($row['status_respons'] !== 'menunggu_konfirmasi' || $row['kode_opsi'] !== 'menu_pengganti') {
            throw new DomainException('Respons ini tidak sedang menunggu konfirmasi penggantian.', 409);
        }
        if (!in_array($row['status_pesanan'], MENU_NOTICE_ACTIVE_STATUSES, true)) {
            throw new DomainException('Pesanan sudah tidak aktif dan penggantian tidak dapat ditolak.', 409);
        }
        if ($row['sumber'] !== 'online') {
            throw new DomainException('Pesanan offline harus ditindaklanjuti secara manual.', 409);
        }

        q(
            "UPDATE pemberitahuan_perubahan_menu_pesanan
             SET status_respons = 'selesai',
                 jenis_penutupan = 'penolakan_pengganti',
                 alasan_penutupan = ?,
                 ditutup_oleh = ?,
                 ditutup_at = now()
             WHERE id = ?
               AND status_respons = 'menunggu_konfirmasi'",
            [$reason, $adminId, $recipientId]
        );

        if ($ownsTransaction) {
            $db->commit();
        }

        return [
            'id' => $recipientId,
            'pesanan_id' => (int) $row['pesanan_id'],
            'status_respons' => 'selesai',
            'jenis_penutupan' => 'penolakan_pengganti',
            'alasan_penutupan' => $reason,
            'status_pesanan' => $row['status_pesanan'],
        ];
    } catch (Throwable $error) {
        if ($ownsTransaction && $db->inTransaction()) {
            $db->rollBack();
        }
        throw $error;
    }
}

/**
 * Tutup seluruh respons yang masih terbuka untuk satu pesanan.
 * Dipakai oleh transaksi pembatalan dan expiration; status selesai/dikonfirmasi
 * tidak pernah ditimpa dan riwayat tetap tersimpan.
 */
function menu_notice_close_for_order(
    int $orderId,
    string $closureType,
    string $reason,
    ?int $actorId = null
): int {
    if (!in_array($closureType, MENU_NOTICE_ORDER_CLOSURE_TYPES, true)) {
        throw new DomainException('Jenis penutupan pesanan tidak valid.', 422);
    }
    $reason = trim($reason);
    if ($reason === '') {
        throw new DomainException('Alasan penutupan notifikasi wajib diisi.', 422);
    }
    if (mb_strlen($reason) > 1000) {
        throw new DomainException('Alasan penutupan maksimal 1000 karakter.', 422);
    }

    return q(
        "UPDATE pemberitahuan_perubahan_menu_pesanan r
         SET status_respons = 'selesai',
             jenis_penutupan = ?,
             alasan_penutupan = ?,
             ditutup_oleh = ?,
             ditutup_at = now()
         FROM detail_pesanan d
         WHERE d.id = r.detail_pesanan_id
           AND d.pesanan_id = ?
           AND r.status_respons IN (
               'menunggu_respons', 'menunggu_konfirmasi', 'menunggu_tindak_lanjut'
           )",
        [$closureType, $reason, $actorId, $orderId]
    )->rowCount();
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
            "SELECT r.id, r.status_respons, r.opsi_id, p.sumber, p.user_id
             FROM pemberitahuan_perubahan_menu_pesanan r
             JOIN detail_pesanan d ON d.id = r.detail_pesanan_id
             JOIN pesanan p ON p.id = d.pesanan_id
             WHERE r.id = ?
             FOR UPDATE OF r",
            [$recipientId]
        )->fetch();
        if (!$row) {
            throw new DomainException('Respons notifikasi tidak ditemukan.', 404);
        }

        // Penerima offline tidak memiliki akun/opsi respons, jadi tindak lanjut
        // manual diselesaikan langsung dari status awal menunggu_respons.
        $isOffline = $row['sumber'] === 'offline' || $row['user_id'] === null;
        if ($isOffline) {
            $canComplete = $row['status_respons'] === 'menunggu_respons';
        } else {
            $optionCode = q(
                'SELECT kode_opsi FROM pemberitahuan_perubahan_menu_opsi WHERE id = ?',
                [(int) $row['opsi_id']]
            )->fetchColumn();
            $canComplete = $row['status_respons'] === 'menunggu_tindak_lanjut'
                && in_array($optionCode, ['hubungi_admin', 'tunggu_informasi'], true);
        }
        if (!$canComplete) {
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
