# Peta Kode

## Ikon komponen dan website uji

- Sumber 46 SVG transparan dan stylesheet CSS mask: `public/assets/component/*.svg`, `public/assets/component/icons.css`. Kelas `ui-icon icon-<nama>` memakai warna `currentColor`.
- Layout Pelanggan, Admin, dan Owner memuat stylesheet tersebut. Navigasi dan dialog ada di masing-masing `partials/layout.php`; pemilih tanggal bersama ada di `public/assets/js/date-picker.js`.
- Kontrol keranjang/status Pelanggan ada di `public/pelanggan/assets/customer.js`; ringkasan kapasitas dan status Admin ada di `public/admin/dashboard.php`, `orders.php`, dan `assets/admin.js`; filter/status Owner ada di `public/owner/dashboard.php`, `orders.php`, dan `products.php`.
- Halaman uji statis di `public/assets/component/preview.html`; katalog, preview, dan laporan uji ada di `docs/04-implementasi/`.


## Identitas, checkout, dan background Pelanggan

- `public/login.php` dan `public/register.php` memakai gambar `.auth-logo` dari
  `public/assets/img/logo-icon.png`; posisi dasar kanan atas dan override logo
  login/register desktop yang besar di tengah hero berada di `public/assets/css/app.css`.
  Referensi ada di `public/assets/img/desain/`.
- Pattern animasi login/register memakai `.drift` dengan tile 402 x 260 px;
  `@keyframes drift` menggeser lapisan dengan `transform` sejauh satu tile.
- `public/owner/partials/layout.php` menyimpan flag autentikasi untuk memilih prefix
  aset `assets/` pada halaman auth public root, `../assets/` pada halaman Owner.
- `public/pelanggan/partials/layout.php` menempatkan `.catalog-policy` sebelum
  `#checkout-date`. Teks merek UI Pelanggan/shared splash memakai `se.noesa`.
- `public/pelanggan/assets/customer.css` menempatkan pola 6% pada
  `.customer-main::after`, di atas latar mobile dan di bawah `.customer-content`.
  Aset public `public/assets/img/Pattern_Se.Noesa_Background.png` disalin dari
  `foto/Pattern_Se.Noesa_Background.png`; dekorasi tidak menerima pointer events.
- `scripts/test-customer-revisions-browser.cjs` memeriksa login/register responsif,
  identitas, urutan info, background, dan alur pelanggan dengan API fixture.

Splash awal `public/index.php` memakai `public/assets/img/logo-icon.png`; ukuran responsif dan animasi `.logo-pop` berada di `public/assets/css/app.css`.

Logo sidebar Pelanggan memakai `public/assets/img/logo-icon.png`, salinan `foto/Icon_Logo.png`, melalui layout bersama dan tautan ke katalog.

Header sticky semua halaman Pelanggan berada pada `.customer-top` di `public/pelanggan/assets/customer.css`, dengan override mobile di `catalog-mobile.css`. Tombol `.catalog-search-shortcut` di `public/pelanggan/index.php` diamati oleh `IntersectionObserver` dalam `catalogPage()` (`customer.js`); tombol menggulir dan memfokuskan input pencarian asli. Animasi sidebar memakai transform/opacity dan visibility tertunda pada `catalog-mobile.css`; animasi pembukaan kalender semua role ada di `public/assets/css/date-picker.css`. Keduanya menghormati reduced motion.

Sidebar Pelanggan berada di `public/pelanggan/partials/layout.php`, dengan tombol Keluar di bagian bawah. `customer.js` mengatur hamburger, Escape, fokus keyboard, dan latar penutup. Gaya mobile berada di `assets/catalog-mobile.css` dan gaya tombol Keluar di `assets/customer.css`.

Panel tanggal katalog Pelanggan ada di `public/pelanggan/index.php`; `assets/customer.js` hanya menampilkan pesan libur atau kuota bermasalah. Gaya panel ada di `assets/customer.css` dan kalender bersama di `public/assets/css/date-picker.css`.
Filter kategori katalog (Semua, Makanan, Minuman, Snack) berada di `public/pelanggan/index.php`; lebar pill, grid, dan kartu produk mobile diatur oleh `assets/catalog-mobile.css`.

