# Test Matrix — Alur Pembayaran Se.Noesa (TASK-04)

- Tanggal: 2026-11-17
- Task: TASK-04 — Audit dan uji alur pembayaran end-to-end
- Batasan lingkungan: database Supabase remote diperlakukan sebagai produksi ⇒ **hanya `SELECT`**. Skenario yang memerlukan mutasi (checkout, upload, verifikasi, pembatalan) **tidak dijalankan** dan ditandai `TIDAK DIJALANKAN`. Bukti diambil dari inspeksi kode + query agregat read-only.
- Tidak ada data pribadi pelanggan yang dicantumkan.

## Matriks jalur

| # | Skenario | Harapan | Bukti kode | Bukti DB read-only | Status uji |
|---|---|---|---|---|---|
| 1 | Buat pesanan baru | Status awal `menunggu_pembayaran`, kuota ditempati | `public/pelanggan/api/orders.php:253-257` INSERT status `menunggu_pembayaran`; `app/daily-quota.php:44` menghitung semua status kecuali `ditolak`/`dibatalkan` | 6 pesanan berstatus `menunggu_pembayaran` | Teramati (kode + agregat); mutasi TIDAK DIJALANKAN |
| 2 | Tampil instruksi/QRIS | QRIS dari `metode_pembayaran` aktif; fallback bila kosong | `payment-methods.php:6`; `customer.js:531-535`; `imagePath()` `customer.js:76-82` menangani `uploads/payment-methods/` | 1 metode pembayaran aktif | Teramati (kode + agregat); render browser TIDAK DIJALANKAN |
| 3 | Batas waktu pembayaran | 1 jam sejak `created_at`, ditampilkan sebagai `payment_expires_at` | `orders.php:159` (upload), `:287` (expires_at), `order-expiration.php:20,35`; teks `layout.php:205` | 6 pesanan `menunggu_pembayaran` tanpa bukti sudah lewat 1 jam ⇒ menunggu eksekusi `expire_unpaid_orders()` | Teramati (kode + agregat) |
| 4 | Lewat tenggat, tanpa bukti | Status → `dibatalkan`, sumber `otomatis`, kuota dilepas | `order-expiration.php:15-47` (FOR UPDATE + UPDATE bersyarat + tutup notifikasi) | Belum ada eksekusi cron ⇒ 6 pesanan masih menunggu | Mutasi TIDAK DIJALANKAN (dilarang); logika terverifikasi statis |
| 5 | Upload bukti valid (status `menunggu_pembayaran`, dalam 1 jam) | File tersimpan, DB path terisi, status → `menunggu_validasi`, kuota tetap ditempati | `orders.php:113-173`: validasi owner/pesanan, MIME JPG/PNG/WebP, ≤2 MB, `FOR UPDATE`, UPDATE bersyarat `CASE WHEN status='menunggu_pembayaran' THEN 'menunggu_validasi'` | 0 pesanan `menunggu_pembayaran` dengan bukti (konsisten) | Mutasi TIDAK DIJALANKAN; logika terverifikasi statis |
| 6 | Ganti bukti (status `menunggu_validasi`) | Nama file baru, path DB diperbarui, status tetap `menunggu_validasi` | `orders.php:155-156` ELSE branch mempertahankan status; `customer.js:542-546,643-646` tombol “Ganti Bukti” | 3 pesanan `menunggu_validasi`, 0 tanpa bukti | Mutasi TIDAK DIJALANKAN; logika terverifikasi statis |
| 7 | Upload bukti setelah tenggat / status berubah | Ditolak 409, file dihapus, tidak ada record rusak | `orders.php:157-160` syarat waktu; `:169-172` `unlink` bila `!$affected`; `:164-168` rollback + `unlink` saat exception | — | Mutasi TIDAK DIJALANKAN; logika terverifikasi statis |
| 8 | Upload bukti ke pesanan bukan miliknya / ID invalid | 404 / 422 | `orders.php:114-118` filter `user_id`, `FILTER_VALIDATE_INT` | — | Mutasi TIDAK DIJALANKAN; logika terverifikasi statis |
| 9 | Format/ukuran file invalid | 422 dengan pesan jelas | `orders.php:128-139` (upload error, ≤2 MB, `getimagesize` MIME) | — | Mutasi TIDAK DIJALANKAN; logika terverifikasi statis |
| 10 | Verifikasi Admin | Status `menunggu_validasi` → `diproses`, hanya bila bukti ada | `public/admin/api/orders.php:197-213` (SELECT bukti + UPDATE bersyarat + timestamp) | 9 pesanan `diproses` | Mutasi TIDAK DIJALANKAN; logika terverifikasi statis |
| 11 | Penolakan Admin | Status → `ditolak`, alasan wajib 1–500 karakter, tampil ke Pelanggan | `admin/api/orders.php:148-179`; `customer.js:485-486,601-603` | 9 pesanan `ditolak`; 4 di antaranya tanpa alasan (data lama, sebelum validasi alasan wajib) | Mutasi TIDAK DIJALANKAN; temuan data lama dicatat |
| 12 | Tampilan status & alasan di Pelanggan | Label konsisten; alasan penolakan tampil | `customer.js:441-445` (label), `:485-486` (riwayat), `:601-603` (detail) | — | Render browser TIDAK DIJALANKAN; statis OK |
| 13 | Dampak transisi terhadap kuota | `ditolak`/`dibatalkan` melepas kuota; status lain menahan | `app/daily-quota.php:33-49`; `expire_unpaid_orders()` men set `dibatalkan` | Perbandingan `v_kuota_harian.terpakai` vs agregat status aktif: **0 baris selisih** pada data saat ini | Teramati (agregat); bug potensial pada view dicatat sebagai BUG-001 |
| 14 | Refund setelah pembayaran terverifikasi | Belum ada otomatisasi; teks UI menyebut refund penuh ditangani Admin di luar aplikasi | `customer.js:563` | — | **TIDAK DIUBAH** — menunggu keputusan bisnis #1 |

