<?php
require __DIR__ . '/../../../app/bootstrap.php';
require_owner();

function paymentImage(array $file): string {
  if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    $code = $file['error'] ?? UPLOAD_ERR_NO_FILE;
    json(['error' => $code === UPLOAD_ERR_NO_FILE ? 'Pilih gambar metode pembayaran' : 'Gambar gagal diunggah'], 422);
  }
  if (($file['size'] ?? 0) > 2 * 1024 * 1024) json(['error' => 'Ukuran gambar maksimal 2 MB'], 422);
  $image = @getimagesize($file['tmp_name']);
  $types = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
  $mime = $image['mime'] ?? '';
  if (!isset($types[$mime])) json(['error' => 'Gunakan gambar JPG, PNG, atau WebP'], 422);
  $folder = __DIR__ . '/../../uploads/payment-methods';
  if (!is_dir($folder) && !mkdir($folder, 0775, true)) json(['error' => 'Folder gambar pembayaran tidak dapat dibuat'], 500);
  $filename = 'method-' . bin2hex(random_bytes(16)) . '.' . $types[$mime];
  if (!move_uploaded_file($file['tmp_name'], $folder . '/' . $filename)) json(['error' => 'Gagal menyimpan gambar pembayaran'], 500);
  return 'uploads/payment-methods/' . $filename;
}
function removePaymentImage(?string $path): void {
  if (!$path || !preg_match('~^uploads/payment-methods/method-[a-f0-9]+\.(jpg|png|webp)$~i', $path)) return;
  $file = __DIR__ . '/../../' . $path;
  if (is_file($file)) @unlink($file);
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
  json(['items' => q('SELECT id,nama,gambar,aktif FROM metode_pembayaran ORDER BY id DESC')->fetchAll()]);
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') json(['error' => 'Metode request tidak didukung'], 405);
$action = $_POST['action'] ?? '';
$id = (int)($_POST['id'] ?? 0);
if ($action === 'delete') {
  $item = q('SELECT gambar FROM metode_pembayaran WHERE id=?', [$id])->fetch();
  if (!$item) json(['error' => 'Metode pembayaran tidak ditemukan'], 404);
  q('DELETE FROM metode_pembayaran WHERE id=?', [$id]);
  removePaymentImage($item['gambar']);
  json(['ok' => true]);
}
if (!in_array($action, ['save', 'toggle'], true)) json(['error' => 'Aksi tidak valid'], 422);
$item = $id ? q('SELECT * FROM metode_pembayaran WHERE id=?', [$id])->fetch() : null;
if ($id && !$item) json(['error' => 'Metode pembayaran tidak ditemukan'], 404);
if ($action === 'toggle') {
  q('UPDATE metode_pembayaran SET aktif=?,updated_at=now() WHERE id=?', [($_POST['aktif'] ?? '') === '1', $id]);
  json(['ok' => true]);
}
$name = trim($_POST['nama'] ?? '');
if ($name === '' || mb_strlen($name) > 80) json(['error' => 'Nama metode wajib diisi, maksimal 80 karakter'], 422);
$active = ($_POST['aktif'] ?? '') === '1';
$oldImage = $item['gambar'] ?? null;
$newImage = null;
if (isset($_FILES['gambar']) && ($_FILES['gambar']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) $newImage = paymentImage($_FILES['gambar']);
if (!$item && !$newImage) json(['error' => 'Gambar wajib diunggah untuk metode baru'], 422);
$image = $newImage ?? $oldImage;
try {
  if ($item) q('UPDATE metode_pembayaran SET nama=?,gambar=?,aktif=?,updated_at=now() WHERE id=?', [$name, $image, $active, $id]);
  else q('INSERT INTO metode_pembayaran(nama,gambar,aktif) VALUES(?,?,?)', [$name, $image, $active]);
} catch (Throwable $e) {
  if ($newImage) removePaymentImage($newImage);
  json(['error' => 'Gagal menyimpan metode pembayaran'], 500);
}
if ($newImage && $oldImage) removePaymentImage($oldImage);
json(['ok' => true]);
json(['error' => 'Metode request tidak didukung'], 405);
