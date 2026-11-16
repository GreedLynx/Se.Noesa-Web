<?php
// Seragamkan metadata dari VIEW untuk daftar, detail, dan Dashboard Admin.
function cancellation_metadata(array $order): array
{
    foreach (['cancelled_at', 'reviewed_at'] as $field) {
        $order[$field] = empty($order[$field]) ? null
            : (new DateTimeImmutable($order[$field]))->setTimezone(new DateTimeZone('Asia/Jakarta'))->format(DateTimeInterface::ATOM);
    }
    foreach (['needs_payment_review', 'production_stop_required'] as $field) {
        $order[$field] = in_array($order[$field] ?? null, [true, 1, '1', 't'], true);
    }
    $order['reviewed_by'] = isset($order['reviewed_by']) ? (int) $order['reviewed_by'] : null;
    return $order;
}
