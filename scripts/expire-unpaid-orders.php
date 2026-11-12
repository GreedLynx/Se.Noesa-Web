<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
ini_set('zend.exception_ignore_args', '1');
$jobFinished = false;
// Konfigurasi lama memakai die(string); scheduler tetap harus melihat kegagalan.
register_shutdown_function(static function () use (&$jobFinished): void {
    if (!$jobFinished) {
        fwrite(STDERR, "Job tidak selesai; periksa konfigurasi dan log PHP.\n");
        exit(1);
    }
});
require __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/order-expiration.php';

$mode = $argv[1] ?? '';
if (count($argv) !== 2 || !in_array($mode, ['--inventory', '--dry-run', '--apply'], true)) {
    fwrite(STDERR, "Gunakan --inventory, --dry-run, atau --apply. Jalankan inventaris dan dry-run sebelum aktivasi.\n");
    exit(1);
}

try {
    $inventory = q("SELECT
        COUNT(*) FILTER (WHERE status = 'menunggu_pembayaran') AS unpaid,
        COUNT(*) FILTER (WHERE status = 'menunggu_pembayaran' AND NULLIF(BTRIM(bukti_bayar), '') IS NULL
            AND created_at + interval '1 hour' <= statement_timestamp()) AS eligible,
        COUNT(*) FILTER (WHERE status = 'menunggu_pembayaran' AND (created_at IS NULL
            OR NULLIF(BTRIM(bukti_bayar), '') IS NOT NULL)) AS inconsistent
        FROM pesanan")->fetch();
    echo 'Inventaris: ' . json_encode($inventory) . PHP_EOL;
    if ($mode === '--inventory' || $mode === '--dry-run') {
        echo $mode === '--dry-run' ? "Dry-run read-only; tidak ada pesanan diubah.\n" : "Tidak ada pesanan diubah.\n";
    } else {
        // UPDATE bersyarat mengecek ulang status setelah menunggu mutasi pembayaran/pembatalan lain.
        $affected = expire_unpaid_orders();
        echo 'Dibatalkan: ' . $affected . PHP_EOL;
    }
    if ((int) $inventory['inconsistent'] > 0) {
        fwrite(STDERR, "Pesanan unpaid dengan waktu NULL atau bukti tersimpan dilewati; periksa secara manual.\n");
        $jobFinished = true;
        exit(1);
    }
    $jobFinished = true;
} catch (Throwable $e) {
    fwrite(STDERR, "Job kedaluwarsa gagal; periksa koneksi dan migrasi database.\n");
    $jobFinished = true;
    exit(1);
}
