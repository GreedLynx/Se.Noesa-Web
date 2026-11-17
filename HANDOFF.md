# Handoff Se.Noesa-Web

## Kondisi saat ini
<<<<<<< HEAD
- PR #45 sudah menggabungkan branch `feature/pembatalan-pesanan` ke `dev` pada commit `62e3a23`.
- Branch aktif `feature/penolakan-reschedule` dibuat dari `dev` lokal yang sudah di-fast-forward ke `origin/dev` (`62e3a23`).
- Implementasi penolakan dan tawaran jadwal ulang selesai pada branch ini. Perubahan belum di-commit atau di-push; migrasi 022 belum diterapkan ke database.
=======
- Branch `feature/full-stack`, HEAD sebelum Tahap 4A: `c54b4a9` (hardening notifikasi perubahan menu: closure audit, advisory lock MNOT, guard Owner read-only, `LIMIT 2` dashboard).
- Tahap 4A (data integrity & payment safety: D2, D3, C6) sudah diimplementasi dan seluruh pengujian lulus; perubahan belum di-commit.
- Tidak ada perubahan `.env`; database produksi tidak disentuh. Semua pengujian memakai PostgreSQL uji terisolasi `127.0.0.1:55432` (`SENOESA_TEST_PGPORT`).
>>>>>>> b5df332373313def0a2b82cae5473d007bbd7f51

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
- Klarifikasi pengguna: pembatalan/penolakan oleh Admin mencakup status `Menunggu pembayaran`, `Menunggu Validasi`, dan `Diproses`, dilakukan manual per pesanan.
- `Menunggu pembayaran`: penanganan bergantung kondisi; kasus yang sudah ditetapkan adalah pesanan dibuat sebelum Admin melakukan close order pada tanggal pengiriman tersebut. Admin dapat membatalkan dengan catatan dan tawaran jadwal pengiriman pengganti.
- `Menunggu Validasi`: jika pembayaran tidak valid, Admin dapat menolak dengan catatan alasan kegagalan pembayaran. Jika pembayaran valid tetapi tanggal pengiriman ditutup oleh Admin, Admin dapat menolak dengan catatan dan tawaran jadwal pengganti.
- `Diproses`: pembatalan hanya ketika tanggal pengiriman ditutup oleh Admin setelah pesanan dibuat, disertai catatan dan tawaran jadwal pengganti.
- Pengguna menyetujui alur dalam aplikasi: Admin memberi satu tanggal usulan; pelanggan menerima atau menolak. Penerimaan memeriksa ulang tanggal libur dan kuota; penolakan membatalkan pesanan dan refund pembayaran valid tetap manual di luar aplikasi.
- Rencana minimum mempertahankan status pesanan selama tawaran menunggu dan menyimpan tawaran pada tabel terpisah. Tawaran paid/diproses berlaku 24 jam; unpaid tetap mengikuti tenggat pembayaran satu jam. Tanggal tujuan tidak memesan kuota sampai diterima.
- Rencana dan hasil eksekusi ada di `docs/rencana-penolakan-reschedule.md`. Migrasi 022 menambah tabel tawaran terpisah; Admin membuat/mengganti tawaran dan pelanggan menerima/menolak dengan token versi. Accept memeriksa kuota ulang; reject mencatat pembatalan pelanggan. Aksi pembayaran/produksi yang bentrok dijaga di server.
- Verifikasi yang lulus: PHP lint pada API Admin/Pelanggan dan job kedaluwarsa; JavaScript syntax check; `scripts/test-order-cancellation-ui.cjs`, `scripts/test-admin-order-detail.cjs`, `scripts/test-admin-cancellation-list.cjs`, `scripts/test-admin-dashboard.cjs`; `git diff --check`.
- Verifikasi yang belum dilakukan: integrasi PostgreSQL/migrasi dan browser login. Tes `scripts/test-payment-deadline.php` disesuaikan untuk tawaran aktif tetapi tidak dijalankan karena harness tersebut membaca konfigurasi proyek dan variabel koneksi PostgreSQL uji tidak tersedia. Tidak ada akses ke `.env` atau database aplikasi.
- Review sumber akhir selesai: tiga temuan tentang lock, tanggal kedaluwarsa, dan tombol Admin diperbaiki; reviewer tidak menemukan isu tambahan. Berikutnya, jalankan suite PostgreSQL terisolasi dan migrasi fresh/upgrade saat koneksi test tersedia, lalu commit dengan Conventional Commit. Jangan push/merge tanpa langkah eksplisit berikutnya.
- Percobaan awal membuat branch gagal karena sandbox tidak dapat menulis `.git`; operasi diulang dengan izin Git, lalu `dev` berhasil di-fast-forward dan branch fitur berhasil dibuat tanpa kehilangan perubahan handoff.
- Tidak ada perubahan `.env`; tidak ada push yang dilakukan pada tugas ini.

## Catatan lanjutan (16 Nov 2026)
- Saat endpoint `public/pelanggan/api/orders.php` memuat daftar pesanan, koneksi PostgreSQL gagal pada `PDO::__construct` dengan `SSL SYSCALL error: Connection reset by peer`; kegagalan terjadi sebelum query daftar pesanan berjalan, jadi ini belum menunjukkan bug SQL pada fitur reschedule.
- Log yang dibagikan memuat kredensial DB dalam trace argumen PDO. Rotasi password database dan pembaruan secret di konfigurasi/deployment diperlukan; kredensial tidak disimpan di repo/HANDOFF.
- Lingkungan sesi ini tidak menyediakan variabel koneksi PostgreSQL uji dan tidak ada utilitas `psql`/`pg_isready`; verifikasi DB terisolasi dan migrasi 022 tetap belum bisa dijalankan. Jangan menerapkan migrasi ke DB aplikasi sebagai pengganti.
- Syntax PHP endpoint tetap valid (`php -l`); `git diff --check` bersih.
