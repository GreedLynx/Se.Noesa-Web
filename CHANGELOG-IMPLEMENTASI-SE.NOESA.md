# Changelog Implementasi Se.Noesa

> Ringkasan perubahan per task. Format: satu entri per checkpoint commit.

## 2026-11-17

### baseline — f7ff4d3
- `docs: establish baseline by organizing implementation docs into docs/`
- Memindahkan `REVISI-LANJUTAN-SE.NOESA.md` dan `revisi-senoesa.md` ke `docs/` (rename 100%).
- Menambahkan `docs/AUDIT-REVISI-TAHAP-2.md`, `docs/PROMPT-EKSEKUSI-SE.NOESA.md`, `docs/TASKS-IMPLEMENTASI-SE.NOESA.md`.
- Dilakukan atas izin eksplisit user untuk membersihkan working tree sebelum eksekusi.

### TASK-00 — 9b7f8f5
- `docs: reconcile AUDIT-REVISI-TAHAP-2 revision status counts`
- Hitung ulang seluruh ID revisi: 89 ID unik — DONE 47, PARTIAL 22, NOT IMPLEMENTED 20.
- Koreksi angka ringkasan bagian 9 dari 38/18/22 menjadi 47/22/20.
- Hapus placeholder `IMG-CUS-? (none)` pada daftar PARTIAL.
- Tambah `docs/AUDIT-REVISI-TAHAP-2-CORRECTION.md` sebagai jejak perubahan.
- Tidak ada perubahan kode aplikasi, migration, atau konfigurasi.