Katalog menampilkan semua produk termasuk yang kuotanya nol lewat `public/pelanggan/api/catalog.php`. Kartu, detail menu, penanda stok habis, dan dialog Keluar dirender oleh `public/pelanggan/assets/customer.js`; CSS desktop di `assets/customer.css` dan mobile di `assets/catalog-mobile.css`.
Label `Stok habis` menjadi anak langsung `.menu-card` agar berada di pojok kanan atas kartu pada kedua ukuran layar; gaya pita abu berada di `assets/customer.css`.
Uji jarak kartu, label stok habis, tombol nonaktif, dan overflow pada beberapa lebar browser ada di `scripts/test-catalog-card-layout.cjs`.

## Jumlah porsi katalog dan keranjang Pelanggan

- `public/pelanggan/assets/customer.js` mengelola indikator sisa porsi, perubahan satu porsi, penghapusan menu, dan jumlah pada payload checkout. `showRemainingStock(product, remaining)` mengatur angka sisa hanya di bawah 50% kuota efektif; wrapper `#detail-stock` diperbarui saat jumlah berubah dan aturan hidden berada di `customer.css`.
- `scripts/test-customer-cart-quantity.cjs` menguji ambang sisa porsi, batas jumlah, total, penghapusan, dan payload checkout.
- `scripts/test-quota-ui.cjs` juga memeriksa angka sisa kartu pada batas 50% dan kuota efektif berbeda.

## Error pembayaran Pelanggan

- `public/pelanggan/assets/customer.js`: `api()` meneruskan status HTTP dan hasil respons tidak valid; `openHistoryDetail(id)` menangani kegagalan detail/metode/QRIS, aksi muat ulang, dan submit bukti. Pesan tampil lokal dalam dialog pembayaran, tidak masuk halaman Notifikasi. POST yang berhasil dipisahkan dari refresh yang gagal; hasil belum pasti harus diperiksa melalui muat ulang tanpa auto-retry upload.
- `public/pelanggan/assets/customer.css`: `.payment-load-error` memberi ruang untuk tombol tutup dialog.
- `scripts/test-customer-payment-errors.cjs`: regresi pemuatan detail/metode/QRIS, validasi/konflik/sesi/server/jaringan/respons upload, submit ganda, dan upload sukses dengan refresh gagal.
- `scripts/test-order-cancellation-ui.cjs`: izin upload/pembatalan, tenggat, dan detail foto/tawaran jadwal tetap diuji.

## Navigasi kembali Admin

| Kebutuhan | File |
|---|---|
| URL asal semua tautan detail Dashboard/daftar dan redirect pesanan offline | `public/admin/assets/admin.js`, `orderDetailUrl()` |
| Simpan/pulihkan tab, tinjauan, pencarian, dan tanggal pada URL daftar | `public/admin/assets/admin.js`, `ordersPage()`, `syncOrderUrl()` |
| Validasi tujuan kembali dan href tanpa JavaScript | `public/admin/order-detail.php` |
| Klik kembali biasa memakai replace; klik tab baru tetap native | `public/admin/assets/admin.js`, `bindOrderBack()` |
| Uji konteks asal, debounce, click modifiers, dan validator PHP tanpa DB | `scripts/test-admin-back-navigation.cjs` |

Rencana dan progress: `docs/rencana-navigasi-kembali-admin.md`.

## Kalender libur operasional

| Kebutuhan | File |
|---|---|
| UI Admin tambah/edit/hapus rentang libur inklusif | `public/admin/schedules.php`, `public/admin/assets/admin.js` |
| CRUD tanggal libur role Admin | `public/admin/api/holidays.php` |
| Daftar pesanan aktif terdampak libur dan tautan detail | `public/admin/api/holidays.php`, `public/admin/schedules.php`, `public/admin/assets/admin.js` |
| Uji form tambah/edit dan validasi API libur tanpa database aplikasi | `scripts/test-holidays.cjs` |
| Overlay kuota efektif semua produk menjadi 0 | `app/daily-quota.php`, `database/migrations/019_operational_holidays.sql` |
| Peringatan tanggal libur dan blokir checkout pelanggan | `public/pelanggan/api/catalog.php`, `public/pelanggan/api/orders.php`, `public/pelanggan/assets/customer.js` |
| Rekap porsi per menu pada tanggal pilihan, dengan tautan detail pesanan | `database/migrations/020_daily_production_report.sql`, `public/admin/dashboard.php`, `public/admin/api/dashboard.php`, `public/admin/assets/admin.js`, `public/admin/assets/admin.css` |
| Uji Dashboard saat rekap hilang/kosong/berisi/tidak valid dan error API | `scripts/test-admin-dashboard.cjs` |

