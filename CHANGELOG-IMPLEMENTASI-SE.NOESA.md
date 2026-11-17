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

### TASK-02 — (checkpoint ini)
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
