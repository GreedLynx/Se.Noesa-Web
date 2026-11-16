# Verifikasi Fitur Notifikasi Perubahan Menu

Tanggal: 16 November 2026

## Ruang lingkup

Verifikasi menutup temuan **S13** pada `docs/audit-2026-11-13.md`: alur
notifikasi perubahan menu dari Admin ke pelanggan, persetujuan penggantian,
selisih harga, tindak lanjut manual, kepemilikan data, dan tampilan detail
pesanan pelanggan.

## Hasil verifikasi otomatis

| Pemeriksaan | Perintah | Hasil |
|---|---|---|
| Sintaks seluruh PHP (73 file) | `php -l` per file | Lulus, 0 error |
| Migrasi, target, kepemilikan, belum dibaca, konfirmasi, selisih, tindak lanjut | `php scripts/test-menu-change-notifications.php` | **PASS** |
| Kontrak UI Admin/Owner/pelanggan (escaping, mode, tandai dibaca) | `node scripts/test-menu-notifications-ui.cjs` | **PASS** |
| Regresi UI yang sudah ada | `node scripts/test-*.cjs` | Semua **PASS** |
| Whitespace diff | `git diff --check` | Bersih |

Uji PHP memakai schema sementara di dalam transaksi lalu melakukan rollback,
sehingga tidak menyisakan data uji. Cakupan yang ditambahkan pada verifikasi ini:

- target hanya berasal dari pesanan aktif (`menunggu_pembayaran`,
  `menunggu_validasi`, `diproses`) dengan kuota efektif 0;
- pesanan `selesai` tidak menjadi target;
- override kuota positif pada tanggal kirim menghapus target;
- target yang sudah punya respons aktif ditandai `sudah_dinotifikasi`;
- hitungan belum dibaca hanya milik pelanggan online pemilik pesanan dan
  kembali 0 setelah ditandai dibaca;
- persetujuan Admin mengganti detail, menghitung total lama/baru, dan mencatat
  selisih bertanda tanpa mengubah status pesanan;
- penyelesaian selisih dan tindak lanjut manual tercatat;
- riwayat tidak dapat dihapus bersama pesanan (`ON DELETE RESTRICT`).

## Hasil pemeriksaan HTTP lokal

Server: `php -S 127.0.0.1:8000 -t public`.

| Skenario | Status |
|---|---|
| Owner membuka daftar API | 200 |
| Owner mengirim POST pembuatan | 403 (hanya Admin) |
| Owner membuka halaman notifikasi | 200 |
| Admin membuka preview dan halaman notifikasi | 200 |
| Pelanggan membuka daftar API dan halaman notifikasi | 200 |
| Pelanggan mengakses API Admin | 401 |
| Halaman Admin/Owner/pelanggan | 200 tanpa warning PHP |

Daftar notifikasi masih kosong pada database kerja karena belum ada
notifikasi nyata yang dibuat. Migrasi `022_menu_change_confirmation_audit.sql`
diterapkan melalui `php scripts/migrate.php`.

## Pemeriksaan manual yang masih disarankan

Lakukan sekali pada lingkungan uji dengan data nyata sebelum rilis:

1. Pilih menu yang kuota efektifnya 0 pada tanggal kirim sebuah pesanan aktif,
   buat notifikasi, dan pastikan pelanggan menerima indikator belum dibaca.
2. Pilih menu pengganti, lalu setujui dari Admin; periksa detail item, total
   pesanan, dan catatan selisih.
3. Tandai tagihan/pengembalian selesai dan catat tindak lanjut untuk pesanan
   offline.
4. Buka detail pesanan pelanggan dan pastikan tautan notifikasi membuka detail
   yang benar.

Pemeriksaan ini sengaja tidak dijalankan otomatis karena membuat notifikasi
nyata dan dapat terlihat oleh pelanggan di database bersama.