Operasional selalu buka secara default. Kuota tersimpan tidak diubah saat
rentang libur dibuat. Pesanan lama tidak berubah otomatis; Admin menangani
pesanan terdampak satu per satu lewat tawaran jadwal pengganti di bawah.
`jadwal_po` dipertahankan sebagai referensi pesanan; UI Jadwal tidak lagi
mengelola periode PO.

## Tawaran jadwal pengganti

| Kebutuhan | File |
|---|---|
| Migrasi tabel tawaran satu baris per pesanan | `database/migrations/022_order_reschedule_offer.sql`, `database/schema.sql` |
| Admin membuat/memperbarui tawaran dan menampilkan statusnya | `public/admin/api/orders.php`, `public/admin/order-detail.php`, `public/admin/assets/admin.js`, `public/admin/assets/admin.css` |
| Pelanggan menerima/menolak tawaran dan melihat riwayatnya | `public/pelanggan/api/orders.php`, `public/pelanggan/assets/customer.js` |
| Validasi kuota tujuan saat tawaran diterima | `app/daily-quota.php`, `public/pelanggan/api/orders.php` |
| Tutup tawaran unpaid saat tenggat pembayaran | `app/order-expiration.php`, `scripts/expire-unpaid-orders.php` |

## Pencarian pembayaran terlambat

| Kebutuhan | File |
|---|---|
| Cari pesanan batal yang belum dicatat, sebelum batas 100 hasil | `public/admin/late-payments.php`, `public/admin/assets/late-payments.js`, `public/admin/api/late-payments.php` |
| Cari rekonsiliasi tercatat dan calon pesanan belum dicatat di tab Pesanan | `public/admin/orders.php`, `public/admin/assets/admin.js`, `public/admin/api/late-payments.php`, `app/late-payment.php` |
| Uji parameter pencarian, query, dan pemuatan pilihan | `scripts/test-admin-late-payment-search.cjs` |

## Pembatalan pesanan dan kedaluwarsa pembayaran

| Kebutuhan | File |
|---|---|
| Admin mencatat pesanan offline langsung berstatus diproses dan memakai kuota tanggal kirim | `public/admin/orders.php`, `public/admin/assets/admin.js`, `public/admin/api/orders.php`, `app/offline-orders.php`, `database/migrations/019_offline_orders.sql` |
| Alasan penolakan Admin tersimpan dan terlihat pada detail pelanggan/Admin | `public/admin/api/orders.php`, `public/admin/assets/admin.js`, `public/pelanggan/api/orders.php`, `public/pelanggan/assets/customer.js`, `database/migrations/020_order_rejection_reason.sql` |
| Cancel milik sendiri sampai H-2, upload sebelum satu jam, izin/waktu detail | `public/pelanggan/api/orders.php` |
| Tampilan pembatalan lama (tombol merah/kartu syarat/pesan H-2), izin server dan refund | `public/pelanggan/assets/customer.js` |
| Inventaris/dry-run dan job CLI kedaluwarsa, tanpa route publik | `scripts/expire-unpaid-orders.php` |
| UPDATE kedaluwarsa bersama untuk job; tidak dipanggil GET katalog/riwayat | `app/order-expiration.php` |
| Galeri foto kiriman (kandidat dari perubahan masuk) dan backfill foto lama | `database/migrations/016_multiple_dispatch_photos.sql`, API/detail Admin dan pelanggan |
| Default semua produk 50 porsi saat migrasi dan default produk baru | `database/migrations/017_default_quota_50.sql`, `public/admin/api/products.php` |
| Constraint dibatalkan dan VIEW pelepas kuota | `database/migrations/016_order_cancellation.sql`, `database/schema.sql` |
| Kuota checkout/katalog setara VIEW | `app/daily-quota.php` |
| Sumber/status asal pembatalan, waktu/ID peninjau, dan VIEW notifikasi | `database/migrations/021_cancellation_admin_review.sql` |
| Normalisasi metadata pembatalan dan tanggal ISO WIB lintas API | `app/order-cancellation.php`, `cancellation_metadata()` |
| Tinjauan Admin atomik/idempotent, filter belum/sudah ditinjau | `public/admin/api/orders.php`, action `review_cancellation` |
| Pemberitahuan belum ditinjau seluruh umur dan maksimal dua tautan prioritas di bawah rekap produksi | `public/admin/api/dashboard.php`, `public/admin/dashboard.php`, `public/admin/assets/admin.js` |
| Tab/count Dibatalkan, ringkasan detail terstruktur, bukti/foto read-only, tombol Sudah ditinjau | `public/admin/orders.php`, `public/admin/order-detail.php`, `public/admin/assets/admin.js`, `public/admin/assets/admin.css` |
| Hierarki antrean Dashboard, alasan prioritas, dan penjelasan tinjauan/refund | `public/admin/dashboard.php`, `public/admin/assets/admin.js`, `public/admin/assets/admin.css` |
| Filter/count batal Admin/Owner, foto kiriman bersyarat status | `public/admin/api/orders.php`, `public/owner/api/orders.php` |
| Uji PostgreSQL terisolasi, migrasi, endpoint, batas waktu dan balapan | `scripts/test-order-cancellation.php`, `scripts/test-daily-quota.php` |
| Uji concurrency pembuatan notifikasi dengan dua koneksi PostgreSQL independen | `scripts/test-menu-notification-concurrency.php` |
| Uji metadata/tinjauan pertama, notifikasi, balapan tinjauan dan fixture job | `scripts/test-order-cancellation.php`, `scripts/test-payment-deadline.php` |
| Uji tab/count/filter tinjauan dan tautan daftar Admin desktop/mobile | `scripts/test-admin-cancellation-list.cjs` |
| Uji izin tombol/form dan pesan refund pelanggan | `scripts/test-order-cancellation-ui.cjs` |
| Aturan dan urutan aktivasi job setiap menit | `docs/01-spesifikasi/aturan-bisnis.md`, `README.md` |

