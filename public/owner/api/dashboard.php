<?php
// GET ?start_date=YYYY-MM-DD&end_date=YYYY-MM-DD
// GET ?period=today|week|month&selected=YYYY-MM|YYYY-MM-DD  (legacy)
require __DIR__ . '/../../../app/bootstrap.php';
require_owner();

$useCustomRange = false;
$startParam = $_GET['start_date'] ?? null;
$endParam   = $_GET['end_date']   ?? null;
$selected   = null;

if (is_string($startParam) && is_string($endParam)
    && preg_match('/^\d{4}-\d{2}-\d{2}$/', $startParam)
    && preg_match('/^\d{4}-\d{2}-\d{2}$/', $endParam)) {
    $startDate = DateTime::createFromFormat('!Y-m-d', $startParam);
    $endDate   = DateTime::createFromFormat('!Y-m-d', $endParam);
    if ($startDate && $endDate) {
        if ($startDate > $endDate) {
            [$startDate, $endDate] = [$endDate, $startDate];
        }
        $useCustomRange = true;
        $period = 'custom';
        $dayCount = (int) $startDate->diff($endDate)->days + 1;
        $previousEndDate   = (clone $startDate)->modify('-1 day');
        $previousStartDate = (clone $previousEndDate)->modify('-' . ($dayCount - 1) . ' days');
    }
}

if (!$useCustomRange) {
    $period  = $_GET['period'] ?? 'today';
    $endDate = new DateTime('today');

    if ($period === 'week') {
        $startDate         = (clone $endDate)->modify('-6 day');
        $previousStartDate = (clone $endDate)->modify('-13 day');
        $previousEndDate   = (clone $endDate)->modify('-7 day');
    } elseif ($period === 'month') {
        $startDate         = new DateTime('first day of this month');
        $previousStartDate = new DateTime('first day of last month');
        $previousEndDate   = (clone $startDate)->modify('-1 day');
    } else {
        $startDate         = clone $endDate;
        $previousStartDate = (clone $endDate)->modify('-1 day');
        $previousEndDate   = clone $previousStartDate;
        $period = 'today';
    }

    // Legacy: selected
    if ($period === 'week' && isset($_GET['selected']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['selected'])) {
        $candidate = DateTime::createFromFormat('!Y-m-d', $_GET['selected']);
        if ($candidate && $candidate->format('Y-m-d') === $_GET['selected']
            && $candidate >= $startDate && $candidate <= $endDate) {
            $selected = $candidate;
            $endDate = clone $candidate;
            $startDate = clone $candidate;
            $previousStartDate = (clone $candidate)->modify('-1 day');
            $previousEndDate   = clone $previousStartDate;
        }
    } elseif ($period === 'month' && isset($_GET['selected']) && preg_match('/^\d{4}-\d{2}$/', $_GET['selected'])) {
        [$year, $month] = array_map('intval', explode('-', $_GET['selected']));
        if ($month >= 1 && $month <= 12) {
            $selected = new DateTime(sprintf('%04d-%02d-01', $year, $month));
            $startDate = clone $selected;
            $endDate   = (clone $selected)->modify('last day of this month');
            $previousStartDate = (clone $selected)->modify('-1 month');
            $previousEndDate   = (clone $previousStartDate)->modify('last day of this month');
        }
    }
}

// SELECT menghitung omzet dan jumlah pesanan pada rentang tanggal.
$summarize = fn($fromDate, $toDate) => q(
    "SELECT COALESCE(SUM(total), 0) om, COUNT(*) n
     FROM pesanan
     WHERE status = 'selesai' AND created_at::date BETWEEN ? AND ?",
    [$fromDate->format('Y-m-d'), $toDate->format('Y-m-d')]
)->fetch();

