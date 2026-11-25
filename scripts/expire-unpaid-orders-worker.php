<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit("Not found\n");
}

require __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/order-expiration.php';

$interval = max(30, (int) (getenv('EXPIRATION_INTERVAL_SECONDS') ?: 60));
fwrite(STDOUT, "Payment expiration worker started; interval={$interval}s\n");

while (true) {
    $startedAt = microtime(true);

    try {
        $cancelled = expire_unpaid_orders();
        fwrite(STDOUT, sprintf(
            "[%s] Payment expiration completed; cancelled=%d\n",
            (new DateTimeImmutable('now', new DateTimeZone('Asia/Jakarta')))->format(DATE_ATOM),
            $cancelled
        ));
    } catch (Throwable $error) {
        fwrite(STDERR, sprintf(
            "[%s] Payment expiration failed (%s)\n",
            (new DateTimeImmutable('now', new DateTimeZone('Asia/Jakarta')))->format(DATE_ATOM),
            get_class($error)
        ));
    }

    $remaining = $interval - (microtime(true) - $startedAt);
    if ($remaining > 0) {
        usleep((int) ($remaining * 1_000_000));
    }
}
