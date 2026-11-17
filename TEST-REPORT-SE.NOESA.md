# Test Report Implementasi Se.Noesa

> Catatan hasil test per task. Test yang tidak dijalankan dicantumkan beserta alasannya, tidak dinyatakan lulus.

## Lingkungan test
- PHP 8.5.10 CLI (C:\php\php.exe) — tersedia
- Node v24.21.0 — tersedia
- Composer 2.11.1 — tersedia
- PDO driver: pgsql — tersedia
- Database: Supabase remote `aws-0-ap-southeast-1.pooler.supabase.com` / `postgres` — **dapat dijangkau (read-only SELECT-only dipakai)**

## Keterbatasan test (berlaku lintas task)
1. **Dilarang menyentuh production.** Session DB adalah Supabase remote; tidak ada konfirmasi bahwa ini lingkungan dev. Maka:
   - Tidak ada `INSERT`, `UPDATE`, `DELETE`, DDL, atau migration yang dijalankan agent.
   - Test yang memerlukan mutasi data (checkout, upload, verifikasi, kuota, dll.) **tidak dijalankan** dan ditandai belum diuji.
   - Hanya query `SELECT` untuk verifikasi struktur/konsistensi data yang diizinkan.
2. Tidak ada framework browser automation (Playwright/Cypress) yang terpasang/konfigurasi. Test UI berupa inspeksi kode statis + `node --check` + PHP lint, dan diklasifikasikan sebagai **verifikasi statis**, bukan test fungsional browser.
3. Tidak ada data pelanggan nyata yang dipakai; tidak ada fixture lokal dibuat tanpa izin.

## Hasil per task

### TASK-00 — Rekonsiliasi audit
- **Jenis:** verifikasi dokumen (tanpa test aplikasi).
- **Perintah:** `node count_audit.js` (script di temp, bukan di repo) mem-parsing tabel bagian 3 dan daftar shorthand bagian 9.
- **Hasil aktual:**
  - Bagian 3: IMG 8 (2/3/3), CUS 29 (18/6/5), ADM 29 (15/7/7), OWN 21 (10/6/5), GEN 2 (2/0/0) → total 89 ID; DONE 47, PARTIAL 22, NOT IMPLEMENTED 20.
  - Duplikat ID: 0. ID di bagian 3 tanpa pasangan di bagian 9: 0. ID di bagian 9 tanpa pasangan di bagian 3: 0. Status bertentangan: 0.
  - Ringkasan bagian 9 sebelum koreksi: 38/18/22 → tidak cocok dengan daftar (47/22/20).
- **Verifikasi ulang setelah perbaikan:** angka klaim bagian 9 = angka enumerasi = 47/22/20; total 89 unik; `git diff --check` tanpa error (hanya warning konversi LF→CRLF).
- **Kesimpulan:** LULUS. Acceptance criteria TASK-00 terpenuhi; tidak ada kode aplikasi yang berubah.

### TASK-01 — Keputusan bisnis
- **Jenis:** verifikasi dokumen.
- **Perintah:** `node check_decisions.js` (script di temp) mem-parse heading, kolom, dan status.
- **Hasil aktual:** 14 heading `#1`–`#14` berurutan; 14 `Keputusan final` semuanya masih template kosong; 14 status `PENDING`; 14 konteks, 14 opsi, 14 dampak, 14 task terdampak.
- **Kesimpulan:** LULUS. Tidak ada keputusan yang dijawab oleh agent. Tidak ada test aplikasi yang relevan untuk task dokumentasi ini.

### TASK-02 — Sinkronisasi Owner/Admin
- **Jenis:** test API read-only (parse output endpoint via harness CLI, tanpa server HTTP) + verifikasi statis frontend + lint.
- **Test 1 —Authorization (POST):**
  - `role=owner, POST` → `{"error":"Unauthorized"}` ✅
  - `role=customer, POST` → `{"error":"Unauthorized"}` ✅
  - `role=owner, GET` → payload JSON berisi `items`, `counts`, `capacity` ✅
  - Catatan: warning pada harness (session sudah aktif) berasal dari script test, bukan aplikasi; POST ditolak sebelum query DB.
- **Test 2 — Kesetaraan data Owner vs Admin (output byte-identik):**
  - `tab=semua` ✅, `tab=validasi` ✅, `tab=dibatalkan` ✅, `tab=ditolak` ✅, `tab=selesai` ✅, `tab=pembayaran` ✅, `tab=proses` ✅, `tab=semua&q=SN-` ✅, `tab=semua&q=__tidakada__` ✅, `id=27` ✅, `id=999999` ✅ → 11/11 identik.
  - Data kosong dan detail tidak ditemukan juga identik (bukan hanya data terisi).
