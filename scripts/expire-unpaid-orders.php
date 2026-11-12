<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit("Not found\n");
}

require __DIR__ . '/../app/bootstrap.php';
require __DIR__ . '/../app/order-expiration.php';

try {
    $count = expire_unpaid_orders();
    fwrite(STDOUT, sprintf(
        "[%s] Canceled %d unpaid order(s).\n",
        (new DateTimeImmutable('now', new DateTimeZone('Asia/Jakarta')))->format('Y-m-d H:i:s T'),
        $count
    ));
} catch (Throwable $error) {
    error_log('[Se.Noesa unpaid order expiration] ' . $error);
    fwrite(STDERR, "Failed to expire unpaid orders. Check the application error log.\n");
    exit(1);
}
