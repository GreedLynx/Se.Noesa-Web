# Handoff Se.Noesa-Web

## Kondisi saat ini
- Branch `feature/full-stack`, HEAD sebelum Tahap 4A: `c54b4a9` (hardening notifikasi perubahan menu: closure audit, advisory lock MNOT, guard Owner read-only, `LIMIT 2` dashboard).
- Tahap 4A (data integrity & payment safety: D2, D3, C6) sudah diimplementasi dan seluruh pengujian lulus; perubahan belum di-commit.
- Tidak ada perubahan `.env`; database produksi tidak disentuh. Semua pengujian memakai PostgreSQL uji terisolasi `127.0.0.1:55432` (`SENOESA_TEST_PGPORT`).

## Fitur yang sudah masuk
- Rentang libur operasional dapat ditambah, diedit, dan dihapus; checkout tanggal libur diblokir tanpa mengubah pesanan lama.
- Dashboard Admin menampilkan rekap porsi per menu untuk tanggal pengiriman pilihan dengan tautan ke detail pesanan.
- Admin memiliki tab/filter riwayat pembatalan, antrean Dashboard maksimal dua pesanan di bawah rekap produksi, detail pembatalan, dan aksi `Sudah ditinjau`.
- Metadata sumber/status asal pembatalan dan peninjau pertama tersimpan. Peninjauan tidak mengubah status pembatalan atau menandakan refund telah selesai.
- Pesanan offline dan alasan penolakan pembayaran juga tercakup di `dev`.

## Verifikasi yang sudah dilakukan
- Uji PostgreSQL terisolasi untuk migrasi 001 sampai 021, pembatalan, role, metadata, transaksi bersamaan, kuota, dan job lulus.
- Uji UI Dashboard, Detail Pesanan, daftar/filter pembatalan, dan halaman terkait lulus.
- Uji `scripts/test-order-rejection-reason.php` dan `scripts/test-offline-order.php` lulus; keduanya membungkus data uji dalam transaksi yang di-rollback.
- Lint PHP dan `git diff --check` lulus.
- Migrasi 021 sebelumnya diterapkan secara terarah; pemeriksaan API Admin baca-saja menemukan jumlah pesanan tetap 47 dan 15 pesanan batal. Tidak ada POST tinjauan pada database aplikasi.
- Tahap 4A: seluruh 11 uji Node lulus; seluruh uji PHP lulus di database uji terisolasi (termasuk `test-menu-change-notifications.php` dengan D2/D3/C6 dan `test-menu-notification-concurrency.php` dengan skenario lock kanonik + tanpa deadlock). `test-payment-deadline.php` diverifikasi lewat harness pembatalan; mode standalone tidak dipakai karena menyambung ke DB konfigurasi aplikasi.

## Keputusan dan batas
- Tinjauan berlaku bersama bagi semua Admin, persisten sampai ditinjau, dan bukan konfirmasi refund. Refund penuh tetap ditangani di luar aplikasi.
- Dashboard hanya menampilkan dua pesanan prioritas, tetapi jumlah dan tautan daftar mencakup seluruh antrean.
- Job kedaluwarsa berjalan melalui CLI; endpoint GET Admin tidak menjalankannya.
- Migrasi dengan awalan 019/020 memiliki nama file berbeda untuk fitur libur/rekap dan fitur offline/penolakan. Periksa ledger berdasarkan nama file lengkap sebelum penerapan.
- Belum dilakukan pemeriksaan melalui sesi browser Admin login langsung.
- Tahap 4A (D2/D3/C6):
  - Harga penggantian dikunci saat pelanggan memilih (`MENU_NOTICE_PRICE_POLICY = 'snapshot_saat_pelanggan_memilih'`); konfirmasi Admin memakai harga snapshot untuk detail dan total, dan perubahan harga katalog hanya diaudit (`harga_katalog_saat_konfirmasi`, `perbedaan_harga_katalog` — migrasi `024_menu_change_price_audit.sql`, tersinkron di `database/schema.sql`).
  - Transparansi pembayaran hanya membaca data yang ada: `pembayaran` pada detail respons memuat status pembayaran, bukti, nominal tagihan dari `pesanan.total`, total lama/baru, selisih, dan status penyelesaian selisih. Tidak ada status pembayaran baru, charge, atau refund otomatis.
  - Urutan lock tabel kanonik `produk` → `kuota_harian` → `tanggal_libur_operasional` dibungkus `daily_quota_lock_tables()` (`app/daily-quota.php`) dan dipakai checkout pelanggan, pesanan offline, respons penggantian pelanggan, serta konfirmasi/penolakan Admin.
  - `scripts/test-payment-deadline.php` hanya dijalankan lewat harness `scripts/test-order-cancellation.php` (mode `--existing`); mode standalone menyambung ke DB konfigurasi aplikasi dan tidak boleh dipakai.

## Rujukan implementasi utama
- Aturan dan indeks file fitur: `docs/peta-kode.md` (kalender libur, rekap produksi, pembatalan, tinjauan Admin).
- Kalender libur: `public/admin/schedules.php`, `public/admin/api/holidays.php`, `app/daily-quota.php`; migrasi `019_operational_holidays.sql`.
- Rekap produksi: `public/admin/dashboard.php`, `public/admin/api/dashboard.php`, `public/admin/assets/admin.js`; VIEW pada `020_daily_production_report.sql`.
- Pembatalan pelanggan dan job kedaluwarsa: `public/pelanggan/api/orders.php`, `app/order-cancellation.php`, `app/order-expiration.php`, `scripts/expire-unpaid-orders.php`; migrasi terkait `016_order_cancellation.sql` dan `018_payment_deadline.sql`.
- Tinjauan pembatalan Admin: `public/admin/orders.php`, `public/admin/order-detail.php`, `public/admin/api/orders.php`, `public/admin/api/dashboard.php`; migrasi `021_cancellation_admin_review.sql`.
- Detail skema dan kontrak endpoint: `docs/database.md` dan `docs/api.md`. Nama migrasi harus dicocokkan lengkap karena awalan 019 dan 020 dipakai oleh migrasi fitur lain.

## Pekerjaan berikutnya
- Fitur penolakan pesanan dengan alasan dan tawaran reschedule masih terpisah. Belum diputuskan status yang dapat ditolak dan apakah tawaran reschedule hanya berupa usulan atau alur penerimaan pelanggan.
- Sebelum implementasi reschedule, minta keputusan: status pesanan mana yang dapat ditolak; dan apakah Admin hanya mengirim alasan beserta tanggal usulan atau pelanggan perlu menerima tawaran lalu menjalani alur perubahan tanggal.
- Tidak ada perubahan `.env`; tidak ada push yang dilakukan pada tugas ini.
