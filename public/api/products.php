<?php // GET ?kategori=makanan|minuman|snack|kritis&q=
require __DIR__.'/../../app/bootstrap.php'; require_owner();
$s='%'.($_GET['q']??'').'%'; $k=$_GET['kategori']??'';
$w=in_array($k,['makanan','minuman','snack'])?"AND kategori='$k'":($k==='kritis'?"AND (kuota-terpakai)<=kuota*0.2":'');
$items=q("SELECT kode,nama,harga,kuota,kuota-terpakai sisa,gambar,kategori FROM produk WHERE (nama ILIKE ? OR kode ILIKE ?) $w ORDER BY kode",[$s,$s])->fetchAll();
$c=q("SELECT COUNT(*) semua, COUNT(*) FILTER(WHERE kuota-terpakai<=kuota*0.2) kritis FROM produk")->fetch();
json(['items'=>$items,'count'=>$c]);
