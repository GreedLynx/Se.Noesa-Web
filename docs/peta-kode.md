# Peta Kode

## Revisi kuota stateless

| Kebutuhan | File |
|---|---|
| Checkout, alamat, bukti bayar, riwayat pelanggan | `public/pelanggan/api/orders.php` |
| Katalog pelanggan | `public/pelanggan/api/catalog.php` |
| Penyimpanan dan daftar produk admin | `public/admin/api/products.php` |
| Penolakan dan validasi pembayaran | `public/admin/api/orders.php` |
| Pengaturan kuota per tanggal lama | `public/admin/api/kuota-harian.php` |
| Koneksi PDO, prepared statement, session, role | `app/bootstrap.php` |
| Pelaksana migrasi dengan pencatatan versi | `scripts/migrate.php` |
| Kolom kuota harian master, sudah ada dalam working tree | `database/migrations/009_produk_kuota_harian.sql` |
| Indeks agregasi baru | `database/migrations/010_stateless_quota_indexes.sql` |
| Snapshot kuota dan validasi produk/dapur bersama | `app/daily-quota.php` |
| Pemilih tanggal kirim katalog | `public/pelanggan/index.php`, `public/pelanggan/assets/customer.js` |
| Kapasitas dan produk kritis admin | `public/admin/api/dashboard.php` |
| Produk owner, baca kuota tanpa aksi tulis | `public/owner/api/products.php` |
| Pengujian PostgreSQL dengan tabel sementara | `scripts/test-daily-quota.php` |
| VIEW ringkasan per tanggal | `database/migrations/011_daily_quota_report.sql` |
| Uji filter tanggal dan keranjang JavaScript | `scripts/test-quota-ui.cjs` |
| Filter tanggal Admin dan Owner | `public/admin/partials/layout.php`, `public/owner/partials/layout.php` |

Kuota mengikuti tanggal kirim Asia/Jakarta. Produk aktif memiliki konfigurasi `kuota_harian.kuota > 0` pada tanggal itu.
Helper bersama menjaga kesesuaian checkout, katalog, daftar produk admin, dan
ringkasan kapasitas admin. Penolakan memulihkan ketersediaan melalui agregasi status.
