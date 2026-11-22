<?php
// Shared helpers for payment-proof upload, storage, access, and cleanup.
// File proof tetap menjadi catatan terpisah; fungsi di sini tidak mengubah status pesanan.

function proof_upload_extension(array $file, bool $fromUpload = true): string
{
    if (!isset($file['error'], $file['tmp_name'], $file['size'])) {
        throw new DomainException('File bukti tidak lengkap.');
    }
    if ($file['error'] === UPLOAD_ERR_INI_SIZE || $file['error'] === UPLOAD_ERR_FORM_SIZE) {
        throw new DomainException('Ukuran gambar melewati batas PHP. Maksimal 2 MB.');
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new DomainException('File bukti tidak berhasil diunggah.');
    }
    if ((int) $file['size'] > 2 * 1024 * 1024) {
        throw new DomainException('Ukuran gambar maksimal 2 MB');
    }
    if ($fromUpload && !is_uploaded_file($file['tmp_name'])) {
        throw new DomainException('File upload tidak valid. Silakan pilih ulang filenya.');
    }
    $info = @getimagesize($file['tmp_name']);
    $mime = is_array($info) ? ($info['mime'] ?? '') : '';
    $types = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    if (!isset($types[$mime])) {
        throw new DomainException('Gunakan gambar JPG, PNG, atau WebP');
    }
    return $types[$mime];
}

function proof_store(array $file, string $prefix, bool $fromUpload = true): string
{
    $extension = proof_upload_extension($file, $fromUpload);
    $directory = dirname(__DIR__) . '/storage/payments';
    if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
        throw new RuntimeException('Folder penyimpanan bukti tidak dapat dibuat');
    }
    if (!is_writable($directory)) {
        throw new RuntimeException('Folder penyimpanan bukti tidak dapat ditulis');
    }
    $filename = $prefix . '-' . bin2hex(random_bytes(12)) . '.' . $extension;
    $moved = $fromUpload
        ? move_uploaded_file($file['tmp_name'], $directory . '/' . $filename)
        : copy($file['tmp_name'], $directory . '/' . $filename);
    if (!$moved) {
        throw new RuntimeException('Gagal menyimpan bukti pembayaran');
    }
    return 'storage/payments/' . $filename;
}

function proof_resolve(string $relative): ?string
{
    $relative = str_replace('\\', '/', trim($relative));
    if (!preg_match('#^(storage/payments|uploads/payments)/([A-Za-z0-9._-]+)$#D', $relative, $m)) {
        return null;
    }
    if ($m[2] === '.' || $m[2] === '..') {
        return null;
    }
    // Bukti baru berada di storage/payments/ (di luar public). Bukti lama
    // disimpan sebagai uploads/payments/... relatif terhadap document root public/.
    $base = str_starts_with($relative, 'storage/payments/')
        ? dirname(__DIR__)
        : dirname(__DIR__) . '/public';
    $path = $base . '/' . $relative;
    return is_file($path) ? $path : null;
}

function proof_mime(string $path): string
{
    // fileinfo tidak selalu tersedia; gunakan getimagesize sebagai fallback.
    if (function_exists('mime_content_type')) {
        $mime = @mime_content_type($path);
        if (is_string($mime) && $mime !== '') return $mime;
    }
    if (function_exists('getimagesize')) {
        $info = @getimagesize($path);
        if (is_array($info) && !empty($info['mime'])) return (string) $info['mime'];
    }
    return '';
}

function proof_stream(string $relative): void
{
    $path = proof_resolve($relative);
    if ($path === null) {
        json(['error' => 'Bukti pembayaran tidak ditemukan'], 404);
    }
    $mime = proof_mime($path) ?: 'application/octet-stream';
    $allowed = ['image/jpeg', 'image/png', 'image/webp'];
    if (!in_array($mime, $allowed, true)) {
        json(['error' => 'Format bukti tidak diizinkan'], 422);
    }
    header('Content-Type: ' . $mime);
    header('Content-Length: ' . filesize($path));
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: private, no-store, max-age=0');
    readfile($path);
    exit;
}

function proof_referenced(string $relative): bool
{
    if ($relative === '') return false;
    $inOrders = (bool) q('SELECT 1 FROM pesanan WHERE bukti_bayar = ? LIMIT 1', [$relative])->fetchColumn();
    if ($inOrders) return true;
    // Late-payment table may not exist before migration 026 is applied.
    $exists = (bool) q("SELECT to_regclass('pembayaran_terlambat') IS NOT NULL")->fetchColumn();
    if ($exists) {
        return (bool) q('SELECT 1 FROM pembayaran_terlambat WHERE bukti_path = ? LIMIT 1', [$relative])->fetchColumn();
    }
    return false;
}

function proof_delete_if_unreferenced(string $relative): void
{
    if ($relative === '' || proof_referenced($relative)) return;
    $path = proof_resolve($relative);
    if ($path !== null) @unlink($path);
}
