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
        'host'    => $env['DB_HOST'],
        'port'    => $env['DB_PORT'],
        'name'    => $env['DB_NAME'],
        'user'    => $env['DB_USER'],
        'pass'    => $env['DB_PASSWORD'],
        'sslmode' => $env['DB_SSLMODE'] ?? 'require',
    ],
];