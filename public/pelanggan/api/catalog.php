<?php
require __DIR__.'/../../../app/bootstrap.php'; require_role('pelanggan');
require_once __DIR__.'/../../../app/daily-quota.php';
header('Cache-Control: no-store');
$day=$_GET['tgl_kirim']??(new DateTimeImmutable('now',new DateTimeZone('Asia/Jakarta')))->modify('+7 days')->format('Y-m-d');
try{$snapshot=daily_quota_snapshot($day);}catch(DomainException $e){json(['error'=>$e->getMessage()],422);}
$category=$_GET['kategori']??'';$query=trim($_GET['q']??'');
$items=array_values(array_filter($snapshot['items'],static function($p)use($category,$query){
  return (int)$p['kuota_harian']>0
    && (!in_array($category,['makanan','minuman','snack'],true)||$p['kategori']===$category)
    && ($query===''||stripos($p['nama'],$query)!==false||stripos($p['kode'],$query)!==false);
}));
$schedule=q("SELECT id,tgl_buka,tgl_tutup,nama FROM jadwal_po WHERE aktif ORDER BY tgl_buka LIMIT 1")->fetch();
json(['items'=>$items,'schedule'=>$schedule,'tanggal'=>$snapshot['tanggal'],'maxKitchen'=>$snapshot['maxKitchen'],'kitchenUsed'=>$snapshot['kitchenUsed'],'kitchenRemaining'=>$snapshot['kitchenRemaining']]);