## Ringkasan hasil

- Jalur sukses/gagal terpetakan: 14 skenario.
- Status tidak melompat: transisi dijaga `UPDATE ... WHERE status = <expected>` dan/atau `FOR UPDATE` (`orders.php`, `admin/api/orders.php`, `order-expiration.php`).
- Bukti terikat pesanan yang benar: filter `id` + `user_id` di SELECT maupun UPDATE (`orders.php:117,151,157,161`).
- Kuota konsisten pada data aktual: 0 selisih antara view agregat dan perhitungan status aktif.
- Browser/API/DB: API + DB tercatat; browser **tidak dijalankan** (tidak ada automation, dan dilarang memakai data pelanggan nyata untuk mutasi).

## Temuan terbuka

| ID | Temuan | Bukti | Tindakan |
|---|---|---|---|
| ~~BUG-001~~ | **Koreksi TASK-07: false alarm.** Definisi final `v_kuota_harian` ada di `database/migrations/016_order_cancellation.sql:12`, yang sudah mengecualikan `ditolak` **dan** `dibatalkan`. Migration 011 adalah definisi lama yang ditimpa. Agregat DB aktual: 0 baris selisih. | `016_order_cancellation.sql:12`, `docs/03-audit/AUDIT-CONCURRENCY-KUOTA.md` | Tidak ada tindakan; dicatat sebagai koreksi. |
| OBS-001 | 6 pesanan `menunggu_pembayaran` tanpa bukti sudah melewati tenggat 1 jam; menunggu `scripts/expire-unpaid-orders.php` dijalankan penjadwal. | query agregat | Operasional, bukan bug kode. Perlu dipastikan cron aktif. |
| OBS-002 | 4 pesanan `ditolak` tanpa `alasan_penolakan` (data historis sebelum validasi alasan wajib). | query agregat | Tidak diubah; jangan backfill tanpa keputusan. |
| OBS-003 | Teks refund penuh di `customer.js:563` menyatakan kebijakan yang masih menunggu keputusan #1. | kode | Tidak diubah sampai #1 disetujui. |
