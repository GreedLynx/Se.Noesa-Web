# Peta Kode

## Kalender libur operasional

| Kebutuhan | File |
|---|---|
| UI Admin rentang libur inklusif | `public/admin/schedules.php`, `public/admin/assets/admin.js` |
| CRUD tanggal libur role Admin | `public/admin/api/holidays.php` |
| Overlay kuota efektif semua produk menjadi 0 | `app/daily-quota.php`, `database/migrations/019_operational_holidays.sql` |
| Peringatan tanggal libur dan blokir checkout pelanggan | `public/pelanggan/api/catalog.php`, `public/pelanggan/api/orders.php`, `public/pelanggan/assets/customer.js` |
| Rekap porsi per menu pada tanggal pilihan, dengan tautan detail pesanan | `database/migrations/020_daily_production_report.sql`, `public/admin/dashboard.php`, `public/admin/api/dashboard.php`, `public/admin/assets/admin.js`, `public/admin/assets/admin.css` |

Operasional selalu buka secara default. Kuota tersimpan tidak diubah saat
rentang libur dibuat. Pesanan lama tidak berubah otomatis; penolakan manual
dengan alasan dan tawaran jadwal ulang menjadi tahap fitur Admin berikutnya.
`jadwal_po` dipertahankan sebagai referensi pesanan; UI Jadwal tidak lagi
mengelola periode PO.

## Pembatalan pesanan dan kedaluwarsa pembayaran

| Kebutuhan | File |
|---|---|
| Cancel milik sendiri sampai H-2, upload sebelum satu jam, izin/waktu detail | `public/pelanggan/api/orders.php` |
| Tampilan pembatalan lama (tombol merah/kartu syarat/pesan H-2), izin server dan refund | `public/pelanggan/assets/customer.js` |
| Inventaris/dry-run dan job CLI kedaluwarsa, tanpa route publik | `scripts/expire-unpaid-orders.php` |
| UPDATE kedaluwarsa bersama untuk job; tidak dipanggil GET katalog/riwayat | `app/order-expiration.php` |
| Galeri foto kiriman (kandidat dari perubahan masuk) dan backfill foto lama | `database/migrations/016_multiple_dispatch_photos.sql`, API/detail Admin dan pelanggan |
| Default semua produk 50 porsi saat migrasi dan default produk baru | `database/migrations/017_default_quota_50.sql`, `public/admin/api/products.php` |
| Constraint dibatalkan dan VIEW pelepas kuota | `database/migrations/016_order_cancellation.sql`, `database/schema.sql` |
| Kuota checkout/katalog setara VIEW | `app/daily-quota.php` |
| Filter/count batal Admin/Owner, foto kiriman bersyarat status | `public/admin/api/orders.php`, `public/owner/api/orders.php` |
| Uji PostgreSQL terisolasi, migrasi, endpoint, batas waktu dan balapan | `scripts/test-order-cancellation.php`, `scripts/test-daily-quota.php` |
| Uji izin tombol/form dan pesan refund pelanggan | `scripts/test-order-cancellation-ui.cjs` |
| Aturan dan urutan aktivasi job setiap menit | `docs/aturan-bisnis.md`, `README.md` |

Manual menerima menunggu_pembayaran/menunggu_validasi/diproses sampai akhir H-2
WIB; refund penuh ditangani Admin di luar aplikasi. Kontak WhatsApp menyusul.
Job hanya membatalkan unpaid tanpa bukti sejak satu jam created_at; inventaris
dan dry-run wajib sebelum aktivasi pesanan lama. Implementasi tidak membaca .env
atau mengaktifkan job/migrasi pada database aplikasi.

## Foto bukti pesanan Admin

- `public/admin/api/orders.php`: upload foto kiriman dan transisi status selesai.
- `public/admin/assets/admin.js`, fungsi `orderDetail()`: foto terlihat saat
  diproses maupun selesai; upload hanya saat diproses.
- `scripts/test-quota-ui.cjs`: uji rendering foto dan pembatasan form upload.

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