- **Test 3 — Pagination:** tidak diimplementasikan di kedua endpoint; seluruh baris dikembalikan. Skenario "banyak halaman" tidak relevan ⇒ dicatat, bukan diklaim lulus.
- **Test 4 — Frontend:** tidak ada lagi selector mutasi (`new-offline-order`, `offline-order-dialog`, `detail-actions`, `reject-order-dialog`, `data-order-action`, `dispatch-photo-form`) pada halaman Owner (verifikasi `Select-String` → 0 kecocokan).
- **Lint:** `node --check public/assets/js/app.js` OK; `php -l` untuk 5 file terkait OK; `git diff --check` bersih.
- **Belum diuji:** interaksi browser desktop/mobile (tidak ada browser automation), klik tombol secara nyata, dan mutasi DB (dilarang oleh batasan lingkungan).
- **Kesimpulan:** LULUS untuk acceptance criteria yang dapat diuji di lingkungan ini. Tidak ada data pelanggan yang dicantumkan dalam laporan ini.

### TASK-04 — Alur pembayaran end-to-end
- **Jenis:** audit kode + query agregat read-only (`SELECT`), tanpa mutasi DB.
- **Perintah:** `php snoesa_payment_audit.php`, `php snoesa_quota_view_check.php` (script di temp).
- **Hasil agregat DB (tanpa data pribadi):**
  - Status: dibatalkan 18, diproses 9, ditolak 9, menunggu_pembayaran 6, menunggu_validasi 3, selesai 19 (total 64).
  - `menunggu_pembayaran` dengan bukti: 0 (konsisten dengan status awal).
  - `menunggu_pembayaran` tanpa bukti & lewat 1 jam: 6 (menunggu penjadwal expire).
  - `menunggu_validasi` tanpa bukti: 0 (sesuai guard verifikasi Admin).
  - `ditolak` tanpa alasan: 4 (data historis).
  - Metode pembayaran aktif: 1.
  - Selisih `v_kuota_harian.terpakai` vs agregat status aktif: **0 baris** (data saat ini konsisten).
- **Matrix:** 14 skenario di `docs/TEST-MATRIX-PEMBAYARAN.md`.
- **Belum dijalankan (dan alasannya):** semua skenario mutasi (checkout, upload/ganti bukti, verifikasi, penolakan, pembatalan, expire) karena dilarang menulis ke DB remote; render browser tidak diuji karena tidak ada browser automation dan dilarang memakai data pelanggan nyata untuk mutasi.
- **Temuan:** BUG-001 (latent), OBS-001, OBS-002, OBS-003 — tercatat di `PROGRESS-IMPLEMENTASI-SE.NOESA.md` dan matrix. Tidak ada perbaikan kode yang dilakukan di task ini.
- **Kesimpulan:** LULUS sebagai audit + test matrix. Tidak ada klaim fungsional browser/mutasi.

### TASK-05 — Minimum pemesanan
- **Jenis:** unit test terhadap handler nyata yang diekstrak dari `customer.js` + query DB read-only + lint.
- **Test handler keranjang (`node test_min_cart.js`, script di temp):** 5/5 PASS
  - min 4, cart 6 → 4 (toast minimum) ✅
  - min 4, cart 5 → 4 (toast minimum) ✅
  - min 4, cart 4 → item dihapus ✅
  - min 4, cart 9 → 5 ✅
  - min 6, cart 7 → 6 (toast minimum) ✅
  - Semua hasil akhir `>= min_pesan`.
- **Konsistensi sumber nilai (read-only):**
  - constraint DB: `CHECK ((min_pesan >= 4))`; nilai aktual produk min 4–10 (11 produk).
  - snapshot kuota: nilai minimum unik 4 dan 10 ⇒ frontend menerima nilai yang sama dengan backend.
- **Lint:** `node --check customer.js` OK; `php -l admin/api/products.php` OK; `git diff --check` bersih.
- **Belum diuji:** klik sungguhan di browser, checkout dengan quantity hasil clamp (mutasi dilarang), form Admin disubmit (mutasi dilarang).
- **Kesimpulan:** LULUS untuk acceptance criteria yang dapat diuji; aturan minimum tidak diubah.

