<?php // GET ?tab=validasi|proses&q=  | GET ?id=  | POST {id,action:validate|complete}
require __DIR__ . '/../../../app/bootstrap.php';
require_owner();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $b = body();
  $to = ['validate' => ['menunggu_validasi', 'diproses'], 'complete' => ['diproses', 'selesai']][$b['action'] ?? ''] ?? null;
  if (!$to) json(['error' => 'Aksi tidak valid'], 422);
  $n = q("UPDATE pesanan SET status=? WHERE id=? AND status=?", [$to[1], $b['id'] ?? 0, $to[0]])->rowCount();
  json($n ? ['ok' => true, 'status' => $to[1]] : ['error' => 'Status pesanan tidak sesuai'], $n ? 200 : 409);
}
if (isset($_GET['id'])) {
  $o = q("SELECT * FROM pesanan WHERE id=?", [$_GET['id']])->fetch() ?: json(['error' => 'Tidak ditemukan'], 404);
  if (!empty($o['bukti_bayar']) && !preg_match('~^(https?://|/)~i', $o['bukti_bayar'])) $o['bukti_bayar'] = '../' . $o['bukti_bayar'];
  $o['menu'] = q("SELECT pr.nama,d.qty,d.harga,d.qty*d.harga sub FROM detail_pesanan d JOIN produk pr ON pr.id=d.produk_id WHERE d.pesanan_id=?", [$o['id']])->fetchAll();
  json($o);
}
$tab = ($_GET['tab'] ?? 'validasi') === 'proses' ? "('diproses','selesai')" : "('menunggu_validasi')";
$s = '%' . ($_GET['q'] ?? '') . '%';
$list = q("SELECT id,kode,nama_pemesan,keterangan,total,status,to_char(created_at,'HH24:MI') jam FROM pesanan WHERE status IN $tab AND (nama_pemesan ILIKE ? OR kode ILIKE ?) ORDER BY created_at DESC", [$s, $s])->fetchAll();
$cnt = q("SELECT COUNT(*) FILTER(WHERE status='menunggu_validasi') v, COUNT(*) FILTER(WHERE status IN('diproses','selesai')) p FROM pesanan")->fetch();
$cap = q("SELECT j.kapasitas, COALESCE(SUM(p.pax) FILTER(WHERE p.status<>'ditolak'),0) pakai FROM jadwal_po j LEFT JOIN pesanan p ON p.jadwal_id=j.id WHERE j.aktif GROUP BY j.id LIMIT 1")->fetch();
json(['orders' => $list, 'count' => $cnt, 'kapasitas' => $cap]);
