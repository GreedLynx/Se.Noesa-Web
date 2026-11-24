<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../app/daily-quota.php';

$base = ['tanggal_libur' => false, 'dailyCapacity' => 50, 'dailyRemaining' => 50];
if (daily_kitchen_capacity([['kuota' => 50], ['kuota' => 20], ['kuota' => 50]]) !== 96
    || daily_kitchen_capacity([['kuota' => 0], ['kuota' => 25]]) !== 20
    || daily_kitchen_capacity([]) !== 0) {
    throw new RuntimeException('Kapasitas dapur harus 80% dari jumlah kuota efektif');
}
$cases = [
    [21, 1, 'available'],
    [20, 1, 'nearly_full'],
    [1, 1, 'nearly_full'],
    [0, 1, 'full'],
    [20, 0, 'menu_empty'],
];
foreach ($cases as [$remaining, $menus, $expected]) {
    $actual = daily_calendar_status([...$base, 'dailyRemaining' => $remaining], $menus);
    if ($actual !== $expected) throw new RuntimeException("Sisa $remaining, menu $menus: $actual, seharusnya $expected");
}
if (daily_capacity_status(21, 50) !== 'available' || daily_capacity_status(20, 50) !== 'nearly_full') {
    throw new RuntimeException('Ambang 40% pada status kapasitas salah');
}
if (daily_calendar_status([...$base, 'tanggal_libur' => true], 1) !== 'closed') {
    throw new RuntimeException('Tanggal libur harus berstatus closed');
}
$orderSnapshot = ['dailyCapacity' => 80, 'dailyUsed' => 75, 'items' => [
    ['id' => 1, 'nama' => 'Menu A', 'kuota_harian' => 50, 'sisa' => 50, 'min_pesan' => 10],
]];
try {
    daily_quota_validate($orderSnapshot, [1 => 10]);
    throw new RuntimeException('Checkout melewati kapasitas dapur');
} catch (DomainException $e) {
    if (!str_contains($e->getMessage(), 'Kuota harian')) throw $e;
}
if (count(daily_quota_validate([...$orderSnapshot, 'dailyUsed' => 70], [1 => 10])) !== 1) {
    throw new RuntimeException('Checkout tepat pada batas dapur harus diterima');
}
echo 'PASS: kalender membedakan >40%, tepat 40%, penuh, Menu Habis, dan libur.' . PHP_EOL;
