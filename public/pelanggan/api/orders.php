<?php
require __DIR__.'/../../../app/bootstrap.php';
$customer=require_role('pelanggan');
if($_SERVER['REQUEST_METHOD']==='POST'){
  $request=body();
  if(($request['action']??'')==='save_address'){
    $label=trim($request['label']??'Rumah');$recipient=trim($request['nama_penerima']??'');$phone=trim($request['telepon']??'');$address=trim($request['alamat']??'');
    if($label===''||strlen($label)>50||$recipient===''||strlen($recipient)>100||!preg_match('/^[0-9+() .-]{8,20}$/',$phone)||$address===''||strlen($address)>2000)json(['error'=>'Lengkapi label, nama penerima, nomor HP, dan alamat dengan benar'],422);
    try{$db=db();$db->beginTransaction();$count=(int)q('SELECT COUNT(*) FROM alamat_pelanggan WHERE user_id=?',[$customer['id']])->fetchColumn();if($count>=20)throw new DomainException('Maksimal 20 alamat tersimpan.');$isDefault=!empty($request['is_default'])||$count===0;if($isDefault)q('UPDATE alamat_pelanggan SET is_default=FALSE WHERE user_id=?',[$customer['id']]);$id=(int)q('INSERT INTO alamat_pelanggan(user_id,label,nama_penerima,telepon,alamat,is_default) VALUES(?,?,?,?,?,?) RETURNING id',[$customer['id'],$label,$recipient,$phone,$address,$isDefault])->fetchColumn();$db->commit();json(['ok'=>true,'id'=>$id,'is_default'=>$isDefault],201);}catch(DomainException $e){if(db()->inTransaction())db()->rollBack();json(['error'=>$e->getMessage()],422);}catch(Throwable $e){if(db()->inTransaction())db()->rollBack();json(['error'=>'Alamat belum dapat disimpan'],500);}
  }
  if(($request['action']??'')==='set_default_address'){$id=(int)($request['id']??0);try{$db=db();$db->beginTransaction();$found=q('SELECT id FROM alamat_pelanggan WHERE id=? AND user_id=? FOR UPDATE',[$id,$customer['id']])->fetch();if(!$found)throw new DomainException('Alamat tidak ditemukan');q('UPDATE alamat_pelanggan SET is_default=(id=?) WHERE user_id=?',[$id,$customer['id']]);$db->commit();json(['ok'=>true]);}catch(DomainException $e){if(db()->inTransaction())db()->rollBack();json(['error'=>$e->getMessage()],404);}}
  if(($_POST['action']??'')==='upload_proof'){
    $id=(int)($_POST['id']??0);$order=q("SELECT id,status FROM pesanan WHERE id=? AND user_id=?",[$id,$customer['id']])->fetch();
    if(!$order) json(['error'=>'Pesanan tidak ditemukan'],404);
    if(!in_array($order['status'],['menunggu_pembayaran','menunggu_validasi'],true)) json(['error'=>'Bukti hanya dapat diunggah untuk pesanan yang menunggu pembayaran atau verifikasi'],409);
    $file=$_FILES['proof']??null;if(!$file) json(['error'=>'Pilih file bukti pembayaran terlebih dahulu'],422);
    if($file['error']!==UPLOAD_ERR_OK) json(['error'=>in_array($file['error'],[UPLOAD_ERR_INI_SIZE,UPLOAD_ERR_FORM_SIZE],true)?'Ukuran gambar melewati batas PHP. Maksimal 2 MB.':'File bukti tidak berhasil diunggah.'],422);
    if($file['size']>2*1024*1024) json(['error'=>'Ukuran gambar maksimal 2 MB'],422);
    $payerName=trim($_POST['nama_pembayar']??'');
    if($payerName===''||strlen($payerName)>100) json(['error'=>'Nama pembayar wajib diisi (maksimal 100 karakter)'],422);
    $image=@getimagesize($file['tmp_name']);$types=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];$mime=$image['mime']??'';
    if(!isset($types[$mime])) json(['error'=>'Gunakan gambar JPG, PNG, atau WebP'],422);
    $folder=__DIR__.'/../../uploads/payments';if(!is_dir($folder)&&!mkdir($folder,0775,true)) json(['error'=>'Folder upload tidak dapat dibuat'],500);
    $filename='payment-'.$id.'-'.bin2hex(random_bytes(12)).'.'.$types[$mime];
    if(!move_uploaded_file($file['tmp_name'],$folder.'/'.$filename)) json(['error'=>'Gagal menyimpan gambar'],500);
    try{q("UPDATE pesanan SET bukti_bayar=?,nama_pembayar=?,status=CASE WHEN status='menunggu_pembayaran' THEN 'menunggu_validasi' ELSE status END WHERE id=? AND user_id=? AND status IN ('menunggu_pembayaran','menunggu_validasi')",['uploads/payments/'.$filename,$payerName,$id,$customer['id']]);}
    catch(Throwable $e){@unlink($folder.'/'.$filename);json(['error'=>'Gagal menyimpan bukti pembayaran'],500);}
    json(['ok'=>true,'bukti_bayar'=>'uploads/payments/'.$filename]);
  }
  $b=$request;$name=trim($b['nama_pemesan']??'');$items=$b['items']??[];$day=$b['tgl_kirim']??'';$time=trim((string)($b['waktu_kirim']??''));$hasDeliveryTime=$time!=='';$time=$hasDeliveryTime?$time:'12:00';$note=trim($b['keterangan']??'');$addressId=(int)($b['alamat_id']??0);$savedAddress=$addressId?q('SELECT nama_penerima,telepon,alamat FROM alamat_pelanggan WHERE id=? AND user_id=?',[$addressId,$customer['id']])->fetch():false;$recipient=trim($b['nama_penerima']??'');$phone=trim($b['telepon']??'');$address=trim($b['alamat']??'');if($savedAddress){$recipient=$savedAddress['nama_penerima'];$phone=$savedAddress['telepon'];$address=$savedAddress['alamat'];}
  if($name===''||strlen($name)>100||$recipient===''||strlen($recipient)>100||!preg_match('/^[0-9+() .-]{8,20}$/',$phone)||$address===''||strlen($address)>2000||!is_array($items)||!count($items)) json(['error'=>'Nama pemesan, penerima, nomor HP, alamat, dan pilihan menu wajib diisi dengan benar'],422);
  if(strlen($note)>120) json(['error'=>'Catatan maksimal 120 karakter'],422);
  $zone=new DateTimeZone('Asia/Jakarta');$delivery=DateTimeImmutable::createFromFormat('!Y-m-d H:i',$day.' '.$time,$zone);$dateErrors=DateTimeImmutable::getLastErrors();$now=new DateTimeImmutable('now',$zone);$minimumDelivery=$now->modify('+7 days')->format('Y-m-d');$deliveryTooSoon=$delivery&&$delivery->format('Y-m-d')<$minimumDelivery;
  if(!$delivery||($dateErrors&&($dateErrors['warning_count']||$dateErrors['error_count']))||$deliveryTooSoon) json(['error'=>'Tanggal pengiriman harus minimal 7 hari dari tanggal pemesanan'],422);
  if(count($items)>50)json(['error'=>'Maksimal 50 jenis menu per pesanan'],422);
  $quantities=[];foreach($items as $line){$pid=filter_var($line['id']??null,FILTER_VALIDATE_INT);$qty=filter_var($line['qty']??null,FILTER_VALIDATE_INT);if(!$pid||!$qty||$qty<1||$qty>500)json(['error'=>'Jumlah menu tidak valid'],422);$quantities[$pid]=($quantities[$pid]??0)+$qty;}ksort($quantities);
  try{
    $db=db();$db->beginTransaction();
    $schedule=q('SELECT id,kapasitas FROM jadwal_po WHERE aktif AND current_date BETWEEN tgl_buka AND tgl_tutup ORDER BY tgl_buka LIMIT 1 FOR UPDATE')->fetch();
    if(!$schedule) throw new DomainException('Jadwal pemesanan belum tersedia untuk tanggal tersebut.');
    $lines=[];$total=0;$pax=0;
    foreach($quantities as $pid=>$qty){
      $product=q('SELECT id,nama,harga,kuota,terpakai,min_pesan FROM produk WHERE id=? FOR UPDATE',[$pid])->fetch();
      if(!$product) throw new DomainException('Salah satu menu sudah tidak tersedia.');
      if($qty<$product['min_pesan']) throw new DomainException($product['nama'].' minimal dipesan '.$product['min_pesan'].' porsi.');
      if($qty>$product['kuota']-$product['terpakai']) throw new DomainException('Stok '.$product['nama'].' tidak mencukupi.');
      $lines[]=['product'=>$product,'qty'=>$qty];$total+=(int)$product['harga']*$qty;$pax+=$qty;
      if($total>2147483647)throw new DomainException('Total pesanan melebihi batas transaksi.');
    }
    $used=(int)q("SELECT COALESCE(SUM(pax),0) FROM pesanan WHERE jadwal_id=? AND status<>'ditolak'",[$schedule['id']])->fetchColumn();
    if($pax>(int)$schedule['kapasitas']-$used) throw new DomainException('Kapasitas dapur tidak mencukupi untuk jumlah pesanan ini.');
    $code='SN-'.(new DateTimeImmutable('now',$zone))->format('ymd').'-'.strtoupper(bin2hex(random_bytes(3)));
    $orderId=(int)q("INSERT INTO pesanan(kode,user_id,jadwal_id,nama_pemesan,keterangan,pax,total,status,tgl_kirim,alamat_pengiriman,telepon_penerima) VALUES(?,?,?,?,?,?,?,'menunggu_pembayaran',?,?,?) RETURNING id",[$code,$customer['id'],$schedule['id'],$recipient,$note?:null,$pax,$total,$delivery->format('Y-m-d H:i:sP'),$address,$phone])->fetchColumn();
    foreach($lines as $line){$p=$line['product'];$qty=$line['qty'];q('INSERT INTO detail_pesanan(pesanan_id,produk_id,qty,harga) VALUES(?,?,?,?)',[$orderId,$p['id'],$qty,$p['harga']]);q('UPDATE produk SET terpakai=terpakai+? WHERE id=?',[$qty,$p['id']]);}
    $db->commit();json(['ok'=>true,'id'=>$orderId,'kode'=>$code,'total'=>$total],201);
  }catch(DomainException $e){if(db()->inTransaction())db()->rollBack();json(['error'=>$e->getMessage()],422);}
  catch(Throwable $e){if(db()->inTransaction())db()->rollBack();json(['error'=>'Pesanan belum dapat disimpan. Coba beberapa saat lagi.'],500);}
}
if(isset($_GET['addresses'])){json(['addresses'=>q('SELECT id,label,nama_penerima,telepon,alamat,is_default FROM alamat_pelanggan WHERE user_id=? ORDER BY is_default DESC,id',[$customer['id']])->fetchAll()]);}
if(isset($_GET['id'])){
  $order=q('SELECT id,kode,nama_pemesan,nama_pembayar,telepon_penerima,alamat_pengiriman,keterangan,pax,total,status,bukti_bayar,tgl_kirim,created_at FROM pesanan WHERE id=? AND user_id=?',[(int)$_GET['id'],$customer['id']])->fetch();
  if(!$order) json(['error'=>'Pesanan tidak ditemukan'],404);
  $order['items']=q('SELECT pr.nama,pr.gambar,d.qty,d.harga,d.qty*d.harga subtotal FROM detail_pesanan d JOIN produk pr ON pr.id=d.produk_id WHERE d.pesanan_id=? ORDER BY pr.nama',[$order['id']])->fetchAll();json($order);
}
$status=$_GET['status']??'';$allowed=['menunggu_pembayaran','menunggu_validasi','diproses','selesai','ditolak'];$filter=in_array($status,$allowed,true)?'AND status=?':'';$args=[$customer['id']];if($filter)$args[]=$status;
$orders=q("SELECT id,kode,nama_pemesan,total,status,tgl_kirim,created_at FROM pesanan WHERE user_id=? $filter ORDER BY created_at DESC LIMIT 50",$args)->fetchAll();
foreach($orders as &$order)$order['items']=q('SELECT pr.nama,pr.gambar,d.qty,d.harga FROM detail_pesanan d JOIN produk pr ON pr.id=d.produk_id WHERE d.pesanan_id=? ORDER BY d.id LIMIT 3',[$order['id']])->fetchAll();unset($order);
json(['orders'=>$orders]);
