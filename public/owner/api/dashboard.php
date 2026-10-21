<?php // GET ?period=today|week|month
require __DIR__ . '/../../../app/bootstrap.php';
require_owner();
$p = $_GET['period'] ?? 'today';
$t = new DateTime('today');
if ($p === 'week') {
    $f = (clone $t)->modify('-6 day');
    $pf = (clone $t)->modify('-13 day');
    $pt = (clone $t)->modify('-7 day');
} elseif ($p === 'month') {
    $f = new DateTime('first day of this month');
    $pf = new DateTime('first day of last month');
    $pt = (clone $f)->modify('-1 day');
} else {
    $f = clone $t;
    $pf = (clone $t)->modify('-1 day');
    $pt = clone $pf;
    $p = 'today';
}
$sum = fn($a, $b) => q("SELECT COALESCE(SUM(total),0) om, COUNT(*) n FROM pesanan WHERE status='selesai' AND created_at::date BETWEEN ? AND ?", [$a->format('Y-m-d'), $b->format('Y-m-d')])->fetch();
$selected = null;
if ($p === 'week' && isset($_GET['selected']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['selected'])) {
    $candidate = DateTime::createFromFormat('!Y-m-d', $_GET['selected']);
    if ($candidate && $candidate->format('Y-m-d') === $_GET['selected'] && $candidate >= $f && $candidate <= $t) {
        $selected = $candidate;
        $t = clone $candidate;
        $f = clone $candidate;
        $pf = (clone $candidate)->modify('-1 day');
        $pt = clone $pf;
    }
} elseif ($p === 'month' && isset($_GET['selected']) && preg_match('/^\d{4}-\d{2}$/', $_GET['selected'])) {
    [$year, $month] = array_map('intval', explode('-', $_GET['selected']));
    if ($year === (int)$t->format('Y') && $month >= 1 && $month <= 12) {
        $selected = new DateTime(sprintf('%04d-%02d-01', $year, $month));
        $f = clone $selected;
        $t = (clone $selected)->modify('last day of this month');
        $pf = (clone $selected)->modify('-1 month');
        $pt = (clone $pf)->modify('last day of this month');
    }
}
$c = $sum($f, $t);
$o = $sum($pf, $pt);
$rg = [$f->format('Y-m-d'), $t->format('Y-m-d')];
$items = (int)q("SELECT COALESCE(SUM(d.qty),0) FROM detail_pesanan d JOIN pesanan s ON s.id=d.pesanan_id WHERE s.status='selesai' AND s.created_at::date BETWEEN ? AND ?", $rg)->fetchColumn();
$chart = $p === 'month'
    ? q("SELECT to_char(m,'Mon') l, to_char(m,'YYYY-MM') k, COALESCE(SUM(s.total),0) v FROM generate_series(date_trunc('year',current_date),date_trunc('year',current_date)+interval '11 month','1 month') m LEFT JOIN pesanan s ON date_trunc('month',s.created_at)=m AND s.status='selesai' GROUP BY m ORDER BY m")->fetchAll()
    : q("SELECT to_char(d,'Dy') l, to_char(d,'YYYY-MM-DD') k, COALESCE(SUM(s.total),0) v, d=current_date AS now FROM generate_series(current_date-6,current_date,'1 day') d LEFT JOIN pesanan s ON s.created_at::date=d AND s.status='selesai' GROUP BY d ORDER BY d")->fetchAll();
$cat = q("SELECT pr.kategori k, MAX(pr.harga) h, SUM(d.qty) n FROM detail_pesanan d JOIN pesanan s ON s.id=d.pesanan_id JOIN produk pr ON pr.id=d.produk_id WHERE s.status='selesai' AND s.created_at::date BETWEEN ? AND ? GROUP BY pr.kategori", $rg)->fetchAll();
$top = q("SELECT pr.nama, pr.gambar, pr.harga, SUM(d.qty) n, SUM(d.qty*d.harga) om FROM detail_pesanan d JOIN pesanan s ON s.id=d.pesanan_id JOIN produk pr ON pr.id=d.produk_id WHERE s.status='selesai' AND s.created_at::date BETWEEN ? AND ? GROUP BY pr.id ORDER BY n DESC LIMIT 5", $rg)->fetchAll();
json([
    'nama' => ucfirst(me()['username']),
    'period' => $p,
    'selected' => $selected ? ($p === 'month' ? $selected->format('Y-m') : $selected->format('Y-m-d')) : null,
    'omzet' => (int)$c['om'],
    'growth' => $o['om'] > 0 ? round(($c['om'] - $o['om']) / $o['om'] * 100, 1) : 0,
    'tiket' => $c['n'] ? round($c['om'] / $c['n']) : 0,
    'orders' => (int)$c['n'],
    'items' => $items,
    'chart' => $chart,
    'kategori' => $cat,
    'top' => $top
]);
