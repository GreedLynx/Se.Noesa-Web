<?php
require __DIR__ . '/../../../app/bootstrap.php';
require_role('admin');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
  if (!(error_reporting() & $severity)) return false;
  throw new ErrorException($message, 0, $severity, $file, $line);
});
set_exception_handler(static function (Throwable $error): void {
  error_log('[Se.Noesa products API] ' . $error);
  if (!headers_sent()) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
  }
  echo json_encode(['error' => 'Kesalahan server saat menyimpan produk: ' . $error->getMessage()], JSON_UNESCAPED_UNICODE);
});

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  // PHP mengosongkan $_POST dan $_FILES jika seluruh request melewati post_max_size.
  $contentLength = (int)($_SERVER['CONTENT_LENGTH'] ?? 0);
  $postLimit = trim((string)ini_get('post_max_size'));
  $postBytes = 0;
  if (preg_match('/^([0-9]+)\s*([KMG])?$/i', $postLimit, $limitMatch)) {
    $postBytes = (int)$limitMatch[1] * match (strtoupper($limitMatch[2] ?? '')) {
      'G' => 1024 ** 3,
      'M' => 1024 ** 2,
      'K' => 1024,
      default => 1
    };
  }
  if ($contentLength > 0 && $postBytes > 0 && $contentLength > $postBytes) json(['error' => 'Data upload melebihi batas PHP (' . $postLimit . '). Kecilkan gambar atau naikkan post_max_size dan upload_max_filesize.'], 413);
  $isMultipart = str_contains($_SERVER['CONTENT_TYPE'] ?? '', 'multipart/form-data');
  $b = $isMultipart ? $_POST : body();
  $action = $b['action'] ?? 'save';

  if ($action === 'delete') {
    try {
      $n = q('DELETE FROM produk WHERE id=?', [(int)($b['id'] ?? 0)])->rowCount();
    } catch (PDOException $e) {
      json(['error' => 'Produk sudah dipakai pada pesanan dan tidak dapat dihapus'], 409);
    }
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
    if ($file['error'] !== UPLOAD_ERR_OK) {
      $uploadErrors = [
        UPLOAD_ERR_INI_SIZE => 'Ukuran gambar melewati batas upload_max_filesize PHP (' . ini_get('upload_max_filesize') . ').',
        UPLOAD_ERR_FORM_SIZE => 'Ukuran gambar melewati batas formulir.',
        UPLOAD_ERR_PARTIAL => 'Gambar hanya terunggah sebagian. Coba unggah ulang.',
        UPLOAD_ERR_NO_TMP_DIR => 'Folder sementara upload PHP tidak tersedia.',
        UPLOAD_ERR_CANT_WRITE => 'PHP tidak dapat menulis file sementara upload.',
        UPLOAD_ERR_EXTENSION => 'Upload dihentikan oleh ekstensi PHP.',
      ];
      json(['error' => $uploadErrors[$file['error']] ?? 'Upload gambar gagal. Silakan coba lagi.'], 422);
    }
    if (!is_uploaded_file($file['tmp_name'])) json(['error' => 'File upload tidak valid. Silakan pilih ulang gambarnya.'], 422);
    if ($file['size'] > 2 * 1024 * 1024) json(['error' => 'Ukuran gambar maksimal 2 MB.'], 422);
    // Gunakan getimagesize agar upload tetap berfungsi pada PHP tanpa ekstensi fileinfo.
    $imageInfo = @getimagesize($file['tmp_name']);
    $mime = is_array($imageInfo) ? ($imageInfo['mime'] ?? '') : '';
    $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    if (!isset($extensions[$mime])) json(['error' => 'Format gambar harus JPG, PNG, atau WebP'], 422);

    // Dari public/admin/api, direktori upload berada di public/uploads/products.
    $directory = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'products';
    if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) json(['error' => 'Folder public/uploads/products tidak dapat dibuat. Periksa izin tulis folder public/uploads.'], 500);
    if (!is_writable($directory)) json(['error' => 'Folder public/uploads/products tidak dapat ditulis oleh PHP. Periksa izin folder.'], 500);
    $filename = bin2hex(random_bytes(16)) . '.' . $extensions[$mime];
    if (!move_uploaded_file($file['tmp_name'], $directory . DIRECTORY_SEPARATOR . $filename)) json(['error' => 'Gambar gagal disimpan ke public/uploads/products. Periksa izin folder dan ruang disk.'], 500);
    $image = 'uploads/products/' . $filename;
  } elseif (!$id) {
    json(['error' => 'Silakan upload gambar untuk produk baru'], 422);
  }

  try {
    if ($id) {
      q('UPDATE produk SET kode=?,nama=?,kategori=?,harga=?,kuota=?,terpakai=?,min_pesan=?,gambar=? WHERE id=?', [$b['kode'], $b['nama'], $b['kategori'], $harga, $kuota, $terpakai, $minimum, $image, $id]);
      $savedId = $id;
    } else {
      $savedId = (int)q('INSERT INTO produk(kode,nama,kategori,harga,kuota,terpakai,min_pesan,gambar) VALUES(?,?,?,?,?,?,?,?) RETURNING id', [$b['kode'], $b['nama'], $b['kategori'], $harga, $kuota, $terpakai, $minimum, $image])->fetchColumn();
    }
  } catch (PDOException $e) {
    json(['error' => 'Kode produk sudah digunakan atau data tidak valid'], 409);
  }
  json(['ok' => true, 'id' => $savedId, 'gambar' => $image]);
}

$category = $_GET['kategori'] ?? '';
$search = '%' . trim($_GET['q'] ?? '') . '%';
$filter = in_array($category, ['makanan', 'minuman', 'snack'], true) ? 'AND kategori=?' : ($category === 'kritis' ? 'AND (kuota=0 OR kuota-terpakai<=kuota*0.2)' : '');
$args = [$search, $search];
if ($filter === 'AND kategori=?') $args[] = $category;
$items = q("SELECT id,kode,nama,kategori,harga,kuota,terpakai,min_pesan,kuota-terpakai sisa,gambar FROM produk WHERE (nama ILIKE ? OR kode ILIKE ?) $filter ORDER BY kode", $args)->fetchAll();
$count = q('SELECT COUNT(*) semua,COUNT(*) FILTER(WHERE kuota=0 OR kuota-terpakai<=kuota*0.2) kritis FROM produk')->fetch();
json(['items' => $items, 'count' => $count]);
