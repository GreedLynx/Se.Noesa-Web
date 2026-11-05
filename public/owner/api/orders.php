<?php // GET ?tab=validasi|proses&q=  | GET ?id=  | POST {id,action:validate|complete}
require __DIR__ . '/../../../app/bootstrap.php';
require_owner();
require_once __DIR__ . '/../../../app/daily-quota.php';
if ($_SERVER['REQUEST_METHOD'] !== 'GET')
  json(['error' => 'Method tidak didukung'], 405);

if (isset($_GET['id'])) {
  $o = q("SELECT * FROM pesanan WHERE id=?", [$_GET['id']])->fetch() ?: json(['error' => 'Tidak ditemukan'], 404);
  if (!empty($o['bukti_bayar']) && !preg_match('~^(https?://|/)~i', $o['bukti_bayar']))
    $o['bukti_bayar'] = '../' . $o['bukti_bayar'];
  $o['menu'] = q("SELECT pr.nama,d.qty,d.harga,d.qty*d.harga sub FROM detail_pesanan d JOIN produk pr ON pr.id=d.produk_id WHERE d.pesanan_id=?", [$o['id']])->fetchAll();
  json($o);
}

$tab = ($_GET['tab'] ?? 'validasi') === 'proses' ? "('diproses','selesai')" : "('menunggu_validasi')";
$s = '%' . ($_GET['q'] ?? '') . '%';
$list = q("SELECT id,kode,nama_pemesan,keterangan,total,status,to_char(COALESCE(status_updated_at,created_at),'HH24:MI') jam FROM pesanan WHERE status IN $tab AND (nama_pemesan ILIKE ? OR kode ILIKE ?) ORDER BY created_at DESC", [$s, $s])->fetchAll();
$cnt = q("SELECT COUNT(*) FILTER(WHERE status='menunggu_validasi') v, COUNT(*) FILTER(WHERE status IN('diproses','selesai')) p FROM pesanan")->fetch();
$day = $_GET['tgl_kirim'] ?? (new DateTimeImmutable('now', new DateTimeZone('Asia/Jakarta')))->format('Y-m-d');
try {
  $quota = daily_quota_snapshot($day, true);
} catch (DomainException $e) {
  json(['error' => $e->getMessage()], 422);
}
$cap = ['kapasitas' => $quota['maxKitchen'], 'pakai' => $quota['kitchenUsed']];

json(['orders' => $list, 'count' => $cnt, 'kapasitas' => $cap]);
