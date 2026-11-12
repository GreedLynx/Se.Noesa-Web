# Peta Kode

## Pemilih tanggal bersama

| Kebutuhan | File |
|---|---|
| Kalender dialog, label tanggal Indonesia, navigasi keyboard, dan batas tanggal input | `public/assets/js/date-picker.js` |
| Tampilan kartu pengiriman, filter ringkas, dan kalender sage | `public/assets/css/date-picker.css` |
| Grid desktop Owner dan lebar ringkasan kuota | `public/assets/css/app.css` |
| Kartu tanggal pengiriman pelanggan | `public/pelanggan/index.php` |
| Pemuatan aset bersama dan filter tanggal Admin/Owner | `public/pelanggan/partials/layout.php`, `public/admin/partials/layout.php`, `public/owner/partials/layout.php` |
| Sinkronisasi tanggal katalog saat checkout berubah | `public/pelanggan/assets/customer.js` |
| Uji kalender dan uji browser opsional tanpa dependensi tambahan | `scripts/test-date-picker.cjs` |

Kalender memperbarui input tanggal yang sudah ada dan memicu event `input`/`change`.
Browser tanpa dukungan dialog atau JavaScript tetap memakai input tanggal bawaan.
Perubahan ini terbatas pada pemilih tanggal katalog dan filter kuota, bukan editor
jadwal/kuota maupun form checkout. Kalender bawaan checkout tetap dipertahankan.

Panel tanggal memenuhi seluruh kolom grid halaman Owner. Uji browser memuat
markup panel dari layout Owner beserta pembungkus `.app` pada halaman Produk dan
Pesanan agar benturan grid desktop ikut diuji.

## Revisi kuota stateless

| Kebutuhan | File |
|---|---|
| Checkout, alamat, bukti bayar, riwayat pelanggan | `public/pelanggan/api/orders.php` |
| Katalog pelanggan | `public/pelanggan/api/catalog.php` |
| Penyimpanan dan daftar produk admin | `public/admin/api/products.php` |
| Penolakan dan validasi pembayaran | `public/admin/api/orders.php` |
| Save/reset override tanggal dan rentang inklusif | `public/admin/api/kuota-harian.php` |
| Form default produk dan override rentang | `public/admin/products.php`, `public/admin/assets/admin.js` |
| Koneksi PDO, prepared statement, session, role | `app/bootstrap.php` |
| Pelaksana migrasi dengan pencatatan versi | `scripts/migrate.php` |
| Autentikasi bersama dan validasi field registrasi | `public/api/auth.php` |
| Token dan OTP reset password | `database/migrations/012_password_resets.sql`, `database/migrations/013_password_reset_otps.sql` |
| Constraint numerik dan urutan tanggal | `database/migrations/014_numeric_check_constraints.sql` |
| Laporan dashboard Owner dengan alias respons kompatibel | `public/owner/api/dashboard.php` |
| Kolom kuota harian master, sudah ada dalam working tree | `database/migrations/009_produk_kuota_harian.sql` |
| Indeks agregasi baru | `database/migrations/010_stateless_quota_indexes.sql` |
| Snapshot kuota dan validasi produk/dapur bersama | `app/daily-quota.php` |
| Pemilih tanggal kirim katalog | `public/pelanggan/index.php`, `public/pelanggan/assets/customer.js` |
| Kapasitas dan produk kritis admin | `public/admin/api/dashboard.php` |
| Produk owner, baca kuota tanpa aksi tulis | `public/owner/api/products.php` |
| Pengujian PostgreSQL dengan tabel sementara | `scripts/test-daily-quota.php` |
| Uji endpoint default/rentang Admin dan penolakan role dengan tabel TEMP | `scripts/test-quota-api.php` |
| VIEW ringkasan per tanggal | `database/migrations/011_daily_quota_report.sql` |
| Default dari override terbaru, CHECK default, dan VIEW efektif | `database/migrations/015_default_product_quota.sql` |
| Uji filter tanggal dan keranjang JavaScript | `scripts/test-quota-ui.cjs` |
| Filter tanggal Admin dan Owner | `public/admin/partials/layout.php`, `public/owner/partials/layout.php` |

Kuota mengikuti tanggal kirim Asia/Jakarta. Kuota efektif memakai override
`kuota_harian.kuota` bila ada, selain itu default `produk.kuota_harian`. Override 0
menonaktifkan produk. Save rentang menimpa overlap; reset kembali ke default.
Helper bersama menjaga kesesuaian checkout, katalog, daftar produk admin, dan
ringkasan kapasitas admin. Penolakan memulihkan ketersediaan melalui agregasi status.
