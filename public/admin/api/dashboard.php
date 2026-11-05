<?php
require __DIR__ . '/../../../app/bootstrap.php';
require_role('admin');
require_once __DIR__ . '/../../../app/daily-quota.php';
$today = (new DateTimeImmutable('today'))->format('Y-m-d');
$stats = q("SELECT COUNT(*) FILTER (WHERE created_at::date=?) orders, COUNT(*) FILTER (WHERE status='menunggu_validasi') pending FROM pesanan", [$today])->fetch();
$day=$_GET['tgl_kirim']??(new DateTimeImmutable('now',new DateTimeZone('Asia/Jakarta')))->format('Y-m-d');
try{$quota=daily_quota_snapshot($day,true);}catch(DomainException $e){json(['error'=>$e->getMessage()],422);}
$cap = ['kapasitas'=>$quota['maxKitchen'],'pakai'=>$quota['kitchenUsed']];
$critical = count(array_filter($quota['items'],static fn($p)=>(int)$p['kuota']===0 || (int)$p['sisa']<=(int)$p['kuota']*0.2));
$chart = q("SELECT d::date hari, to_char(d,'Dy') label, COUNT(p.id) FILTER(WHERE p.status='selesai') jumlah FROM generate_series(current_date-6,current_date,'1 day') d LEFT JOIN pesanan p ON p.created_at::date=d GROUP BY d ORDER BY d")->fetchAll();
$priority = q("SELECT p.id,p.kode,p.nama_pemesan,p.keterangan,p.pax,p.total,p.status,p.tgl_kirim,p.created_at FROM pesanan p WHERE p.status='menunggu_validasi' ORDER BY p.created_at ASC LIMIT 5")->fetchAll();
$recent = q("SELECT p.id,p.kode,p.nama_pemesan,p.total,p.status,p.created_at FROM pesanan p ORDER BY p.created_at DESC LIMIT 6")->fetchAll();
json(['orders' => (int)$stats['orders'], 'pending' => (int)$stats['pending'], 'capacity' => (int)$cap['kapasitas'], 'used' => (int)$cap['pakai'], 'critical' => $critical, 'chart' => $chart, 'priority' => $priority, 'recent' => $recent]);
