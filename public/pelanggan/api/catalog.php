<?php
require __DIR__.'/../../../app/bootstrap.php'; require_role('pelanggan');
$category=$_GET['kategori']??'';$query='%'.trim($_GET['q']??'').'%';
$filter=in_array($category,['makanan','minuman','snack'],true)?'AND kategori=?':'';$args=[$query,$query];if($filter)$args[]=$category;
$items=q("SELECT id,kode,nama,kategori,harga,kuota,terpakai,min_pesan,kuota-terpakai sisa,gambar FROM produk WHERE kuota>terpakai AND (nama ILIKE ? OR kode ILIKE ?) $filter ORDER BY kategori,nama",$args)->fetchAll();
$schedule=q("SELECT id,tgl_buka,tgl_tutup,nama FROM jadwal_po WHERE aktif AND current_date BETWEEN tgl_buka AND tgl_tutup ORDER BY tgl_buka LIMIT 1")->fetch();
json(['items'=>$items,'schedule'=>$schedule]);
