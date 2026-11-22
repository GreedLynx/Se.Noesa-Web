<?php
// Admin Reconciliation untuk pembayaran QRIS terlambat.
// Batasan: tidak mengubah pesanan lama, status pesanan, kuota, stok, atau checkout normal.

require_once __DIR__ . '/proof-storage.php';

function late_payment_table_available(): bool
{
    static $available = null;
    if ($available === null) {
        try {
            $available = (bool) q("SELECT to_regclass('pembayaran_terlambat') IS NOT NULL")->fetchColumn();
        } catch (Throwable $e) {
            $available = false;
        }
    }
    return $available;
}

function late_payment_statuses(): array
{
    return ['menunggu_verifikasi', 'terverifikasi', 'tidak_valid'];
}

function late_payment_parse_datetime($value): string
{
    $value = trim((string) $value);
    if ($value === '') throw new DomainException('Waktu transaksi wajib diisi.');
    $zone = new DateTimeZone('Asia/Jakarta');
    foreach (['!Y-m-d\TH:i', '!Y-m-d H:i', '!Y-m-d\TH:i:s'] as $format) {
        $date = DateTimeImmutable::createFromFormat($format, $value, $zone);
        $errors = DateTimeImmutable::getLastErrors();
        if ($date && (!$errors || (!$errors['warning_count'] && !$errors['error_count']))) {
            return $date->format('Y-m-d H:i:sP');
        }
    }
    throw new DomainException('Format waktu transaksi tidak valid.');
}

function late_payment_log(int $id, string $action, ?string $before, ?string $after, ?string $reason, int $actorId): void
{
    q('INSERT INTO pembayaran_terlambat_log
        (rekonsiliasi_id, aksi, status_sebelum, status_sesudah, alasan, actor_id, created_at)
        VALUES (?, ?, ?, ?, ?, ?, clock_timestamp())',
        [$id, $action, $before, $after, $reason !== null && $reason !== '' ? $reason : null, $actorId]);
}