$currentSummary  = $summarize($startDate, $endDate);
$previousSummary = $summarize($previousStartDate, $previousEndDate);
$dateRange = [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')];

// JOIN header dan detail pesanan, lalu SUM menghitung jumlah porsi pada periode.
$items = (int) q(
    "SELECT COALESCE(SUM(d.qty), 0)
     FROM detail_pesanan d
     JOIN pesanan s ON s.id = d.pesanan_id
     WHERE s.status = 'selesai' AND s.created_at::date BETWEEN ? AND ?",
    $dateRange
)->fetchColumn();

// Chart:
// - custom  : per hari, label DD/MM
// - month   : per bulan dalam tahun berjalan
// - week    : 7 hari terakhir
if ($period === 'custom') {
    $chart = q(
        "SELECT to_char(d, 'DD/MM') l, to_char(d, 'YYYY-MM-DD') k, COALESCE(SUM(s.total), 0) v
         FROM generate_series(?::date, ?::date, '1 day') d
         LEFT JOIN pesanan s
           ON s.created_at::date = d AND s.status = 'selesai'
         GROUP BY d
         ORDER BY d",
        $dateRange
    )->fetchAll();
} elseif ($period === 'month') {
    $chart = q(
        "SELECT to_char(m, 'Mon') l, to_char(m, 'YYYY-MM') k, COALESCE(SUM(s.total), 0) v
         FROM generate_series(
                date_trunc('year', CURRENT_DATE),
                date_trunc('year', CURRENT_DATE) + INTERVAL '11 month',
                '1 month') m
         LEFT JOIN pesanan s
           ON date_trunc('month', s.created_at) = m AND s.status = 'selesai'
         GROUP BY m
         ORDER BY m"
    )->fetchAll();
} else {
    $chart = q(
        "SELECT to_char(d, 'Dy') l, to_char(d, 'YYYY-MM-DD') k, COALESCE(SUM(s.total), 0) v
         FROM generate_series(CURRENT_DATE - 6, CURRENT_DATE, '1 day') d
         LEFT JOIN pesanan s
           ON s.created_at::date = d AND s.status = 'selesai'
         GROUP BY d
         ORDER BY d"
    )->fetchAll();
}

// JOIN detail, pesanan, dan produk; GROUP BY kategori.
$categories = q(
    "SELECT pr.kategori k, MAX(pr.harga) h, SUM(d.qty) n
     FROM detail_pesanan d
     JOIN pesanan s ON s.id = d.pesanan_id
     JOIN produk pr ON pr.id = d.produk_id
     WHERE s.status = 'selesai' AND s.created_at::date BETWEEN ? AND ?
     GROUP BY pr.kategori",
    $dateRange
)->fetchAll();

// JOIN detail, pesanan, dan produk; top 5 produk terlaris.
$top = q(
    "SELECT pr.nama, pr.gambar, pr.harga, SUM(d.qty) n, SUM(d.qty * d.harga) om
     FROM detail_pesanan d
     JOIN pesanan s ON s.id = d.pesanan_id
     JOIN produk pr ON pr.id = d.produk_id
     WHERE s.status = 'selesai' AND s.created_at::date BETWEEN ? AND ?
     GROUP BY pr.id
     ORDER BY n DESC
     LIMIT 5",
    $dateRange
)->fetchAll();

$growth = ($previousSummary['om'] > 0)
    ? round(($currentSummary['om'] - $previousSummary['om']) / $previousSummary['om'] * 100, 1)
    : 0;

json([
    'nama'     => ucfirst(me()['username']),
    'period'   => $period,
    'start'    => $startDate->format('Y-m-d'),
    'end'      => $endDate->format('Y-m-d'),
    'selected' => $selected ? ($period === 'month' ? $selected->format('Y-m') : $selected->format('Y-m-d')) : null,
    'omzet'    => (int) $currentSummary['om'],
    'growth'   => $growth,
    'tiket'    => $currentSummary['n'] ? round($currentSummary['om'] / $currentSummary['n']) : 0,
    'orders'   => (int) $currentSummary['n'],
    'items'    => $items,
    'chart'    => $chart,
    'kategori' => $categories,
    'top'      => $top,
]);