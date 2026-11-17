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

### TASK-05 — (checkpoint ini)
- `fix(TASK-05): enforce per-product minimum in cart and clarify admin validation`
- Audit: sumber minimum konsisten di DB (`CHECK (min_pesan >= 4)`, default 4), `daily_quota_snapshot` (`GREATEST(4, min_pesan)`), `daily_quota_validate` (dipakai checkout & pesanan offline), form Admin (`min="4"`), dan API Admin (`>= 4`).
- Bug ditemukan: tombol kurang di keranjang Pelanggan `cart[id] -= min` bisa menghasilkan jumlah di bawah minimum (mis. 6 − 4 = 2 untuk minimum 4), sehingga checkout ditolak backend setelah UI mengizinkan state invalid.
- Perbaikan:
  - `public/pelanggan/assets/customer.js`: clamp hasil pengurangan ke `min`, tampilkan pesan “Minimum pesanan … adalah N porsi” bila clamp terjadi; hapus item hanya bila jumlah sudah sama dengan minimum.
  - `public/admin/api/products.php`: pisahkan validasi harga dan minimum; pesan error spesifik “Minimum pemesanan minimal 4 porsi”.
- Tidak mengubah angka/aturan minimum bisnis.
- Migration: tidak ada.
