# Handoff Se.Noesa-Web

## Kondisi saat ini
- PR #44 untuk branch `feature/pembatalan-pesanan` sudah merge ke `dev` pada 2026-11-13 (`3f782a7`). HEAD lokal branch fitur saat ini menunjuk commit merge tersebut dan selaras dengan `origin/dev`; branch lokal satu commit di depan `origin/feature/pembatalan-pesanan`.
- Tidak ada perubahan kode tracked yang belum di-commit. Handoff ini diperbarui atas permintaan pengguna dan akan dicatat tersendiri.

## Fitur yang sudah masuk
- Rentang libur operasional dapat ditambah, diedit, dan dihapus; checkout tanggal libur diblokir tanpa mengubah pesanan lama.
- Dashboard Admin menampilkan rekap porsi per menu untuk tanggal pengiriman pilihan dengan tautan ke detail pesanan.
- Admin memiliki tab/filter riwayat pembatalan, antrean Dashboard maksimal dua pesanan di bawah rekap produksi, detail pembatalan, dan aksi `Sudah ditinjau`.
- Metadata sumber/status asal pembatalan dan peninjau pertama tersimpan. Peninjauan tidak mengubah status pembatalan atau menandakan refund telah selesai.
- Pesanan offline dan alasan penolakan pembayaran juga tercakup di `dev`.

## Verifikasi yang sudah dilakukan
- Uji PostgreSQL terisolasi untuk migrasi 001–021, pembatalan, role, metadata, transaksi bersamaan, kuota, dan job lulus.
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

## Pekerjaan berikutnya
- Fitur penolakan pesanan dengan alasan dan tawaran reschedule masih terpisah. Belum diputuskan status yang dapat ditolak dan apakah tawaran reschedule hanya berupa usulan atau alur penerimaan pelanggan.
- Tidak ada perubahan `.env`; tidak ada push yang dilakukan pada tugas ini.
