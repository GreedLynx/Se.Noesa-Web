<?php
require __DIR__ . '/../../../app/bootstrap.php';
require_role('pelanggan');
require_once __DIR__ . '/../../../app/daily-quota.php';

header('Cache-Control: no-store');

$zone = new DateTimeZone('Asia/Jakarta');
$first = (new DateTimeImmutable('now', $zone))->modify('+7 days')->format('Y-m-d');
$start = $_GET['start'] ?? $first;
$end = $_GET['end'] ?? (new DateTimeImmutable($first, $zone))->modify('+30 days')->format('Y-m-d');

try {
    [$start, $end] = daily_quota_range($start, $end);
} catch (DomainException $e) {
    json(['error' => $e->getMessage()], 422);
}

// Batasi rentang agar endpoint ringan untuk kalender bulanan.
$span = (new DateTimeImmutable($start, $zone))->diff(new DateTimeImmutable($end, $zone))->days;
if ($span > 62) {
    json(['error' => 'Rentang tanggal maksimal 62 hari'], 422);
}

$dates = [];
for ($day = new DateTimeImmutable($start, $zone), $last = new DateTimeImmutable($end, $zone);
     $day <= $last;
     $day = $day->modify('+1 day')) {
    $value = $day->format('Y-m-d');
    try {
        $snapshot = daily_quota_snapshot($value);
    } catch (DomainException $e) {
        continue;
    }
    $holiday = !empty($snapshot['tanggal_libur']);
    $availableItems = 0;
    foreach ($snapshot['items'] as $item) {
        if (daily_product_available($item, $holiday)) $availableItems++;
    }
    $remaining = (int) $snapshot['dailyRemaining'];
    $status = $holiday ? 'closed'
        : ($remaining <= 0 || $availableItems === 0 ? 'full'
        : ($remaining <= intdiv(SENOESA_DAILY_PACKAGE_LIMIT, 5) ? 'nearly_full' : 'available'));

    $dates[] = [
        'tanggal' => $value,
        'status' => $status,
        'sisa' => $remaining,
        'kapasitas' => (int) $snapshot['dailyCapacity'],
        'menu_tersedia' => $availableItems,
        'tanggal_libur' => (bool) $snapshot['tanggal_libur'],
    ];
}

json(['start' => $start, 'end' => $end, 'dates' => $dates]);