function late_payment_get(int $id): ?array
{
    if (!late_payment_table_available()) return null;
    $row = q('SELECT r.*, p.kode kode_pesanan, p.nama_pemesan, p.telepon_penerima, p.total total_pesanan,
            p.status status_pesanan, p.tgl_kirim, p.created_at tanggal_pesanan,
            u.username admin_pencatat, v.username admin_verifikator
        FROM pembayaran_terlambat r
        JOIN pesanan p ON p.id = r.pesanan_id
        LEFT JOIN users u ON u.id = r.dicatat_oleh
        LEFT JOIN users v ON v.id = r.diverifikasi_oleh
        WHERE r.id = ?', [$id])->fetch();
    return $row ?: null;
}

function late_payment_list(): array
{
    if (!late_payment_table_available()) return [];
    return q('SELECT r.id, r.pesanan_id, r.nominal_dilaporkan, r.waktu_transaksi,
            r.status_rekonsiliasi, r.dicatat_at, r.diverifikasi_at, r.tindak_lanjut,
            p.kode, p.nama_pemesan, p.telepon_penerima, p.total, p.status status_pesanan,
            p.tgl_kirim, p.created_at tanggal_pesanan
        FROM pembayaran_terlambat r
        JOIN pesanan p ON p.id = r.pesanan_id
        ORDER BY r.dicatat_at DESC, r.id DESC')->fetchAll();
}

function late_payment_logs(int $id): array
{
    if (!late_payment_table_available()) return [];
    return q('SELECT l.*, u.username actor
        FROM pembayaran_terlambat_log l
        LEFT JOIN users u ON u.id = l.actor_id
        WHERE l.rekonsiliasi_id = ? ORDER BY l.created_at, l.id', [$id])->fetchAll();
}

function late_payment_record(int $orderId, array $post, array $file, int $adminId, bool $fromUpload = true): array
{
    if (!late_payment_table_available()) throw new DomainException('Fitur rekonsiliasi belum tersedia pada database ini.');
    if ($orderId < 1) throw new DomainException('ID pesanan tidak valid.');

    $db = db();
    $ownsTransaction = !$db->inTransaction();
    if ($ownsTransaction) $db->beginTransaction();
    $stored = null;
    try {
        $order = q('SELECT id, status FROM pesanan WHERE id = ? FOR UPDATE', [$orderId])->fetch();
        if (!$order) throw new DomainException('Pesanan tidak ditemukan.');
        if ($order['status'] !== 'dibatalkan') {
            throw new DomainException('Rekonsiliasi hanya untuk pesanan dibatalkan/kedaluwarsa.');
        }
        if (q('SELECT 1 FROM pembayaran_terlambat WHERE pesanan_id = ?', [$orderId])->fetchColumn()) {
            throw new DomainException('Pesanan ini sudah memiliki catatan rekonsiliasi.');
        }

        $nominal = filter_var($post['nominal_dilaporkan'] ?? null, FILTER_VALIDATE_INT);
        if ($nominal === false || $nominal < 0) throw new DomainException('Nominal pembayaran tidak valid.');
        $waktu = late_payment_parse_datetime($post['waktu_transaksi'] ?? '');
        $referensi = trim((string) ($post['referensi_transaksi'] ?? ''));
        if (strlen($referensi) > 100) throw new DomainException('Referensi transaksi maksimal 100 karakter.');
        $catatan = trim((string) ($post['catatan_admin'] ?? ''));
        if (strlen($catatan) > 1000) throw new DomainException('Catatan Admin maksimal 1000 karakter.');

        $stored = proof_store($file, 'late-' . $orderId, $fromUpload);
        $id = (int) q('INSERT INTO pembayaran_terlambat
            (pesanan_id, bukti_path, nominal_dilaporkan, waktu_transaksi, referensi_transaksi,
             status_rekonsiliasi, catatan_admin, dicatat_oleh, dicatat_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, clock_timestamp()) RETURNING id',
            [$orderId, $stored, $nominal, $waktu, $referensi !== '' ? $referensi : null,
             'menunggu_verifikasi', $catatan !== '' ? $catatan : null, $adminId])->fetchColumn();
        late_payment_log($id, 'dicatat', null, 'menunggu_verifikasi', $catatan !== '' ? $catatan : null, $adminId);
        if ($ownsTransaction) $db->commit();
        return late_payment_get($id) ?? ['id' => $id];
    } catch (Throwable $e) {
        if ($ownsTransaction && $db->inTransaction()) $db->rollBack();
        if ($stored !== null) proof_delete_if_unreferenced($stored);
        throw $e;
    }
}

function late_payment_verify(int $id, string $status, string $note, int $adminId): array
{
    if (!late_payment_table_available()) throw new DomainException('Fitur rekonsiliasi belum tersedia pada database ini.');
    if (!in_array($status, ['terverifikasi', 'tidak_valid'], true)) {
        throw new DomainException('Hasil verifikasi tidak dikenal.');
    }
    $note = trim($note);
    if ($status === 'tidak_valid' && $note === '') {
        throw new DomainException('Alasan penolakan wajib diisi.');
    }
    if (strlen($note) > 1000) throw new DomainException('Catatan maksimal 1000 karakter.');

    $db = db();
    $ownsTransaction = !$db->inTransaction();
    if ($ownsTransaction) $db->beginTransaction();
    try {
        $row = q('SELECT id, status_rekonsiliasi FROM pembayaran_terlambat WHERE id = ? FOR UPDATE', [$id])->fetch();
        if (!$row) throw new DomainException('Catatan rekonsiliasi tidak ditemukan.');
        if ($row['status_rekonsiliasi'] !== 'menunggu_verifikasi') {
            throw new DomainException('Catatan ini sudah diverifikasi.');
        }
        $before = $row['status_rekonsiliasi'];
        q('UPDATE pembayaran_terlambat
            SET status_rekonsiliasi = ?, diverifikasi_oleh = ?, diverifikasi_at = clock_timestamp(), hasil_catatan = ?
            WHERE id = ?', [$status, $adminId, $note !== '' ? $note : null, $id]);
        late_payment_log($id, $status === 'terverifikasi' ? 'diverifikasi' : 'ditolak',
            $before, $status, $note !== '' ? $note : null, $adminId);
        if ($ownsTransaction) $db->commit();
        return late_payment_get($id) ?? ['id' => $id];
    } catch (Throwable $e) {
        if ($ownsTransaction && $db->inTransaction()) $db->rollBack();
        throw $e;
    }
}

function late_payment_follow_up(int $id, array $post, int $adminId): array
{
    if (!late_payment_table_available()) throw new DomainException('Fitur rekonsiliasi belum tersedia pada database ini.');
    $type = (string) ($post['tindak_lanjut'] ?? '');
    if (!in_array($type, ['pesanan_baru', 'refund'], true)) {
        throw new DomainException('Jenis tindak lanjut tidak valid.');
    }
    $note = trim((string) ($post['tindak_lanjut_catatan'] ?? ''));
    if (strlen($note) > 1000) throw new DomainException('Catatan maksimal 1000 karakter.');

    $db = db();
    $ownsTransaction = !$db->inTransaction();
    if ($ownsTransaction) $db->beginTransaction();
    try {
        $row = q('SELECT id, status_rekonsiliasi, tindak_lanjut, pesanan_id FROM pembayaran_terlambat WHERE id = ? FOR UPDATE', [$id])->fetch();
        if (!$row) throw new DomainException('Catatan rekonsiliasi tidak ditemukan.');
        if ($row['status_rekonsiliasi'] !== 'terverifikasi') {
            throw new DomainException('Tindak lanjut hanya untuk pembayaran terverifikasi.');
        }
        if ($row['tindak_lanjut'] !== null) {
            throw new DomainException('Tindak lanjut sudah dicatat.');
        }

        $orderId = null;
        $refundNominal = null;
        $refundDate = null;
        $refundRef = null;
        if ($type === 'pesanan_baru') {
            $orderId = filter_var($post['tindak_lanjut_order_id'] ?? null, FILTER_VALIDATE_INT);
            if (!$orderId || $orderId < 1 || $orderId === (int) $row['pesanan_id']) {
                throw new DomainException('Pesanan baru harus berupa pesanan berbeda yang dibuat lewat checkout normal.');
            }
            if (!q('SELECT 1 FROM pesanan WHERE id = ?', [$orderId])->fetchColumn()) {
                throw new DomainException('Pesanan baru tidak ditemukan.');
            }
        } else {
            $refundNominal = filter_var($post['refund_nominal'] ?? null, FILTER_VALIDATE_INT);
            if ($refundNominal === false || $refundNominal < 0) throw new DomainException('Nominal refund tidak valid.');
            $refundDate = late_payment_parse_datetime($post['refund_tanggal'] ?? '');
            $refundRef = trim((string) ($post['refund_referensi'] ?? ''));
            if (strlen($refundRef) > 100) throw new DomainException('Referensi refund maksimal 100 karakter.');
        }

        q('UPDATE pembayaran_terlambat
            SET tindak_lanjut = ?, tindak_lanjut_order_id = ?, tindak_lanjut_catatan = ?,
                refund_nominal = ?, refund_tanggal = ?, refund_referensi = ?,
                tindak_lanjut_oleh = ?, tindak_lanjut_at = clock_timestamp()
            WHERE id = ?',
            [$type, $orderId, $note !== '' ? $note : null,
             $refundNominal, $refundDate, $refundRef !== '' ? $refundRef : null, $adminId, $id]);
        late_payment_log($id, 'tindak_lanjut', $row['status_rekonsiliasi'],
            $row['status_rekonsiliasi'], $note !== '' ? $note : $type, $adminId);
        if ($ownsTransaction) $db->commit();
        return late_payment_get($id) ?? ['id' => $id];
    } catch (Throwable $e) {
        if ($ownsTransaction && $db->inTransaction()) $db->rollBack();
        throw $e;
    }
}
