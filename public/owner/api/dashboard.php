<?php
// GET ?period=today|week|month
require __DIR__ . '/../../../app/bootstrap.php';
require_owner();

$period = $_GET['period'] ?? 'today';
$endDate = new DateTime('today');
if ($period === 'week') {
    $startDate = (clone $endDate)->modify('-6 day');
    $previousStartDate = (clone $endDate)->modify('-13 day');
    $previousEndDate = (clone $endDate)->modify('-7 day');
} elseif ($period === 'month') {
    $startDate = new DateTime('first day of this month');
    $previousStartDate = new DateTime('first day of last month');
    $previousEndDate = (clone $startDate)->modify('-1 day');
} else {
    $startDate = clone $endDate;
    $previousStartDate = (clone $endDate)->modify('-1 day');
    $previousEndDate = clone $previousStartDate;
    $period = 'today';
}

$summarize = fn($fromDate, $toDate) => q("SELECT COALESCE(SUM(total),0) om, COUNT(*) n FROM pesanan WHERE status='selesai' AND created_at::date BETWEEN ? AND ?", [$fromDate->format('Y-m-d'), $toDate->format('Y-m-d')])->fetch();

$selected = null;
if ($period === 'week' && isset($_GET['selected']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['selected'])) {
    $candidate = DateTime::createFromFormat('!Y-m-d', $_GET['selected']);
    if ($candidate && $candidate->format('Y-m-d') === $_GET['selected'] && $candidate >= $startDate && $candidate <= $endDate) {
        $selected = $candidate;
        $endDate = clone $candidate;
        $startDate = clone $candidate;
        $previousStartDate = (clone $candidate)->modify('-1 day');
        $previousEndDate = clone $previousStartDate;
    }
} elseif ($period === 'month' && isset($_GET['selected']) && preg_match('/^\d{4}-\d{2}$/', $_GET['selected'])) {
    [$year, $month] = array_map('intval', explode('-', $_GET['selected']));
    if ($year === (int) $endDate->format('Y') && $month >= 1 && $month <= 12) {
        $selected = new DateTime(sprintf('%04d-%02d-01', $year, $month));
        $startDate = clone $selected;
        $endDate = (clone $selected)->modify('last day of this month');
        $previousStartDate = (clone $selected)->modify('-1 month');
        $previousEndDate = (clone $previousStartDate)->modify('last day of this month');
    }
}

$currentSummary = $summarize($startDate, $endDate);
$previousSummary = $summarize($previousStartDate, $previousEndDate);
$dateRange = [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')];
$items = (int) q("SELECT COALESCE(SUM(d.qty),0) FROM detail_pesanan d JOIN pesanan s ON s.id=d.pesanan_id WHERE s.status='selesai' AND s.created_at::date BETWEEN ? AND ?", $dateRange)->fetchColumn();
$chart = $period === 'month'
    ? q("SELECT to_char(m,'Mon') l, to_char(m,'YYYY-MM') k, COALESCE(SUM(s.total),0) v FROM generate_series(date_trunc('year',current_date),date_trunc('year',current_date)+interval '11 month','1 month') m LEFT JOIN pesanan s ON date_trunc('month',s.created_at)=m AND s.status='selesai' GROUP BY m ORDER BY m")->fetchAll()
    : q("SELECT to_char(d,'Dy') l, to_char(d,'YYYY-MM-DD') k, COALESCE(SUM(s.total),0) v, d=current_date AS now FROM generate_series(current_date-6,current_date,'1 day') d LEFT JOIN pesanan s ON s.created_at::date=d AND s.status='selesai' GROUP BY d ORDER BY d")->fetchAll();
$categories = q("SELECT pr.kategori k, MAX(pr.harga) h, SUM(d.qty) n FROM detail_pesanan d JOIN pesanan s ON s.id=d.pesanan_id JOIN produk pr ON pr.id=d.produk_id WHERE s.status='selesai' AND s.created_at::date BETWEEN ? AND ? GROUP BY pr.kategori", $dateRange)->fetchAll();
$top = q("SELECT pr.nama, pr.gambar, pr.harga, SUM(d.qty) n, SUM(d.qty*d.harga) om FROM detail_pesanan d JOIN pesanan s ON s.id=d.pesanan_id JOIN produk pr ON pr.id=d.produk_id WHERE s.status='selesai' AND s.created_at::date BETWEEN ? AND ? GROUP BY pr.id ORDER BY n DESC LIMIT 5", $dateRange)->fetchAll();

json([
    'nama' => ucfirst(me()['username']),
    'period' => $period,
    'selected' => $selected ? ($period === 'month' ? $selected->format('Y-m') : $selected->format('Y-m-d')) : null,
    'omzet' => (int) $currentSummary['om'],
    'growth' => $previousSummary['om'] > 0 ? round(($currentSummary['om'] - $previousSummary['om']) / $previousSummary['om'] * 100, 1) : 0,
    'tiket' => $currentSummary['n'] ? round($currentSummary['om'] / $currentSummary['n']) : 0,
    'orders' => (int) $currentSummary['n'],
    'items' => $items,
    'chart' => $chart,
    'kategori' => $categories,
    'top' => $top,
]);