Manual menerima menunggu_pembayaran/menunggu_validasi/diproses sampai akhir H-2
WIB; refund penuh ditangani Admin di luar aplikasi. Kontak WhatsApp menyusul.
Job hanya membatalkan unpaid tanpa bukti sejak satu jam created_at; inventaris
dan dry-run wajib sebelum aktivasi pesanan lama. Migrasi tinjauan 021 diterapkan
terpisah; job kedaluwarsa tetap belum diaktifkan.

Tinjauan pembatalan berlaku untuk seluruh Admin. Waktu/peninjau pertama disimpan
tanpa mengganti waktu pembatalan; menandai sudah ditinjau bukan konfirmasi refund.
Pemberitahuan bertahan sampai ditinjau tanpa batas tujuh hari, termasuk data lama
yang sumber/status sebelumnya belum tercatat. GET hanya membaca data.

## Foto bukti pesanan Admin

- `public/admin/api/orders.php`: upload foto kiriman dan transisi status selesai.
- `public/admin/assets/admin.js`, fungsi `orderDetail()`: foto terlihat saat
  diproses maupun selesai; upload hanya saat diproses.
- `scripts/test-quota-ui.cjs`: uji rendering foto dan pembatasan form upload.

## Pemilih tanggal bersama

| Kebutuhan | File |
|---|---|
| Kalender dialog, status/titik warna tanggal, pencarian tanggal tersedia terdekat, navigasi keyboard, dan batas tanggal input | `public/assets/js/date-picker.js` |
| Tampilan kartu pengiriman, kalender sage, titik status, dan legend | `public/assets/css/date-picker.css` |
| Grid desktop Owner dan lebar ringkasan kuota | `public/assets/css/app.css` |
| Kartu tanggal pengiriman pelanggan | `public/pelanggan/index.php` |
| Pemuatan aset bersama dan filter tanggal Admin/Owner | `public/pelanggan/partials/layout.php`, `public/admin/partials/layout.php`, `public/owner/partials/layout.php` |
| Sinkronisasi tanggal katalog saat checkout berubah | `public/pelanggan/assets/customer.js` |
| Uji status kapasitas dan kalender browser dengan API fixture | `scripts/test-calendar-status.php`, `scripts/test-calendar-availability-ui.cjs`, `scripts/test-date-picker.cjs` |
| Minimum pesanan 4 porsi, validasi produk Admin, dan migration database | `app/daily-quota.php`, `public/pelanggan/assets/customer.js`, `public/admin/products.php`, `public/admin/api/products.php`, `database/migrations/026_minimum_order_4.sql` |

`app/daily-quota.php` adalah sumber kapasitas dapur bersama: floor 80% total
kuota efektif produk pada tanggal. Kapasitas/sisa ini dipakai checkout, laporan
Admin/Owner, pesanan offline, katalog, dan status tanggal.

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
| Sembunyikan argumen fungsi dari stack trace | `app/bootstrap.php`, `zend.exception_ignore_args` |
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

## Notifikasi perubahan menu (Stage 2–6)

