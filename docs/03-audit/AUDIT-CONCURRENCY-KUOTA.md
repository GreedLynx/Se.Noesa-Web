# Audit Concurrency Kuota Checkout — TASK-07

- Tanggal: 2026-11-17
- Task: TASK-07 — Uji concurrency kuota saat checkout
- Dependency: TASK-02 (selesai), TASK-05 (selesai)
- Batasan: dilarang mengunci tabel produksi atau melakukan checkout mutasi ⇒ simulasi dua checkout bersamaan **tidak dijalankan**. Audit dilakukan dengan penelusuran kode + test TEMP-table yang di-rollback.

---

## 1. Peta jalur yang mengubah pemakaian kuota

| Jalur | File | Lock kanonik | Revalidasi setelah lock | Dampak kuota |
|---|---|---|---|---|
| Checkout Pelanggan | `public/pelanggan/api/orders.php:222-264` | `daily_quota_lock_tables()` → `kuota_harian ... FOR UPDATE` → `jadwal_po ... FOR UPDATE` | `daily_quota_snapshot()` + `daily_quota_validate()` | Menambah pemakaian |
| Pesanan manual Admin | `app/offline-orders.php:59-110` | `daily_quota_lock_tables()` | `daily_quota_snapshot()` + `daily_quota_validate()` | Menambah pemakaian |
| Konfirmasi penggantian menu | `app/menu-notifications.php:269-413` | `daily_quota_lock_tables()` + `FOR UPDATE OF r, d, p` + `FOR SHARE` produk | `menu_notice_available_products()` (snapshot + min_pesan) | Dapat memindahkan/add pemakaian |
| Verifikasi / penolakan / selesai | `public/admin/api/orders.php:143-213` | Tidak ada lock kuota | `UPDATE ... WHERE status = <expected>` | Hanya mengubah status; kuota dilepas saat `ditolak` |
| Pembatalan Pelanggan | `public/pelanggan/api/orders.php:12-48` | `SELECT ... FOR UPDATE` baris pesanan | `UPDATE ... WHERE status IN (...)` + H-2 | Melepas kuota (`dibatalkan`) |
| Expire otomatis | `app/order-expiration.php:4-61` | `SELECT ... FOR UPDATE` kandidat | `UPDATE ... WHERE status='menunggu_pembayaran' ...` | Melepas kuota (`dibatalkan`) |

**Kesimpulan urutan lock:** semua jalur yang dapat **menambah** pemakaian kuota memakai urutan kanonik yang sama (`produk` → `kuota_harian` → `tanggal_libur_operasional`) dengan mode `SHARE ROW EXCLUSIVE`. Dua mode yang sama saling konflik, sehingga jalur-jalur tersebut saling menunggu (serialize). Jalur yang hanya **melepas** kuota tidak mengambil lock ini, sehingga tidak dapat menyebabkan oversell.

## 2. Transaksi dan rollback

- `orders.php` memulai `beginTransaction()` sebelum mengambil lock; `daily_quota_validate()` melempar `DomainException` sebelum INSERT ⇒ `catch` melakukan `rollBack()` (`orders.php:265-272`).
- Kegagalan pada INSERT detail setelah INSERT pesanan ⇒ `catch (Throwable)` melakukan `rollBack()`, sehingga tidak ada detail pesanan parsial.
- `offline-orders.php` dan `menu_notifications_confirm_replacement` mengikuti pola `ownsTransaction` + `BEGIN/COMMIT/ROLLBACK` yang sama.
- `order-expiration.php` melakukan lock kandidat + `UPDATE` bersyarat di dalam satu transaksi; penutupan notifikasi ikut transaksi.

## 3. Isolation dan snapshot

- Koneksi memakai default `READ COMMITTED` (tidak ada `SET TRANSACTION ISOLATION LEVEL` di kode). Ini memadai karena lock meja sudah menyerialisasi penulis yang menambah pemakaian; pernyataan SELECT setelah lock melihat commit transaksi sebelumnya.
- `daily_quota_snapshot()` menghitung pemakaian dari `detail_pesanan` join `pesanan` dengan `status NOT IN ('ditolak','dibatalkan')` (`app/daily-quota.php:33-49`).
- View laporan `v_kuota_harian` definitive berasal dari `016_order_cancellation.sql:7-22`, yang juga mengecualikan `ditolak` **dan** `dibatalkan`. Catatan: `011_daily_quota_report.sql` masih berisi definisi lama (hanya `ditolak`), tetapi ditimpa oleh migration 016. Verifikasi DB aktual: 0 baris selisih antara view dan agregat status aktif.

## 4. Hasil test

- `scripts/test-daily-quota.php` **tidak dijalankan apa adanya** karena bagian akhir mengunci `kuota_harian` di database produksi (`LOCK TABLE ... SHARE ROW EXCLUSIVE` + koneksi kedua dengan `lock_timeout`). Menjalankannya berisiko memblokir checkout nyata.
- Salinan sementara tanpa bagian lock produksi dijalankan: **44 PASS**, 0 FAIL. Cakupan: indeks, default/override kuota, batas dapur 80%, sisa kuota, penolakan/pembatalan memulihkan kuota, tepat pada batas dapur diterima, minimum 4 porsi, zona waktu Asia/Jakarta, rentang UPSERT, validasi tanggal, dan konsistensi view laporan vs query checkout. Seluruh data uji berada di TEMP table dan dibatalkan (`rollback`).
- Simulasi dua checkout konkuren dengan data nyata **tidak dijalankan** (memerlukan INSERT/UPDATE pada DB remote).

## 5. Kesimpulan

- Tidak ditemukan race condition atau inkonsistensi mekanisme lock pada kode checkout saat ini.
- Tidak ada perubahan kode yang diperlukan (sesuai instruksi TASK-07: perbaiki hanya jika test membuktikan masalah).
- Status TASK-07: **DONE (audit + test terbatas)**. Skenario konkuren dengan mutasi nyata tetap tercatat sebagai belum diuji.

## 6. Catatan koreksi BUG-001

BUG-001 yang dicatat pada TASK-04 (**“view kuota hanya mengecualikan ditolak”**) dinyatakan **tidak berlaku / false alarm**. Definisi final view ada di `database/migrations/016_order_cancellation.sql:12` yang sudah mengecualikan `dibatalkan`. Bukti: test “Pembatalan memulihkan kuota produk/dapur sama dengan penolakan dan VIEW” lulus, dan query agregat pada DB aktual menghasilkan 0 baris selisih.
