# Handoff Se.Noesa-Web

## Kondisi saat ini
- PR #44 untuk branch `feature/pembatalan-pesanan` sudah merge ke `dev` pada 2026-11-13 (`3f782a7`). HEAD lokal saat ini `c98a903` (`docs: refresh project handoff`); branch fitur selaras dengan `origin/feature/pembatalan-pesanan` dan memuat merge PR #44 dari `dev`.
- Tidak ada perubahan kode aplikasi yang belum di-commit. `HANDOFF.md` sedang diperbarui untuk melengkapi konteks sesi berikutnya; perubahan ini belum di-commit.

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

## Keputusan dan batas
- Tinjauan berlaku bersama bagi semua Admin, persisten sampai ditinjau, dan bukan konfirmasi refund. Refund penuh tetap ditangani di luar aplikasi.
- Dashboard hanya menampilkan dua pesanan prioritas, tetapi jumlah dan tautan daftar mencakup seluruh antrean.
- Job kedaluwarsa berjalan melalui CLI; endpoint GET Admin tidak menjalankannya.
- Migrasi dengan awalan 019/020 memiliki nama file berbeda untuk fitur libur/rekap dan fitur offline/penolakan. Periksa ledger berdasarkan nama file lengkap sebelum penerapan.
- Belum dilakukan pemeriksaan melalui sesi browser Admin login langsung.

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
