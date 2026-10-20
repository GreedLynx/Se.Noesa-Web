<?php
require __DIR__.'/../../../app/bootstrap.php'; require_role('admin');
$today=(new DateTimeImmutable('today'))->format('Y-m-d');
$stats=q("SELECT COUNT(*) FILTER (WHERE created_at::date=?) orders, COUNT(*) FILTER (WHERE status='menunggu_validasi') pending FROM pesanan",[$today])->fetch();
$cap=q("SELECT COALESCE(SUM(j.kapasitas),0) kapasitas, COALESCE(SUM(x.pakai),0) pakai FROM jadwal_po j LEFT JOIN LATERAL (SELECT COALESCE(SUM(p.pax),0) pakai FROM pesanan p WHERE p.jadwal_id=j.id AND p.status<>'ditolak') x ON true WHERE j.aktif")->fetch();
$critical=(int)q("SELECT COUNT(*) FROM produk WHERE kuota=0 OR kuota-terpakai<=kuota*0.2")->fetchColumn();
$chart=q("SELECT d::date hari, to_char(d,'Dy') label, COUNT(p.id) FILTER(WHERE p.status='selesai') jumlah FROM generate_series(current_date-6,current_date,'1 day') d LEFT JOIN pesanan p ON p.created_at::date=d GROUP BY d ORDER BY d")->fetchAll();
$priority=q("SELECT p.id,p.kode,p.nama_pemesan,p.keterangan,p.pax,p.total,p.status,p.tgl_kirim,p.created_at FROM pesanan p WHERE p.status='menunggu_validasi' ORDER BY p.created_at ASC LIMIT 5")->fetchAll();
$recent=q("SELECT p.id,p.kode,p.nama_pemesan,p.total,p.status,p.created_at FROM pesanan p ORDER BY p.created_at DESC LIMIT 6")->fetchAll();
json(['orders'=>(int)$stats['orders'],'pending'=>(int)$stats['pending'],'capacity'=>(int)$cap['kapasitas'],'used'=>(int)$cap['pakai'],'critical'=>$critical,'chart'=>$chart,'priority'=>$priority,'recent'=>$recent]);
