<?php
// Regression test: resolusi path bukti lama (public/uploads) dan baru (storage).
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../app/proof-storage.php';

function check(bool $ok, string $message): void
{
    if (!$ok) throw new RuntimeException('FAIL: ' . $message);
    echo 'PASS: ' . $message . PHP_EOL;
}

$legacy = 'uploads/payments/payment-73-6de30c61b01cc20ea4c96589.jpg';
check(proof_resolve($legacy) !== null, 'bukti lama di public/uploads/payments dapat di-resolve');
check(str_contains((string) proof_resolve($legacy), '/public/uploads/payments/'), 'path legacy diarahkan ke document root public');
check(proof_resolve('storage/payments/.gitkeep') !== null, 'bukti baru di storage/payments dapat di-resolve');
$resolved = proof_resolve($legacy);
check($resolved !== null && proof_mime($resolved) === 'image/jpeg', 'deteksi MIME fallback untuk JPEG (tanpa fileinfo)');
check(proof_resolve('../config/config.php') === null, 'path traversal ditolak');
check(proof_resolve('uploads/payments/tidak-ada.jpg') === null, 'file legacy yang tidak ada ditolak');
echo 'Seluruh pengujian path bukti lulus.' . PHP_EOL;
