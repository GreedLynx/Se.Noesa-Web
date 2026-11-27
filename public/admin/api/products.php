<?php
require __DIR__ . '/../../../app/bootstrap.php';
require_role('admin');
require_once __DIR__ . '/../../../app/daily-quota.php';
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
    if (!(error_reporting() & $severity)) {
        return false;
    }
    throw new ErrorException($message, 0, $severity, $file, $line);
});
set_exception_handler(static function (Throwable $error): void {
    try {
        if (db()->inTransaction()) {
            db()->rollBack();
        }
    } catch (Throwable) {
    }
    error_log('[se.noesa products API] ' . $error);
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
    }
    echo json_encode(['error' => 'Kesalahan server saat menyimpan produk: ' . $error->getMessage()], JSON_UNESCAPED_UNICODE);
});

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    // PHP mengosongkan $_POST dan $_FILES jika seluruh request melewati post_max_size.
    $contentLength = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
    $postLimit = trim((string) ini_get('post_max_size'));
    $postBytes = 0;
    if (preg_match('/^([0-9]+)\s*([KMG])?$/i', $postLimit, $limitMatch)) {
        $postBytes = (int) $limitMatch[1] * match (strtoupper($limitMatch[2] ?? '')) {
            'G' => 1024 ** 3,
            'M' => 1024 ** 2,
            'K' => 1024,
            default => 1
        };
    }
    if ($contentLength > 0 && $postBytes > 0 && $contentLength > $postBytes) {
        json(['error' => 'Data upload melebihi batas PHP (' . $postLimit . '). Kecilkan gambar atau naikkan post_max_size dan upload_max_filesize.'], 413);
    }
    $isMultipart = str_contains($_SERVER['CONTENT_TYPE'] ?? '', 'multipart/form-data');
    $request = $isMultipart ? $_POST : body();
    $action = $request['action'] ?? 'save';

    if ($action === 'delete') {
        try {
            // DELETE menghapus produk berdasarkan ID; foreign key menolak penghapusan jika sudah dipakai.
            $affectedRows = q('DELETE
                FROM produk
                WHERE id = ?', [(int) ($request['id'] ?? 0)])->rowCount();
        } catch (PDOException $e) {
            json(['error' => 'Produk sudah dipakai pada pesanan dan tidak dapat dihapus'], 409);
        }
        json($affectedRows ? ['ok' => true] : ['error' => 'Produk tidak ditemukan'], $affectedRows ? 200 : 404);
    }

    foreach (['kode', 'nama', 'kategori', 'harga', 'min_pesan'] as $field) {
        if (!isset($request[$field]) || trim((string) $request[$field]) === '') {
            json(['error' => 'Semua kolom wajib diisi'], 422);
        }
    }
    if (!in_array($request['kategori'], ['makanan', 'minuman', 'snack'], true)) {
        json(['error' => 'Kategori tidak valid'], 422);
    }
    $harga = filter_var($request['harga'], FILTER_VALIDATE_INT);
    $minimum = filter_var($request['min_pesan'], FILTER_VALIDATE_INT);
    $defaultQuota = filter_var($request['kuota_default'] ?? $request['kuota_harian'] ?? 50, FILTER_VALIDATE_INT);
    if ($defaultQuota === false || $defaultQuota < 0 || $defaultQuota > 2147483647) {
        json(['error' => 'Kuota default harus angka 0 sampai 2147483647'], 422);
    }
    if ($harga === false || $harga < 1) {
        json(['error' => 'Harga tidak valid'], 422);
    }
    // Sumber kebenaran minimum: constraint DB (min_pesan >= 4) dan snapshot (GREATEST(4, min_pesan)).
    if ($minimum === false || $minimum < SENOESA_MIN_PACKAGE_QTY) {
        json(['error' => 'Minimum pemesanan minimal ' . SENOESA_MIN_PACKAGE_QTY . ' porsi'], 422);
    }

    $id = (int) ($request['id'] ?? 0);
    if ($id < 0) {
        json(['error' => 'ID produk tidak valid'], 422);
    }
    $file = $_FILES['gambar_file'] ?? null;
    $hasNewImage = $file && ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
    $newImageExtension = null;
    // Dari public/admin/api, direktori upload berada di public/uploads/products.
    $uploadDirectory = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'products';

    if ($hasNewImage) {
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
        if (!is_uploaded_file($file['tmp_name'])) {
            json(['error' => 'File upload tidak valid. Silakan pilih ulang gambarnya.'], 422);
        }
        if ($file['size'] > 2 * 1024 * 1024) {
            json(['error' => 'Ukuran gambar maksimal 2 MB.'], 422);
        }
        // Gunakan getimagesize agar upload tetap berfungsi pada PHP tanpa ekstensi fileinfo.
        $imageInfo = @getimagesize($file['tmp_name']);
        $mime = is_array($imageInfo) ? ($imageInfo['mime'] ?? '') : '';
        $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        if (!isset($extensions[$mime])) {
            json(['error' => 'Format gambar harus JPG, PNG, atau WebP'], 422);
        }
        $newImageExtension = $extensions[$mime];
    } elseif (!$id) {
        json(['error' => 'Silakan upload gambar untuk produk baru'], 422);
    }

    $db = db();
    $uploadedPath = null;
    $db->beginTransaction();
    try {
        if ($id > 0) {
            // Lock kanonik sebelum memeriksa pemakaian agar checkout baru tidak menyelinap di tengah penyimpanan.
            daily_quota_lock_tables();
            // SELECT mengambil gambar lama agar tetap dipakai ketika produk diedit tanpa upload baru.
            $oldImage = q('SELECT gambar
                FROM produk
                WHERE id = ?', [$id])->fetchColumn();
            if ($oldImage === false) {
                $db->rollBack();
                json(['error' => 'Produk tidak ditemukan'], 404);
            }
            $conflict = daily_quota_find_usage_conflict($id, (int) $defaultQuota, null, null, false);
            if ($conflict !== null) {
                $db->rollBack();
                json(daily_quota_conflict_payload($conflict, (int) $defaultQuota, 'default'), 409);
            }
            $image = $oldImage ?: null;
        } else {
            $image = null;
        }

        if ($hasNewImage) {
            if (!is_dir($uploadDirectory) && !mkdir($uploadDirectory, 0775, true) && !is_dir($uploadDirectory)) {
                $db->rollBack();
                json(['error' => 'Folder public/uploads/products tidak dapat dibuat. Periksa izin tulis folder public/uploads.'], 500);
            }
            if (!is_writable($uploadDirectory)) {
                $db->rollBack();
                json(['error' => 'Folder public/uploads/products tidak dapat ditulis oleh PHP. Periksa izin folder.'], 500);
            }
            $filename = bin2hex(random_bytes(16)) . '.' . $newImageExtension;
            $targetPath = $uploadDirectory . DIRECTORY_SEPARATOR . $filename;
            if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
                $db->rollBack();
                json(['error' => 'Gambar gagal disimpan ke public/uploads/products. Periksa izin folder dan ruang disk.'], 500);
            }
            $uploadedPath = $targetPath;
            $image = 'uploads/products/' . $filename;
        }

        if ($id > 0) {
            // UPDATE menyimpan perubahan pada produk yang sudah ada.
            q('UPDATE produk
                SET kode = ?, nama = ?, kategori = ?, harga = ?, min_pesan = ?, gambar = ?, kuota_harian = ?
                WHERE id = ?', [$request['kode'], $request['nama'], $request['kategori'], $harga, $minimum, $image, $defaultQuota, $id]);
            $savedId = $id;
        } else {
            // INSERT produk baru dan RETURNING mengambil ID hasil insert.
            $savedId = (int) q('INSERT INTO produk (kode, nama, kategori, harga, min_pesan, gambar, kuota_harian)
                VALUES (?, ?, ?, ?, ?, ?, ?)
                RETURNING id', [$request['kode'], $request['nama'], $request['kategori'], $harga, $minimum, $image, $defaultQuota])->fetchColumn();
        }

        $db->commit();
    } catch (PDOException $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        if ($uploadedPath !== null && is_file($uploadedPath)) {
            @unlink($uploadedPath);
        }
        json(['error' => 'Kode produk sudah digunakan atau data tidak valid'], 409);
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        if ($uploadedPath !== null && is_file($uploadedPath)) {
            @unlink($uploadedPath);
        }
        throw $e;
    }
    json(['ok' => true, 'id' => $savedId, 'gambar' => $image]);
}

$category = $_GET['kategori'] ?? '';
$date = $_GET['tgl_kirim'] ?? (new DateTimeImmutable('now', new DateTimeZone('Asia/Jakarta')))->format('Y-m-d');
try {
    $snapshot = daily_quota_snapshot($date, true);
} catch (DomainException $e) {
    json(['error' => $e->getMessage()], 422);
}
$all = $snapshot['items'];
$critical = static fn($product) => !$snapshot['tanggal_libur'] && ((int) $product['kuota'] === 0 || (int) $product['sisa'] <= (int) $product['kuota'] * 0.2);
$query = trim($_GET['q'] ?? '');
$items = array_values(array_filter($all, static function ($product) use ($category, $query, $critical) {
    return (!in_array($category, ['makanan', 'minuman', 'snack'], true) || $product['kategori'] === $category)
        && ($category !== 'kritis' || $critical($product))
        && ($query === '' || stripos($product['nama'], $query) !== false || stripos($product['kode'], $query) !== false);
}));
$count = ['semua' => count($all), 'kritis' => count(array_filter($all, $critical))];
json(['items' => $items, 'count' => $count, 'tanggal' => $date, 'tanggal_libur' => $snapshot['tanggal_libur']]);
