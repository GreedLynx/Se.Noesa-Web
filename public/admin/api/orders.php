<?php
require __DIR__.'/../../../app/bootstrap.php'; require_role('admin');
if($_SERVER['REQUEST_METHOD']==='POST'){
  $b=body(); $actions=['validate'=>['menunggu_validasi','diproses'],'reject'=>['menunggu_validasi','ditolak'],'complete'=>['diproses','selesai']]; $transition=$actions[$b['action']??'']??null;
  if(!$transition) json(['error'=>'Aksi tidak valid'],422);
  if(($b['action']??'')==='reject'){
    $db=db();$db->beginTransaction();
    try{
      $current=q("SELECT id FROM pesanan WHERE id=? AND status='menunggu_validasi' FOR UPDATE",[(int)($b['id']??0)])->fetch();
      if(!$current){$db->rollBack();json(['error'=>'Status pesanan sudah berubah atau tidak sesuai'],409);}
      q("UPDATE pesanan SET status='ditolak' WHERE id=?",[$current['id']]);
      q("UPDATE produk p SET terpakai=GREATEST(0,p.terpakai-d.qty) FROM (SELECT produk_id,SUM(qty) qty FROM detail_pesanan WHERE pesanan_id=? GROUP BY produk_id) d WHERE p.id=d.produk_id",[$current['id']]);
      $db->commit();json(['ok'=>true,'status'=>'ditolak']);
    }catch(Throwable $e){if($db->inTransaction())$db->rollBack();json(['error'=>'Status pesanan belum dapat diperbarui'],500);}
  }
  if(($b['action']??'')==='validate'){
    $proof=q("SELECT bukti_bayar FROM pesanan WHERE id=? AND status='menunggu_validasi'",[(int)($b['id']??0)])->fetch();
    if(!$proof)json(['error'=>'Pesanan tidak ditemukan atau sudah berubah status'],409);
    if(empty($proof['bukti_bayar']))json(['error'=>'Pelanggan belum mengunggah bukti pembayaran'],409);
  }
  $n=q('UPDATE pesanan SET status=? WHERE id=? AND status=?',[$transition[1],(int)($b['id']??0),$transition[0]])->rowCount();
  json($n?['ok'=>true,'status'=>$transition[1]]:['error'=>'Status pesanan sudah berubah atau tidak sesuai'], $n?200:409);
}
if(isset($_GET['id'])){
  $o=q("SELECT p.*,COALESCE(u.username,p.nama_pemesan) akun_pemesan,u.phone FROM pesanan p LEFT JOIN users u ON u.id=p.user_id WHERE p.id=?",[(int)$_GET['id']])->fetch();
  if(!$o) json(['error'=>'Pesanan tidak ditemukan'],404);
  $o['menu']=q("SELECT pr.nama,pr.gambar,d.qty,d.harga,d.qty*d.harga subtotal FROM detail_pesanan d JOIN produk pr ON pr.id=d.produk_id WHERE d.pesanan_id=? ORDER BY pr.nama",[$o['id']])->fetchAll();
  json($o);
}
$tab=match(($_GET['tab']??'validasi')){'proses'=>"('diproses')",'selesai'=>"('selesai')",default=>"('menunggu_validasi')"};
$search='%'.trim($_GET['q']??'').'%';
$items=q("SELECT p.id,p.kode,p.nama_pemesan,p.keterangan,p.pax,p.total,p.status,p.tgl_kirim,p.created_at FROM pesanan p WHERE p.status IN $tab AND (p.nama_pemesan ILIKE ? OR p.kode ILIKE ?) ORDER BY CASE WHEN p.status='menunggu_validasi' THEN 0 ELSE 1 END,p.created_at DESC",[$search,$search])->fetchAll();
$count=q("SELECT COUNT(*) FILTER(WHERE status='menunggu_validasi') pending,COUNT(*) FILTER(WHERE status='diproses') processing,COUNT(*) FILTER(WHERE status='selesai') completed FROM pesanan")->fetch();
$cap=q("SELECT COALESCE(SUM(j.kapasitas),0) kapasitas,COALESCE(SUM(x.pakai),0) pakai FROM jadwal_po j LEFT JOIN LATERAL(SELECT COALESCE(SUM(p.pax),0) pakai FROM pesanan p WHERE p.jadwal_id=j.id AND p.status<>'ditolak') x ON true WHERE j.aktif")->fetch();
json(['items'=>$items,'counts'=>$count,'capacity'=>$cap]);
