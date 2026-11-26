<?php
require_once __DIR__ . '/menu-notifications.php';

function expire_unpaid_orders(): int
{
    $db = db();
    $ownsTransaction = !$db->inTransaction();
    if ($ownsTransaction) {
        $db->beginTransaction();
    }

    try {
        // Kunci kandidat lebih dulu agar pembatalan dan penutupan notifikasi
        // berjalan dalam satu transaksi tanpa menimpa respons yang sudah selesai.
        $candidates = q(
            "SELECT id
            FROM pesanan
            WHERE status = 'menunggu_pembayaran'
              AND NULLIF(BTRIM(bukti_bayar), '') IS NULL
              AND created_at + interval '1 hour' <= statement_timestamp()
            ORDER BY id
            FOR UPDATE"
        )->fetchAll(PDO::FETCH_COLUMN);

        $cancelled = 0;
        foreach ($candidates as $candidateId) {
            // UPDATE bersyarat mengecek ulang status setelah menunggu mutasi pembayaran/pembatalan lain.
            $affected = q(
                "UPDATE pesanan
                SET status = 'dibatalkan', status_updated_at = clock_timestamp(),
                    sumber_pembatalan = 'otomatis', status_sebelum_batal = status
                WHERE id = ?
                  AND status = 'menunggu_pembayaran'
                  AND NULLIF(BTRIM(bukti_bayar), '') IS NULL
                  AND created_at + interval '1 hour' <= statement_timestamp()",
                [(int) $candidateId]
            )->rowCount();
            if ($affected !== 1) {
                continue;
            }

            menu_notice_close_for_order(
                (int) $candidateId,
                'kedaluwarsa_pesanan',
                'Pesanan dibatalkan otomatis karena kedaluwarsa pembayaran.'
            );
            $cancelled++;
        }

        if ($ownsTransaction) {
            $db->commit();
        }

        return $cancelled;
    } catch (Throwable $error) {
        if ($ownsTransaction && $db->inTransaction()) {
            $db->rollBack();
        }
        throw $error;
    }
}

/**
 * Sinkronkan status pesanan pelanggan yang sedang dilihat tanpa menunggu worker.
 * Scope user_id mencegah endpoint pelanggan memutasi pesanan milik akun lain.
 */
function expire_unpaid_orders_for_user(int $userId): int
{
    if ($userId < 1) return 0;

    $ids = q(
        "SELECT id
        FROM pesanan
        WHERE user_id = ?
          AND status = 'menunggu_pembayaran'
          AND NULLIF(BTRIM(bukti_bayar), '') IS NULL
          AND created_at + interval '1 hour' <= statement_timestamp()
        ORDER BY id
        LIMIT 50",
        [$userId]
    )->fetchAll(PDO::FETCH_COLUMN);

    $cancelled = 0;
    foreach ($ids as $id) {
        $db = db();
        $ownsTransaction = !$db->inTransaction();
        if ($ownsTransaction) $db->beginTransaction();
        try {
            $order = q(
                "SELECT id
                FROM pesanan
                WHERE id = ? AND user_id = ?
                  AND status = 'menunggu_pembayaran'
                  AND NULLIF(BTRIM(bukti_bayar), '') IS NULL
                  AND created_at + interval '1 hour' <= statement_timestamp()
                FOR UPDATE",
                [(int) $id, $userId]
            )->fetchColumn();
            if (!$order) {
                if ($ownsTransaction) $db->commit();
                continue;
            }

            $updated = q(
                "UPDATE pesanan
                SET status = 'dibatalkan', status_updated_at = clock_timestamp(),
                    sumber_pembatalan = 'otomatis', status_sebelum_batal = status
                WHERE id = ? AND user_id = ?
                  AND status = 'menunggu_pembayaran'
                  AND NULLIF(BTRIM(bukti_bayar), '') IS NULL
                  AND created_at + interval '1 hour' <= statement_timestamp()",
                [(int) $id, $userId]
            )->rowCount();
            if ($updated === 1) {
                menu_notice_close_for_order(
                    (int) $id,
                    'kedaluwarsa_pesanan',
                    'Pesanan dibatalkan otomatis karena kedaluwarsa pembayaran.'
                );
                $cancelled++;
            }
            if ($ownsTransaction) $db->commit();
        } catch (Throwable $error) {
            if ($ownsTransaction && $db->inTransaction()) $db->rollBack();
            throw $error;
        }
    }
    return $cancelled;
}