### TASK-06 — Upload foto produk Admin
- **Jenis:** uji fungsional HTTP terhadap endpoint asli dengan router sementara (session admin + CSRF diset), hanya mengirim skenario **gagal** sehingga tidak ada penulisan file/DB.
- **Hasil (5/5):**
  - `fake.txt` (text/plain) → HTTP 422 `Format gambar harus JPG, PNG, atau WebP` ✅
  - file 3 MB → HTTP 422 `Ukuran gambar melewati batas upload_max_filesize PHP (2M).` ✅
  - nama file `../../traversal.jpg` dengan konten bukan gambar → HTTP 422 format ditolak ✅ (nama file tidak dipakai untuk path; path dibuat dari hex acak + ekstensi MIME)
  - field wajib kosong → HTTP 422 `Semua kolom wajib diisi` ✅
  - `min_pesan=2` → HTTP 422 `Minimum pemesanan minimal 4 porsi` ✅
- **Verifikasi impacts:** `git status` bersih, tidak ada file baru di `public/uploads/products/` (hanya 14 file baseline + `.gitkeep`), server uji dimatikan.
- **Audit statis:** validasi MIME/ukuran di backend ✅; path traversal dicegah oleh nama file generated ✅; empty state/fallback gambar ada di customer/admin/owner ✅; pengecekan `is_writable` dan kegagalan `move_uploaded_file` ditangani ✅.
- **Belum dijalankan:** upload valid (JPG/PNG/WebP ≤2 MB) → akan menulis file dan UPDATE/INSERT DB, dilarang; kegagalan storage/permission sungguhan; render browser.
- **Temuan:** OBS-004 (file orphan bila DB gagal setelah file dipindahkan) — dicatat, tidak diubah.
- **Kesimpulan:** LULUS untuk skenario yang diizinkan; tidak ada klaim upload valid.

### TASK-07 — Concurrency kuota
- **Jenis:** audit statis + test TEMP-table (salinan `scripts/test-daily-quota.php` tanpa lock produksi).
- **Hasil:** 44 PASS, 0 FAIL. Meliputi indeks, default/override, batas 80%, sisa kuota, pemulihan kuota saat ditolak/dibatalkan, batas tepat diterima, minimum 4 porsi, timezone, UPSERT rentang, validasi tanggal, dan kesetaraan view laporan vs query checkout. Semua data uji di TEMP table dan di-rollback.
- **Tidak dijalankan:** script asli `scripts/test-daily-quota.php` (mengunci `kuota_harian` produksi) dan simulasi dua checkout konkuren dengan mutasi — keduanya dilarang karena database remote.
- **Analisis lock:** jalur yang menambah pemakaian (checkout, offline order, konfirmasi penggantian menu) memakai urutan lock kanonik yang sama dengan mode `SHARE ROW EXCLUSIVE` ⇒ saling serialize. Jalur yang melepas kuota tidak mengambil lock tersebut ⇒ tidak dapat menyebabkan oversold.
- **Koreksi:** BUG-001 (TASK-04) dinyatakan false alarm; migration `016_order_cancellation.sql:12` sudah mengecualikan `dibatalkan` dari view, dan query agregat DB aktual menghasilkan 0 selisih.
- **Kesimpulan:** LULUS sebagai audit + test terbatas. Tidak ada perubahan kode. Tidak ada klaim test konkuren dengan data nyata.

### TASK-11 — Status dan filter Admin
- **Jenis:** test API read-only (semua skenario GET) + verifikasi label statis + lint.
- **Hasil filter (9/9 sesuai):**
  - `tab=dibatalkan` → 18 item, semuanya `dibatalkan`; `review=unreviewed` → 18; `review=reviewed` → 0 ✅
  - `tab=ditolak` → 9 item, semuanya `ditolak` ✅
  - `tab=validasi` → 3 item `menunggu_validasi` ✅
  - `tab=pembayaran` → 6 item `menunggu_pembayaran` ✅
  - `tab=proses` → 9 item `diproses` ✅
  - `tab=selesai` → 19 item `selesai` ✅
  - `tab=semua` → 64 item, 6 status berbeda; `counts.total=64`, `cancelled=18`, `cancellation_unreviewed=18` ✅
