<?php
require __DIR__ . '/../../../app/bootstrap.php';
require_role('admin');
require_once __DIR__.'/../../../app/daily-quota.php';
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
  if (!(error_reporting() & $severity)) return false;
  throw new ErrorException($message, 0, $severity, $file, $line);
});
set_exception_handler(static function (Throwable $error): void {
  if(db()->inTransaction())db()->rollBack();
  error_log('[Se.Noesa kuota-harian API] ' . $error);
  if (!headers_sent()) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
  }
  echo json_encode(['error' => 'Kuota harian belum dapat disimpan'], JSON_UNESCAPED_UNICODE);
});

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $b = body();
  $action = $b['action'] ?? 'save';

  if ($action === 'delete') {
    $id = filter_var($b['id'] ?? null, FILTER_VALIDATE_INT);
    if ($id === false || $id<1) json(['error' => 'ID tidak valid'], 422);
    db()->beginTransaction();
    q('LOCK TABLE kuota_harian IN SHARE ROW EXCLUSIVE MODE');
    $n = q('DELETE FROM kuota_harian WHERE id=?', [$id])->rowCount();
    db()->commit();
    json($n ? ['ok' => true] : ['error' => 'Kuota harian tidak ditemukan'], $n ? 200 : 404);
  }

  $produkId = filter_var($b['produk_id'] ?? null, FILTER_VALIDATE_INT);
  $tanggal = $b['tanggal'] ?? '';
  $kuota = filter_var($b['kuota'] ?? null, FILTER_VALIDATE_INT);

  if ($produkId === false || $produkId < 1) json(['error' => 'produk_id tidak valid'], 422);
  if (!is_string($tanggal) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggal)) json(['error' => 'tanggal harus format YYYY-MM-DD'], 422);
  $parsed = date_create_from_format('Y-m-d', $tanggal);
  if (!$parsed || $parsed->format('Y-m-d') !== $tanggal) json(['error' => 'tanggal tidak valid'], 422);
  if ($kuota === false || $kuota < 0 || $kuota>2147483647) json(['error' => 'kuota harus angka 0 sampai 2147483647'], 422);
  if(!q('SELECT id FROM produk WHERE id=?',[$produkId])->fetchColumn())json(['error'=>'Produk tidak ditemukan'],404);
  db()->beginTransaction();
  q('LOCK TABLE kuota_harian IN SHARE ROW EXCLUSIVE MODE');

  q(
    'INSERT INTO kuota_harian(produk_id,tanggal,kuota) VALUES(?,?,?) ON CONFLICT(produk_id,tanggal) DO UPDATE SET kuota=EXCLUDED.kuota',
    [$produkId, $tanggal, $kuota]
  );
  db()->commit();
  json(['ok' => true]);
}

$produkId = $_GET['produk_id'] ?? null;
$tanggal = $_GET['tanggal'] ?? null;
if($_SERVER['REQUEST_METHOD']!=='GET')json(['error'=>'Method tidak didukung'],405);
if($tanggal!==null){try{daily_quota_snapshot($tanggal);}catch(DomainException $e){json(['error'=>$e->getMessage()],422);}}

if ($produkId !== null && $tanggal !== null) {
  $row = q('SELECT id,produk_id,tanggal,kuota FROM kuota_harian WHERE produk_id=? AND tanggal=?', [(int)$produkId, $tanggal])->fetch();
  json($row ?: null);
}

if ($produkId !== null) {
  $items = q('SELECT id,produk_id,tanggal,kuota FROM kuota_harian WHERE produk_id=? ORDER BY tanggal DESC', [(int)$produkId])->fetchAll();
  json(['items' => $items]);
}

json(['items' => []]);
