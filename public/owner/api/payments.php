<?php
require __DIR__ . '/../../../app/bootstrap.php';
require_owner();

function payment_upload_extension(array $file): string
{
    if (!isset($file['error'], $file['tmp_name'], $file['size'])) {
        throw new DomainException('File gambar tidak lengkap.');
    }
    if ($file['error'] === UPLOAD_ERR_INI_SIZE || $file['error'] === UPLOAD_ERR_FORM_SIZE || (int) $file['size'] > 2 * 1024 * 1024) {
        throw new DomainException('Ukuran gambar maksimal 2 MB.');
    }
    if ($file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
        throw new DomainException('Gambar gagal diunggah. Silakan pilih ulang file.');
    }
    $info = @getimagesize($file['tmp_name']);
    $types = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    $mime = is_array($info) ? ($info['mime'] ?? '') : '';
    if (!isset($types[$mime])) {
        throw new DomainException('Gunakan gambar JPG, PNG, atau WebP.');
    }
    return $types[$mime];
}

function payment_delete_image(string $relative): void
{
    if (preg_match('#^uploads/payment-methods/method-[a-f0-9]+\.(?:jpg|png|webp)$#iD', $relative)) {
        $path = dirname(__DIR__, 2) . '/' . $relative;
        if (is_file($path)) @unlink($path);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    json([
        // SELECT mengambil semua metode pembayaran untuk dikelola owner.
        'items' => q('SELECT id, nama, gambar, aktif FROM metode_pembayaran ORDER BY id DESC')->fetchAll(),
    ]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    $action = $_POST['action'] ?? '';
    $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);

    try {
        if ($action === 'save') {
            $nama = trim((string) ($_POST['nama'] ?? ''));
            if ($nama === '' || (function_exists('mb_strlen') ? mb_strlen($nama) : strlen($nama)) > 80) {
                throw new DomainException('Nama metode wajib diisi (maksimal 80 karakter).');
            }
            $aktif = ($_POST['aktif'] ?? '0') === '1';
            $existing = null;
            if ($id !== false && $id !== null) {
                $existing = q('SELECT id, gambar FROM metode_pembayaran WHERE id = ?', [$id])->fetch();
                if (!$existing) json(['error' => 'Metode pembayaran tidak ditemukan.'], 404);
            } elseif (!empty($_POST['id'])) {
                throw new DomainException('ID metode pembayaran tidak valid.');
            }

            $newPath = null;
            if (isset($_FILES['gambar']) && (int) ($_FILES['gambar']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                $extension = payment_upload_extension($_FILES['gambar']);
                $directory = dirname(__DIR__, 2) . '/uploads/payment-methods';
                if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
                    throw new RuntimeException('Folder gambar pembayaran tidak dapat dibuat.');
                }
                if (!is_writable($directory)) throw new RuntimeException('Folder gambar pembayaran tidak dapat ditulis.');
                $filename = 'method-' . bin2hex(random_bytes(12)) . '.' . $extension;
                if (!move_uploaded_file($_FILES['gambar']['tmp_name'], $directory . '/' . $filename)) {
                    throw new RuntimeException('Gagal menyimpan gambar pembayaran.');
                }
                $newPath = 'uploads/payment-methods/' . $filename;
            }

            if (!$existing && $newPath === null) throw new DomainException('Unggah gambar QRIS terlebih dahulu.');
            if ($existing) {
                $gambar = $newPath ?? $existing['gambar'];
                q('UPDATE metode_pembayaran SET nama = ?, gambar = ?, aktif = ?, updated_at = now() WHERE id = ?', [$nama, $gambar, $aktif, $id]);
                if ($newPath !== null && $existing['gambar'] !== $newPath) payment_delete_image($existing['gambar']);
            } else {
                q('INSERT INTO metode_pembayaran (nama, gambar, aktif) VALUES (?, ?, ?)', [$nama, $newPath, $aktif]);
            }
            json(['ok' => true]);
        }

        if ($action === 'toggle') {
            if (!$id) json(['error' => 'ID metode pembayaran tidak valid.'], 422);
            q('UPDATE metode_pembayaran SET aktif = ?, updated_at = now() WHERE id = ?', [($_POST['aktif'] ?? '0') === '1', $id]);
            json(['ok' => true]);
        }

        if ($action === 'delete') {
            if (!$id) json(['error' => 'ID metode pembayaran tidak valid.'], 422);
            $existing = q('SELECT gambar FROM metode_pembayaran WHERE id = ?', [$id])->fetch();
            if (!$existing) json(['error' => 'Metode pembayaran tidak ditemukan.'], 404);
            q('DELETE FROM metode_pembayaran WHERE id = ?', [$id]);
            payment_delete_image($existing['gambar']);
            json(['ok' => true]);
        }

        json(['error' => 'Aksi metode pembayaran tidak dikenal.'], 422);
    } catch (DomainException $error) {
        json(['error' => $error->getMessage()], 422);
    } catch (PDOException $error) {
        error_log('Owner payment method database error: ' . $error->getMessage());
        json(['error' => 'Gagal menyimpan ke database. Pastikan migrasi database/004_payment_methods.sql sudah diterapkan.'], 500);
    } catch (Throwable $error) {
        error_log('Owner payment method error: ' . $error->getMessage());
        json(['error' => $error->getMessage() !== '' ? $error->getMessage() : 'Metode pembayaran gagal disimpan.'], 500);
    }
}

json(['error' => 'Metode request tidak didukung'], 405);