- **Konsistensi label:** tidak ada lagi teks “Menunggu validasi” pada file UI Admin/Owner/menu-notifications; seluruhnya “Menunggu verifikasi”. Nilai status DB dan `data-tab` tidak diubah.
- **Lint:** 3 file JS lolos `node --check`; `php -l owner/orders.php` OK; `git diff --check` bersih.
- **Belum diuji:** render browser desktop/mobile; perubahan istilah status final (menunggu keputusan #12).
- **Kesimpulan:** LULUS. Tidak ada nilai status database yang diubah.

### TASK-12 — Auto-refresh Admin
- **Jenis:** audit statis alur aksi + lint.
- **Hasil audit:**
  - Libur: `loadHolidays()` dipanggil ulang setelah simpan/hapus + toast ✅
  - Produk: `loadProducts()` / `refreshProducts()` setelah simpan/hapus + toast ✅
  - Kuota harian: `loadKuotaHarian()` + `refreshProducts()` setelah simpan/reset/hapus + toast ✅
  - Status pesanan & unggah foto kiriman: reload halaman setelah toast sukses ✅
  - `api()` memakai `cache: "no-store"` → tidak ada data stale ✅
- **Perbaikan:** filter kategori/pencarian produk tidak lagi di-reset setelah simpan; verifikasi keberadaan produk memakai query tanpa filter.
- **Lint:** `node --check public/admin/assets/admin.js` OK; `git diff --check` bersih.
- **Belum diuji:** klik sungguhan di browser desktop/mobile (tidak ada browser automation).
- **Kesimpulan:** LULUS untuk audit + perbaikan yang dapat diverifikasi statis. Tidak ada klaim test interaksi browser.

### TASK-13 — Produk dinonaktifkan pada histori (BLOCKED)
- **Jenis:** audit skema + query read-only `information_schema`.
- **Hasil:**
  - Kolom `produk.aktif`: **tidak ada**. Kolom snapshot di `detail_pesanan`: **tidak ada** (0 baris).
  - FK aktual: `detail_pesanan.produk_id REFERENCES produk(id)` (tanpa `ON DELETE`), sehingga produk yang sudah dipesan tidak dapat dihapus ⇒ baris histori aman.
  - Query detail Admin/Owner (`admin/api/orders.php:229-234`) dan Pelanggan (`pelanggan/api/orders.php:305-308`) memakai `JOIN produk` dan menampilkan `produk.nama` saat ini.
- **Konsekuensi:** tidak ada data yang bisa dipakai untuk menampilkan “keterangan produk dinonaktifkan” tanpa perubahan skema/keputusan bisnis.
- **Status:** BLOCKED — `DECISION-NEW-01` menunggu persetujuan. Tidak ada kode/migration yang diubah.

### TASK-14 — Informasi kuota penuh Pelanggan
- **Jenis:** test API read-only + unit test potongan kode UI nyata + lint.
- **API `catalog.php` (5 skenario):**
  - 2026-11-30: `items=9`, `habis=2` (Salad Buah, Matcha Latte) ✅
  - 2026-11-30 + `kategori=minuman`: `habis` tetap 2 dan sesuai kategori ✅
  - 2026-11-25 (libur): `items=0`, `habis=11`, UI menyembunyikan pesan kuota penuh ✅
  - 2026-11-24 / 2026-12-01: `habis=0`, pesan disembunyikan ✅
- **UI (`node test_full_hint.js`, 3/3 PASS):** ada menu penuh → pesan tampil dengan tanggal “30 Nov 2026”; tidak ada menu penuh → tersembunyi; hari libur → tersembunyi.
- **Pencegahan checkout:** produk dengan `sisa=0` tidak masuk `items` katalog dan ditolak `daily_quota_validate()` di backend (audit TASK-07); tidak ada perubahan pada endpoint checkout.
- **Lint:** `php -l catalog.php` OK, `php -l index.php` OK, `node --check customer.js` OK.
- **Belum diuji:** render browser dan perubahan tanggal secara interaktif (tidak ada browser automation).
- **Kesimpulan:** LULUS untuk API + unit UI; pesan mengikuti tanggal dan filter.

### TASK-16 — Dialog Pelanggan
- **Jenis:** unit test terhadap potongan kode dialog asli dari `customer.js` + lint.
- **Hasil (`node test_dialog_backdrop.js`, 3/3 PASS):** `detail-dialog`, `cart-dialog`, dan `checkout-dialog` semuanya menutup saat klik backdrop (`e.target === dialog`) dan tidak menutup saat klik konten.
- **Lint:** `node --check customer.js` OK.
- **Belum diuji:** klik mouse/ Escape sungguhan di browser, fokus keyboard, dan scroll body (tidak ada browser automation); `scroll lock` tidak ditemukan di kode sehingga tidak ada state yang perlu dipulihkan.
- **Kesimpulan:** LULUS untuk perilaku backdrop yang dapat diuji secara unit. Tidak ada klaim test browser.

### TASK-17 — Label keranjang Pelanggan
- **Jenis:** verifikasi statis label + lint.
- **Hasil:** `index.php:56` memuat “ISI KERANJANG” dan tidak ada lagi “RINGKASAN KERANJANG”; `customer.js:260` memuat “Jumlah dipesan” dan “Sisa Porsi” (dua makna berbeda, keduanya dipertahankan); `layout.php:199-202` memuat 2 item `<li>` pada `.catalog-policy` tepat setelah input tanggal kirim.
- **Lint:** `php -l index.php` OK, `php -l partials/layout.php` OK, `node --check customer.js` OK, `git diff --check` bersih.
- **Belum diuji:** tampilan visual desktop/mobile (tidak ada browser automation).
- **Kesimpulan:** LULUS secara statis; tidak ada perubahan pada logika kuota/keranjang.

### TASK-18 — Navigasi/dashboard Pelanggan
- **Jenis:** unit test regression logout + verifikasi statis CSS.
- **Regression logout (`node test_logout_dialog.js`, 4/4 PASS):** dialog tampil; “Ya, Keluar” memanggil logout dan mengarahkan ke `../login.php`; “Batal”, klik backdrop, dan klik konten tidak memicu logout; dialog ditutup dan dihapus pada semua jalur.
- **CSS:** aturan FAB untuk `body[data-page="history"] .history-link` memakai `position: fixed`, `right/bottom` dengan `env(safe-area-inset-*)`, ukuran 54 px di mobile, dan padding tambahan pada `.customer-content`.
- **Belum diuji:** tampilan/browser sungguhan, jangkauan jari dan overlap konten pada berbagai viewport (tidak ada browser automation).
- **CUS-22:** tidak ada elemen panah pada menu dashboard Pelanggan di kode saat ini ⇒ tidak diubah dan dicatat.
- **Kesimpulan:** LULUS untuk regressi logout + perubahan CSS yang dapat diverifikasi statis. Tidak ada klaim test visual.

### TASK-20 — Dashboard & detail Owner (PARTIAL)
- **Jenis:** unit test potongan kode + lint + verifikasi statis.
- **Hasil (`node test_owner_quota_date.js`):** dengan tanggal → “Tanggal 30 Nov 2026 · Kapasitas dapur 440 porsi …”; tanpa tanggal → tetap “Kapasitas dapur 440 porsi …” tanpa prefiks tanggal. PASS.
- **Verifikasi statis:** 2 ikon dekoratif dihapus dari dashboard Owner; tombol kembali memakai `‹` sama seperti topbar; logout Owner memanggil `confirmDialog` (bukan native confirm). Owner tetap read-only (perubahan TASK-02 tidak diubah).
- **Lint:** `node --check app.js` OK; `php -l owner/dashboard.php` OK; `php -l owner/order-detail.php` OK; `git diff --check` bersih.
- **Belum dipenuhi / belum diuji:** metrik OWN-09/11 menunggu keputusan #13; white-space OWN-02/03/13/15 dan filter tanggal tren OWN-04 perlu QA visual; tidak ada browser automation.
- **Kesimpulan:** PARTIAL. Tidak ada klaim DONE untuk bagian yang menunggu keputusan/visual QA.

### TASK-21 — UI Admin
- **Jenis:** verifikasi statis + lint.
- **Hasil:**
  - Tidak ada lagi `.top-avatar` di layout Admin (ADM-25).
  - Nav mobile Memiliki `class="active"` sesuai `$page` untuk 5 entri (ADM-21).
  - Semua ikon sidebar/mobile/keluar/menu-toggle memakai SVG `stroke-width=2`, `20x20` (ADM-20); hanya satu glyph hamburger tersisa sebelum dikonversi, lalu dikonversi juga ⇒ 0 glyph tersisa.
  - Ikon search di `orders.php` dan `products.php` memakai SVG yang sama (ADM-23).
  - `.side-nav i:empty { display:none }` mencegah strip badge kosong (ADM-18).
  - Template baris order desktop/mobile memakai `order-user`/`mobile-order-user` untuk username dan `order-code` untuk kode (ADM-17).
- **Lint:** `php -l` 3 file Admin OK; `node --check admin.js` OK; `git diff --check` bersih.
- **Belum diuji:** render visual desktop/mobile dan klik navigasi (tidak ada browser automation).
- **Kesimpulan:** LULUS secara statis; tidak ada perubahan fungsi Admin.
