<?php
function expire_unpaid_orders(): int
{
    return count(q(
        "UPDATE pesanan
        SET status = 'dibatalkan', status_updated_at = now()
        WHERE status = 'menunggu_pembayaran'
          AND created_at <= now() - INTERVAL '1 hour'
        RETURNING id"
    )->fetchAll(PDO::FETCH_COLUMN));
}