| Kebutuhan | File |
|---|---|
| Tabel notifikasi, opsi, target, snapshot, status respons | `database/migrations/021_menu_change_notifications.sql` |
| Audit total pesanan dan tindak lanjut manual | `database/migrations/022_menu_change_confirmation_audit.sql` |
| Audit closure dan penutupan otomatis | `database/migrations/023_menu_change_closure_audit.sql` |
| Audit harga katalog saat konfirmasi penggantian | `database/migrations/024_menu_change_price_audit.sql` |
| Kebijakan harga snapshot, ringkasan pembayaran, dan penyelesaian selisih | `app/menu-notifications.php` |
| Lock tabel kanonik produk → kuota_harian → tanggal_libur_operasional | `app/daily-quota.php` |
| Skema baseline dan dokumentasi database | `database/schema.sql`, `docs/01-spesifikasi/database.md` |
| Hitung target dan validasi sisa kuota/minimum porsi | `app/menu-notifications.php` |
| API riwayat, preview, detail, pembuatan, persetujuan, dan tindak lanjut | `public/admin/api/menu-notifications.php` |
| API pelanggan: riwayat, detail, tandai dibaca, kirim respons | `public/pelanggan/api/menu-notifications.php` |
| Uji migrasi idempoten, FK, unique, target, kepemilikan, dan status | `scripts/test-menu-change-notifications.php` |
| Uji kontrak UI Admin/Owner/pelanggan tanpa browser | `scripts/test-menu-notifications-ui.cjs` |
| UI Admin: buat, preview, konfirmasi penggantian, selisih, tindak lanjut | `public/admin/menu-notifications.php` |
| UI Owner: riwayat, pelanggan terdampak, respons (hanya baca) | `public/owner/menu-notifications.php` |
| UI pelanggan: daftar, detail, pilih tindakan, riwayat respons | `public/pelanggan/menu-notifications.php` |
| Tautan notifikasi pada detail pesanan pelanggan | `public/pelanggan/api/orders.php`, `public/pelanggan/assets/customer.js` |
| Komponen UI bersama Admin/Owner | `public/assets/js/menu-notifications.js`, `public/assets/css/menu-notifications.css` |
| Komponen UI pelanggan dan indikator belum dibaca | `public/pelanggan/assets/menu-notifications.js`, `public/pelanggan/assets/menu-notifications.css` |
| Navigasi halaman | `public/admin/partials/layout.php`, `public/owner/partials/layout.php`, `public/pelanggan/partials/layout.php` |

Status pesanan aktif: `menunggu_pembayaran`, `menunggu_validasi`, `diproses`.
Target selalu dihitung dari `detail_pesanan`; ID target dari browser tidak dipakai.
Pesanan offline tetap ditautkan untuk tindak lanjut manual, tanpa notifikasi in-app.
API tidak mengubah detail pesanan, status pembayaran, atau menjalankan
penggantian/refund tanpa persetujuan. Aksi konfirmasi Admin mengubah detail dan
total hanya setelah respons pelanggan; status pesanan/pembayaran tetap. Tagihan
dan pengembalian manual tidak dilakukan oleh aplikasi. Harga penggantian dikunci
saat pelanggan memilih; perbedaan harga katalog saat konfirmasi hanya diaudit
pada `harga_katalog_saat_konfirmasi`/`perbedaan_harga_katalog`. Detail respons
memuat `info_harga` dan `pembayaran` (status pembayaran, bukti, nominal,
total lama/baru, dan status penyelesaian selisih) agar Admin dapat membedakan
pelanggan yang sudah dan belum membayar tanpa status pembayaran baru.

UI Admin memakai pola `admin_head`/`admin_foot`, sedangkan Owner memakai
`head`/`topbar`/`nav`/`foot`. Keduanya memuat satu skrip bersama dengan mode
`admin`/`owner`; Owner tidak menampilkan form pembuatan dan tidak dapat POST.
Dialog detail menampilkan snapshot dan respons. Persetujuan memperbarui item
pesanan serta total tanpa mengubah status pesanan/pembayaran. Tagihan/refund dan
kontak pelanggan tetap dilakukan manual; Admin mencatat hasilnya.

UI pelanggan memakai `customer_head`/`customer_foot` dan hanya menampilkan baris
penerima milik `user_id` sesi dengan pesanan online. Detail riwayat pesanan
menautkan ke notifikasi terkait. Membuka detail notifikasi menandai sebagai
dibaca melalui POST terpisah. Pelanggan dapat memilih menu pengganti, menghubungi
Admin, atau menunggu informasi selama status respons masih `menunggu_respons`
dan pesanan aktif.
