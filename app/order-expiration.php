<?php
function expire_unpaid_orders(): int
{
    $expired = q(
        "WITH expired AS (
        UPDATE pesanan
        SET status = 'dibatalkan', status_updated_at = clock_timestamp(),
            sumber_pembatalan = 'otomatis', status_sebelum_batal = status
        WHERE status = 'menunggu_pembayaran'
          AND NULLIF(BTRIM(bukti_bayar), '') IS NULL
          AND created_at + interval '1 hour' <= statement_timestamp()
        RETURNING id
        ), closed AS (
        UPDATE penawaran_jadwal_ulang r
        SET status_tawaran = 'kedaluwarsa', ditanggapi_at = statement_timestamp()
        FROM expired WHERE r.pesanan_id = expired.id AND r.status_tawaran = 'menunggu'
        )
        SELECT COUNT(*) FROM expired"
    )->fetchColumn();
    return (int) $expired;
}
