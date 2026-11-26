# Rencana perbaikan hitung mundur bukti pembayaran

**Rekomendasi:** hentikan hitung mundur setelah server menerima bukti pertama. Pelanggan sudah memenuhi batas unggah. Selama menunggu Admin, pelanggan dapat mengganti bukti sesuai izin server. Saat Admin memvalidasi dan pesanan menjadi `diproses`, tutup akses ganti bukti.

## Temuan

Dokumen aturan bisnis dan kontrak API mengizinkan penggantian selama menunggu validasi tanpa tenggat satu jam. Pada branch ini, GET detail, pemeriksaan awal POST, dan UPDATE di `public/pelanggan/api/orders.php` masih menerapkan tenggat asli pada kedua status. Di `public/pelanggan/assets/customer.js`, `hasPaymentWindow` juga memasukkan keduanya.

## Langkah implementasi

1. Selaraskan GET detail dan POST unggah. Untuk `menunggu_pembayaran`, terima bukti hanya sebelum satu jam sejak pesanan dibuat berdasarkan jam database. Untuk `menunggu_validasi`, kembalikan tenggat kosong dan izinkan penggantian selama status serta aturan tawaran jadwal mengizinkan. Terapkan syarat yang sama pada UPDATE setelah penguncian baris. Pastikan job kedaluwarsa hanya membatalkan pesanan tanpa bukti yang memenuhi syarat.
2. Pada detail riwayat, tampilkan hitung mundur hanya untuk pembayaran pertama yang masih terbuka. Setelah POST sukses, bersihkan interval dan timeout lama, termasuk jika refresh gagal. Saat detail menunjukkan bukti sudah tersimpan, tampilkan “Menunggu verifikasi” dan tombol ganti sesuai izin server. Jika hasil POST tidak pasti, muat ulang status sebelum menawarkan kirim ulang. Upload yang gagal tidak memulai ulang tenggat.
3. Uji dengan waktu terkontrol. Unggah pertama sesaat sebelum tenggat harus berhasil, tepat saat tenggat harus ditolak. Sesudah unggah, tutup dan buka lagi detail lalu lewati tenggat asli: timer tetap hilang, pesanan tetap menunggu validasi, dan penggantian bukti masih bisa berhasil. Pastikan status diproses, ditolak, dan dibatalkan menutup unggah. Uji penggantian bersamaan dengan validasi Admin agar permintaan yang terlambat tidak menimpa bukti terverifikasi.
4. Verifikasi dengan PostgreSQL uji dan browser pelanggan, termasuk kegagalan jaringan, refresh, dan pembatasan saat ada tawaran jadwal. Perbarui kontrak API, peta kode, serta handoff berdasarkan hasil.

Status: diimplementasikan. Verifikasi API, browser, dan batas uji dicatat di `docs/00-panduan/HANDOFF.md`.
