<?php

// Lokasi file .env
$envPath = dirname(__DIR__) . '/.env';

// Periksa apakah file .env tersedia
if (!file_exists($envPath)) {
    die("File .env tidak ditemukan!");
}

// Membaca file .env
$env = parse_ini_file($envPath, false, INI_SCANNER_RAW);

if ($env === false) {
    die("Gagal membaca file .env!");
}

// Mengembalikan konfigurasi
return [
    'db' => [
        'host' => $env['DB_HOST'] ?? '127.0.0.1',
        'port' => $env['DB_PORT'] ?? '5432',
        'name' => $env['DB_NAME'] ?? $env['DB_DATABASE'] ?? '',
        'user' => $env['DB_USER'] ?? $env['DB_USERNAME'] ?? '',
        'pass' => $env['DB_PASSWORD'] ?? '',
        'sslmode' => $env['DB_SSLMODE'] ?? 'require',
    ],
    'contact' => [
        // Nomor resmi Admin. Kosong berarti belum tersedia; UI tidak boleh mengarang nomor.
        'admin_whatsapp' => trim((string) ($env['ADMIN_WHATSAPP'] ?? '')),
        // Tautan kanal sosial resmi; kosong berarti tidak ditampilkan kepada pelanggan.
        'instagram' => trim((string) ($env['INSTAGRAM_URL'] ?? '')),
        'tiktok' => trim((string) ($env['TIKTOK_URL'] ?? '')),
    ],
    'mail' => [
        'host' => $env['SMTP_HOST'] ?? '',
        'port' => (int)($env['SMTP_PORT'] ?? 587),
        'secure' => strtolower($env['SMTP_SECURE'] ?? 'tls'),
        'username' => $env['SMTP_USERNAME'] ?? '',
        'password' => $env['SMTP_PASSWORD'] ?? '',
        'from_email' => $env['SMTP_FROM_EMAIL'] ?? '',
        'from_name' => $env['SMTP_FROM_NAME'] ?? 'Se.Noesa Catering',
    ],
];
