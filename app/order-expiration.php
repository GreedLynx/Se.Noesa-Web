<?php
function expire_unpaid_orders(): int
{
    return q(
        "UPDATE pesanan
        SET status = 'dibatalkan', status_updated_at = clock_timestamp(),
            sumber_pembatalan = 'otomatis', status_sebelum_batal = status
        WHERE status = 'menunggu_pembayaran'
          AND NULLIF(BTRIM(bukti_bayar), '') IS NULL
          AND created_at + interval '1 hour' <= statement_timestamp()"
    )->rowCount();
}
