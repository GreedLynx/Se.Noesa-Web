<?php
require __DIR__ . '/../../../app/bootstrap.php';
require_role('admin');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $isMultipart = str_contains($_SERVER['CONTENT_TYPE'] ?? '', 'multipart/form-data');
  $b = $isMultipart ? $_POST : body();
  $action = $b['action'] ?? 'save';

  if ($action === 'delete') {
    try { $n = q('DELETE FROM produk WHERE id=?', [(int)($b['id'] ?? 0)])->rowCount(); }
    catch (PDOException $e) { json(['error' => 'Produk sudah dipakai pada pesanan dan tidak dapat dihapus'], 409); }
    json($n ? ['ok' => true] : ['error' => 'Produk tidak ditemukan'], $n ? 200 : 404);
  }

  foreach (['kode', 'nama', 'kategori', 'harga', 'kuota', 'terpakai', 'min_pesan'] as $field) {
    if (!isset($b[$field]) || trim((string)$b[$field]) === '') json(['error' => 'Semua kolom wajib diisi'], 422);
  }
  if (!in_array($b['kategori'], ['makanan', 'minuman', 'snack'], true)) json(['error' => 'Kategori tidak valid'], 422);
  $harga = filter_var($b['harga'], FILTER_VALIDATE_INT);
  $kuota = filter_var($b['kuota'], FILTER_VALIDATE_INT);
  $terpakai = filter_var($b['terpakai'], FILTER_VALIDATE_INT);
  $minimum = filter_var($b['min_pesan'], FILTER_VALIDATE_INT);
  if ($harga === false || $harga < 1 || $kuota === false || $kuota < 0 || $terpakai === false || $terpakai < 0 || $terpakai > $kuota || $minimum === false || $minimum < 1) {
    json(['error' => 'Harga dan kuota tidak valid'], 422);
  }

  $id = (int)($b['id'] ?? 0);
  $oldImage = $id ? q('SELECT gambar FROM produk WHERE id=?', [$id])->fetchColumn() : false;
  if ($id && $oldImage === false) json(['error' => 'Produk tidak ditemukan'], 404);
  $image = $oldImage ?: null;
  $file = $_FILES['gambar_file'] ?? null;
  if ($file && $file['error'] !== UPLOAD_ERR_NO_FILE) {
    if ($file['error'] !== UPLOAD_ERR_OK) json(['error' => 'Upload gagal. Pastikan ukuran gambar tidak lebih dari 2 MB'], 422);
    if ($file['size'] > 2 * 1024 * 1024) json(['error' => 'Ukuran gambar maksimal 2 MB'], 422);
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    if (!isset($extensions[$mime])) json(['error' => 'Format gambar harus JPG, PNG, atau WebP'], 422);

    $directory = __DIR__ . '/../../uploads/products';
    if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) json(['error' => 'Folder upload tidak dapat dibuat'], 500);
    $filename = bin2hex(random_bytes(16)) . '.' . $extensions[$mime];
    if (!move_uploaded_file($file['tmp_name'], $directory . '/' . $filename)) json(['error' => 'Gambar gagal disimpan'], 500);
    $image = 'uploads/products/' . $filename;
  } elseif (!$id) {
    json(['error' => 'Silakan upload gambar untuk produk baru'], 422);
  }

  try {
    if ($id) q('UPDATE produk SET kode=?,nama=?,kategori=?,harga=?,kuota=?,terpakai=?,min_pesan=?,gambar=? WHERE id=?', [$b['kode'], $b['nama'], $b['kategori'], $harga, $kuota, $terpakai, $minimum, $image, $id]);
    else q('INSERT INTO produk(kode,nama,kategori,harga,kuota,terpakai,min_pesan,gambar) VALUES(?,?,?,?,?,?,?,?)', [$b['kode'], $b['nama'], $b['kategori'], $harga, $kuota, $terpakai, $minimum, $image]);
  } catch (PDOException $e) {
    json(['error' => 'Kode produk sudah digunakan atau data tidak valid'], 409);
  }
  json(['ok' => true]);
}

$category = $_GET['kategori'] ?? '';
$search = '%' . trim($_GET['q'] ?? '') . '%';
$filter = in_array($category, ['makanan', 'minuman', 'snack'], true) ? 'AND kategori=?' : ($category === 'kritis' ? 'AND (kuota=0 OR kuota-terpakai<=kuota*0.2)' : '');
$args = [$search, $search];
if ($filter === 'AND kategori=?') $args[] = $category;
$items = q("SELECT id,kode,nama,kategori,harga,kuota,terpakai,min_pesan,kuota-terpakai sisa,gambar FROM produk WHERE (nama ILIKE ? OR kode ILIKE ?) $filter ORDER BY kode", $args)->fetchAll();
$count = q('SELECT COUNT(*) semua,COUNT(*) FILTER(WHERE kuota=0 OR kuota-terpakai<=kuota*0.2) kritis FROM produk')->fetch();
json(['items' => $items, 'count' => $count]);