### TASK-01 — 6be9681
- `docs(TASK-01): document 14 pending business decisions`
- Buat `KEPUTUSAN-BISNIS-SE.NOESA.md` berisi 14 keputusan dari task board dan revisi lanjutan.
- Setiap keputusan memiliki pertanyaan asli, konteks audit, opsi, kolom keputusan final yang dikosongkan, dampak, dan task terdampak.
- Semua keputusan berstatus `PENDING`; tidak ada jawaban yang diisi agent.
- Task yang bergantung (#3, #4, #5, #6, #8, #9, #10, #11, #12, #13, #14) tetap BLOCKED.

### TASK-02 — 2ba8f69
- `task(TASK-02): verify owner order synchronization and enforce read-only UI`
- Audit: `public/owner/api/orders.php` hanya `require` `public/admin/api/orders.php`, sehingga query/filter/detail/count identik oleh konstruksi. Tidak ada pagination di kedua endpoint (semua baris dikembalikan), sehingga tidak ada perbedaan pagination.
- Backend read-only sudah benar: role `owner`/`admin` diizinkan untuk GET; POST wajib `admin`.
- Temuan nyata: frontend Owner masih menampilkan kontrol mutasi Admin (`+ Pesanan offline`, dialog pesanan offline, tombol reject/validate/complete, form unggah foto kiriman) meski backend menolaknya.
- Perbaikan scope TASK-02:
  - `public/owner/orders.php`: hapus tombol dan dialog pesanan offline.
  - `public/owner/order-detail.php`: hapus `#detail-actions` dan dialog penolakan.
  - `public/owner/partials/layout.php`: tambah `data-role="owner"` pada `<body>`.
  - `public/assets/js/app.js`: pasang blok offline hanya bila kontrol tersedia; jangan render form unggah foto kirim dan jangan pasang handler aksi pada Owner; jaga `actions` null-safe.
- Tidak ada perubahan pada endpoint API, skema, atau migration.

### TASK-04 — 2bf0153
- `audit(TASK-04): map payment flow and record test matrix`
- Buat `docs/TEST-MATRIX-PEMBAYARAN.md` berisi 14 skenario sukses/gagal beserta bukti kode dan bukti agregat DB read-only.
- Verifikasi: status awal `menunggu_pembayaran`, tenggat 1 jam, upload/ganti bukti terikat `id` + `user_id`, verifikasi/penolakan Admin bersyarat, alasan penolakan tampil ke Pelanggan, kuota dilepas hanya untuk `ditolak`/`dibatalkan`.
- Tidak mengubah status, istilah, timeout, atau kebijakan refund (keputusan #1 dan #12 masih PENDING).
- Tidak ada kode aplikasi yang diubah. Temuan dicatat: BUG-001 (view kuota), OBS-001..003.
- Update `KEPUTUSAN-BISNIS-SE.NOESA.md` #1 dengan bukti teks refund di `customer.js:563`.

### TASK-05 — 626cb9f
- `fix(TASK-05): enforce per-product minimum in cart and clarify admin validation`
- Audit: sumber minimum konsisten di DB (`CHECK (min_pesan >= 4)`, default 4), `daily_quota_snapshot` (`GREATEST(4, min_pesan)`), `daily_quota_validate` (dipakai checkout & pesanan offline), form Admin (`min="4"`), dan API Admin (`>= 4`).
- Bug ditemukan: tombol kurang di keranjang Pelanggan `cart[id] -= min` bisa menghasilkan jumlah di bawah minimum (mis. 6 − 4 = 2 untuk minimum 4), sehingga checkout ditolak backend setelah UI mengizinkan state invalid.
- Perbaikan:
  - `public/pelanggan/assets/customer.js`: clamp hasil pengurangan ke `min`, tampilkan pesan “Minimum pesanan … adalah N porsi” bila clamp terjadi; hapus item hanya bila jumlah sudah sama dengan minimum.
  - `public/admin/api/products.php`: pisahkan validasi harga dan minimum; pesan error spesifik “Minimum pemesanan minimal 4 porsi”.
- Tidak mengubah angka/aturan minimum bisnis.
- Migration: tidak ada.

### TASK-06 — d76a988
- `test(TASK-06): verify admin product image upload validation`
- Audit `public/admin/api/products.php` (validasi error upload, `is_uploaded_file`, ≤2 MB, MIME via `getimagesize`, nama file generated dari hex acak + ekstensi MIME, folder `public/uploads/products`, transaksi DB terpisah).
- Uji fungsional 5 skenario gagal lewat server PHP lokal + router sementara (tanpa menyentuh DB/storage produksi):
  - format tidak didukung → 422, ukuran > batas → 422, nama file traversal → 422, field wajib kosong → 422, minimum < 4 → 422.
- Tidak ada perubahan kode aplikasi. Skenario upload valid tidak dijalankan karena akan menulis file dan database.
- Temuan: OBS-004 — file yang sudah dipindahkan dapat menjadi orphan bila UPDATE/INSERT DB gagal (tidak ada cleanup); tidak diubah di task ini.

### TASK-07 — 7a2572c
- `audit(TASK-07): verify quota checkout locking and rollback behavior`
- Buat `docs/AUDIT-CONCURRENCY-KUOTA.md` berisi peta jalur kuota, urutan lock kanonik, analisis isolation/rollback, dan koreksi BUG-001.
- Menjalankan salinan sementara `scripts/test-daily-quota.php` tanpa bagian lock produksi: 44 PASS, 0 FAIL; seluruh fixture berada di TEMP table dan di-rollback.
- Tidak menjalankan script asli karena bagian akhir mengunci `kuota_harian` pada database remote.
- Tidak ada perubahan kode (tidak ada race condition yang terbukti).
- Koreksi: BUG-001 dari TASK-04 dinyatakan false alarm karena migration 016 sudah memperbaiki definisi view.

### TASK-11 — 1371442
- `fix(TASK-11): align order status labels and verify status filters`
- Audit 6 nilai status DB (`menunggu_pembayaran`, `menunggu_validasi`, `diproses`, `selesai`, `ditolak`, `dibatalkan`) dan semua tab filter API/UI.
- Perbaikan label UI yang masih memakai “Menunggu validasi” menjadi “Menunggu verifikasi” (selaras dengan perubahan ADM-19 yang sudah berlaku):
  - `public/owner/orders.php` (tab status)
  - `public/assets/js/app.js` (badge status daftar & detail Owner)
  - `public/admin/assets/admin.js` (badge antrean prioritas & empty state)
  - `public/assets/js/menu-notifications.js` (label status notifikasi)
- Tidak ada nilai status database, action name, atau istilah final yang diubah (keputusan #12 masih PENDING).
- Migration: tidak ada.

### TASK-12 — e824f49
- `fix(TASK-12): preserve admin product filters after save`
- Audit seluruh aksi Admin: libur, produk, kuota harian, status pesanan, dan unggah foto kiriman.
- Temuan: sebagian besar aksi sudah memperbarui komponen secara lokal (`loadHolidays`, `loadProducts`, `loadKuotaHarian`, `refreshProducts`) dengan `toast` sukses/gagal; `api()` memakai `cache: "no-store"`.
- Bug ditemukan: setelah.simpan produk, kode menghapus filter kategori dan nilai pencarian sehingga pengguna kehilangan konteks (IMG-ADM-04 / ADM-28).
- Perbaikan `public/admin/assets/admin.js`: filter kategori/pencarian dipertahankan; verifikasi produk tersimpan dilakukan terhadap daftar produk tanpa filter, bukan daftar terfilter.
- Reload penuh pada detail pesanan dan unggah foto kiriman tetap dipertahankan karena memang perlu menyegarkan status/button; tidak ada state lama yang dibiarkan.
- Migration: tidak ada.

### TASK-13 — BLOCKED (dokumentasi audit) — 055d350
- `docs(TASK-13): record blocked product snapshot decision`
- Audit: `produk` tidak memiliki kolom `aktif`; `detail_pesanan` tidak memiliki kolom snapshot nama/detail (verifikasi `information_schema`: 0 kolom). FK `detail_pesanan.produk_id → produk.id` mencegah penghapusan produk yang sudah dipesan.
- Konsekuensi: baris histori tidak hilang, tetapi detail pesanan menampilkan `produk.nama` saat ini, sehingga penggantian nama produk mengubah tampilan pesanan lama.
- Implementasi keterangan “produk dinonaktifkan” memerlukan definisi bisnis + migration (kolom `aktif` dan/atau snapshot). Tidak ada keputusan yang tersedia ⇒ task ditandai BLOCKED, kode tidak diubah.
- Tambah `DECISION-NEW-01` pada `KEPUTUSAN-BISNIS-SE.NOESA.md` (keputusan baru yang ditemukan saat implementasi, bukan bagian dari 14 keputusan awal).

### TASK-14 — ae9a58b
- `feat(TASK-14): inform customers about full quota per delivery date`
- `public/pelanggan/api/catalog.php`: ekspos `habis` (produk dengan `sisa <= 0`) yang tetap mengikuti filter kategori/pencarian aktif; `items` tetap hanya berisi produk tersedia.
- `public/pelanggan/index.php`: tambah elemen `#quota-full-hint` di panel tanggal.
- `public/pelanggan/assets/customer.js`: render pesan “Kuota penuh untuk tanggal …: <nama produk>” pada `loadCatalog()`, sehingga otomatis diperbarui saat tanggal, kategori, atau pencarian berubah. Pesan disembunyikan pada hari libur agar tidak menimpa pesan libur.
- Backend checkout sudah menolak produk habis lewat `daily_quota_validate()` ⇒ tidak ada perubahan pada endpoint checkout.
- Migration: tidak ada.

### TASK-16 — 28f4d37
- `fix(TASK-16): add backdrop close to customer cart and checkout dialogs`
- Audit: detail dialog dan history dialog sudah memiliki pola `click` dengan `e.target === dialog`; cart dan checkout belum.
- Perbaikan `public/pelanggan/assets/customer.js`: pasang handler backdrop close pada `#cart-dialog` dan `#checkout-dialog` mengikuti pola yang sama; klik di dalam konten tidak menutup dialog; Escape/fokus tetap memakai perilaku native `<dialog>`; tidak ada scroll-lock JS yang perlu dipulihkan.
- Migration: tidak ada.

### TASK-17 — 6eec3ea
- `fix(TASK-17): tidy customer cart labels and checkout policy list`
- `public/pelanggan/index.php`: “RINGKASAN KERANJANG” → “ISI KERANJANG” (CUS-20).
- `public/pelanggan/assets/customer.js`: label “Jumlah” → “Jumlah dipesan”; “Sisa Porsi” tetap dipertahankan karena memang informasi kuota yang berbeda makna (CUS-18).
- `public/pelanggan/partials/layout.php`: `catalog-policy` dari paragraf tunggal menjadi daftar 2 poin, tetap berada tepat setelah pemilihan tanggal kirim (CUS-21).
- `public/pelanggan/assets/customer.css`: tambah gaya list pada `.catalog-policy`.
- Migration: tidak ada.

### TASK-18 — 8123bd3
- `fix(TASK-18): move customer add-order action to bottom-right FAB`
- `public/pelanggan/assets/customer.css`: tautan “Pesan menu” pada halaman riwayat (`body[data-page="history"]`) kini `position: fixed` di kanan bawah; pada mobile menjadi tombol bundar 54 px di atas navigasi bawah dengan `env(safe-area-inset-*)`; `.customer-content` diberi tambahan padding bawah agar tidak tertutup.
- Regression logout: dialog konfirmasi Pelanggan tidak diubah dan diuji ulang (4 skenario: Ya, Batal, backdrop, klik konten).
- CUS-22 (alignment panah 4 menu dashboard) **tidak ditemukan** di kode Pelanggan saat ini (tidak ada elemen panah pada menu dashboard); tidak ada perubahan spekulatif.
- Migration: tidak ada.

### TASK-20 — PARTIAL — (checkpoint ini)
- `fix(TASK-20): show quota date and tidy owner dashboard icons`
- `public/assets/js/app.js`: ringkasan kuota pada halaman produk Owner kini diawali tanggal yang sedang difilter (`Tanggal 30 Nov 2026 · Kapasitas dapur …`) — OWN-20.
- `public/owner/order-detail.php`: panah tombol kembali disamakan dengan topbar (`‹`) — OWN-17.
- `public/owner/dashboard.php`: hapus 2 ikon dekoratif (`<span class="ic">`) pada kartu “Pesanan Masuk” dan “Rincian Kategori” — OWN-10.
- Verifikasi: dialog logout Owner sudah memakai `confirmDialog` terpusat — OWN-18.
- **Tidak diubah:** metrik “Rata-rata Pesanan”/“Botol minuman” (OWN-09/11 menunggu keputusan #13); white-space/responsivitas detail (OWN-02/03/13/15) dan pemilihan tanggal tren (OWN-04) memerlukan QA visual/desain.
- Status PARTIAL: sebagian acceptance criteria masih bergantung pada keputusan #13 dan uji browser. Migration: tidak ada.
