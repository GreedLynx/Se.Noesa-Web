<?php // GET ?kategori=makanan|minuman|snack|kritis&q=
require __DIR__ . '/../../../app/bootstrap.php';
require_owner();
require_once __DIR__ . '/../../../app/daily-quota.php';
$s = trim($_GET['q'] ?? '');
$k = $_GET['kategori'] ?? '';
if ($_SERVER['REQUEST_METHOD'] !== 'GET') json(['error'=>'Method tidak didukung'],405);
$day=$_GET['tgl_kirim']??(new DateTimeImmutable('now',new DateTimeZone('Asia/Jakarta')))->format('Y-m-d');
try{$snapshot=daily_quota_snapshot($day,true);}catch(DomainException $e){json(['error'=>$e->getMessage()],422);}
$critical=static fn($p)=>(int)$p['kuota']===0 || (int)$p['sisa']<=(int)$p['kuota']*0.2;
$items=array_values(array_filter($snapshot['items'],static function($p)use($s,$k,$critical){
  return (!in_array($k,['makanan','minuman','snack'],true)||$p['kategori']===$k)
    && ($k!=='kritis'||$critical($p))
    && ($s===''||stripos($p['nama'],$s)!==false||stripos($p['kode'],$s)!==false);
}));
json(['items'=>$items,'count'=>['semua'=>count($snapshot['items']),'kritis'=>count(array_filter($snapshot['items'],$critical))],'tanggal'=>$day,'maxKitchen'=>$snapshot['maxKitchen'],'kitchenUsed'=>$snapshot['kitchenUsed'],'kitchenRemaining'=>$snapshot['kitchenRemaining']]);
