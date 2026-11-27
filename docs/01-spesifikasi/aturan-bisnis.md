# Aturan Bisnis: Kuota Produk

## Aturan aktif: default dan override

- Admin menetapkan kuota default per hari pengiriman saat membuat/mengedit produk,
  disimpan pada `produk.kuota_harian`.
- Default awal 50 porsi untuk produk baru. Migrasi 017 juga menetapkan semua
  default produk lama menjadi 50 satu kali; override tanggal tidak berubah.
- Kuota efektif memakai override `kuota_harian.kuota` bila barisnya ada, selain itu
  memakai default produk. Override 0 tetap berlaku dan tidak menjadi fallback.
- Rentang inklusif: tanggal mulai dan selesai termasuk, maksimal 366 tanggal per
  permintaan. Save terbaru menimpa tanggal overlap; tanggal lainnya tidak berubah.
- Kembali ke default menghapus override pilihan, tanpa memulihkan override lama.
- Default 0 membuat produk tidak tersedia kecuali ada override positif. Override
  0 membuat produk tidak tersedia pada tanggal tersebut.
- Kuota adalah kapasitas total, tidak dikurangi saat checkout.

## Pemesanan dan pemakaian

- Satuan pesanan porsi; minimal 4 porsi per varian atau nilai `min_pesan` lebih tinggi. Default produk 4 porsi.
- Tanggal kirim minimal H+7 dalam Asia/Jakarta.
- Pemakaian aktif penahan kuota adalah jumlah `detail_pesanan.qty` pada tanggal kirim Asia/Jakarta untuk pesanan yang memenuhi `daily_reserved_clause()` (`menunggu_validasi`, `diproses`, `selesai`, serta `menunggu_pembayaran` tanpa `bukti_bayar` yang tenggat 1 jam dari `created_at` belum habis). Pesanan `ditolak`, `dibatalkan`, dan `menunggu_pembayaran` yang kedaluwarsa tidak dihitung sebagai pemakaian aktif.
- Sisa produk `max(0, kuota efektif - pemakaian)`.
- Kapasitas dapur per tanggal `floor(jumlah kuota efektif semua produk × 80%)`; sisa dapur minimal 0. Override tanggal dan libur sudah diterapkan sebelum penjumlahan.
- Status kalender memakai sisa kapasitas: Tersedia di atas 40%, Hampir penuh pada 40% atau kurang selama masih ada sisa, dan Penuh jika sisa 0. Libur ditandai Libur; jika kapasitas masih tersisa tetapi tidak ada menu yang memenuhi minimum, statusnya Menu Habis.
- Admin tidak dapat menyimpan kuota efektif produk lebih kecil dari jumlah porsi aktif (`daily_reserved_clause()`) pada tanggal terdampak — misalnya jika sudah terpakai 40 porsi, nilai 39 ditolak dan nilai 40 diterima.
  - Saat edit kuota default (`produk.kuota_harian`), pemeriksaan berlaku pada seluruh tanggal aktif produk yang **tidak** memiliki override di `kuota_harian`; tanggal yang sudah memiliki override tidak membatasi perubahan default.
  - Saat `save`, `reset`, atau `delete` override pada `kuota_harian`, seluruh tanggal terdampak diperiksa secara atomik terhadap kuota efektif baru (nilai override baru untuk `save`, atau kuota default produk yang kembali berlaku untuk `reset`/`delete`).
  - Nilai `0` tetap diizinkan bila tanggal terdampak tidak memiliki pemakaian aktif (termasuk bila hanya ada pesanan `ditolak`, `dibatalkan`, atau `menunggu_pembayaran` kedaluwarsa).
- Pesanan yang sudah ada tidak diubah atau dibatalkan secara otomatis oleh perubahan kuota. Penurunan kuota yang tetap mencukupi pemakaian aktif tidak mengubah pesanan lama, dan checkout baru tetap ditolak jika sisa produk atau kapasitas dapur tidak mencukupi.
- Penolakan/pembatalan pesanan memulihkan sisa melalui agregasi, tanpa update counter.
- Owner read-only; hanya Admin menulis default dan override.

## Tanggal libur operasional

- Operasional selalu buka secara default; Admin memasukkan, mengedit, atau menghapus
  tanggal/rentang libur di halaman Jadwal.
- Rentang tanggal libur inklusif. Pada tanggal tersebut kuota efektif seluruh
  produk dan kapasitas dapur menjadi 0 tanpa mengubah kuota tersimpan.
- Pelanggan melihat peringatan saat memilih tanggal libur dan checkout pada
  tanggal itu ditolak oleh UI serta server.
- Pesanan yang sudah ada tidak berubah otomatis ketika libur ditambahkan.
  Kebijakan yang disepakati: Admin menolaknya satu per satu dengan alasan dan
  tawaran penjadwalan ulang; alur Admin tersebut menjadi implementasi terpisah.
- Sebelum rentang libur disimpan, Admin mendapat peringatan yang merinci pesanan
  aktif terdampak. Admin menindaklanjutinya memakai alur tawaran pada plan
  penolakan dan reschedule; penyimpanan libur tidak mengubah pesanan otomatis.
- Halaman Jadwal menampilkan pesanan aktif yang tanggal kirimnya tertutup agar
  Admin dapat membuka detail dan menawari jadwal pengganti. Pesanan offline
  memerlukan tindak lanjut manual karena tidak mempunyai respons pelanggan.
- Dashboard Admin merangkum jumlah porsi per menu untuk tanggal kirim terpilih.
  Hitungan mencakup pesanan selain `ditolak` dan `dibatalkan`; tautan membuka
  detail pesanan yang sudah menyediakan checklist dan tindakan status.

## Cakupan

`jadwal_po` tetap sebagai referensi pesanan dan tidak menentukan hari operasional.
Penutupan tanggal global memakai tabel tersendiri agar kuota produk tersimpan
tetap pulih otomatis ketika tanggal libur dihapus.

## Pembatalan pesanan

- Pesanan `menunggu_pembayaran` tanpa bukti kedaluwarsa satu jam sejak `created_at`.
  Tepat tenggat, upload bukti ditolak. Upload yang berhasil beralih ke
  `menunggu_validasi` menghentikan tenggat; antrean validasi Admin tidak dihitung.
- Pelanggan hanya dapat membatalkan pesanan sendiri dengan status
  `menunggu_pembayaran`, `menunggu_validasi`, atau `diproses` sampai akhir hari H-2
  Asia/Jakarta. Kirim 20 November: boleh hingga 18 November berakhir, ditolak mulai
  19 November 00:00 WIB. Tanggal kirim NULL/lampau tidak dapat dibatalkan manual.
- Refund penuh untuk uang yang sudah ditransfer ditangani Admin di luar aplikasi.
  Bukti bayar, foto kiriman, dan detail tetap disimpan. Kontak WhatsApp Admin akan
  ditambahkan setelah nomor/tautan tersedia; belum ada tautan sementara.
- `dibatalkan` terminal, seperti `selesai`/`ditolak`; tidak dapat dihidupkan kembali.
  Mutasi bersamaan diserialisasi. Upload/validasi yang selesai dahulu masih dapat
  diikuti pembatalan yang sah sampai H-2, dengan ketentuan refund di atas.
- Job CLI berjalan setiap menit, terpisah dari batas H-2. Kuota dilepas setelah
  status diubah job; keterlambatan sekitar satu siklus saat scheduler sehat.
- Jika pelanggan melaporkan transfer tanpa sempat mengunggah bukti, Admin mencari
  pesanan yang sudah dibatalkan untuk mencatat bukti rekonsiliasi. Catatan itu
  dapat dicari lagi menurut nama atau kode; pencatatan tidak memverifikasi
  transfer atau menghidupkan kembali pesanan lama.
- Pesanan lama mengikuti tenggat asli setelah inventaris dan dry-run sebelum
  aktivasi. Unpaid dengan waktu NULL atau bukti tersimpan dilaporkan untuk
  pemeriksaan manual dan tidak dibatalkan job.
- Pendapatan hanya dari `selesai`. Total semua pesanan tetap mencakup pembatalan.

## Tinjauan pembatalan oleh Admin

- Pembatalan yang sah langsung berlaku dan tidak menunggu persetujuan Admin.
  Tab Dibatalkan menampilkan seluruh riwayat; filter tinjauan membedakan belum
  dan sudah ditinjau. Pencarian kode/nama tetap tersedia.
- Pembatalan baru mencatat sumber pelanggan/otomatis dan status sebelumnya.
  Sumber/status asal data lama yang tidak diketahui tetap ditampilkan belum tercatat.
- Pemberitahuan Dashboard mencakup seluruh pembatalan belum ditinjau tanpa batas
  usia tujuh hari, termasuk data lama. Maksimal dua tautan ditampilkan dengan
  jumlah total dan tautan ke daftar lengkap.
- Pesanan yang sebelumnya diproses diprioritaskan untuk menghentikan persiapan/
  pengiriman. Pesanan dari menunggu_validasi atau dengan bukti tersimpan perlu
  pemeriksaan pembayaran. Bukti unggahan sendiri tidak menjamin uang sudah masuk.
- Tombol Sudah ditinjau mencatat waktu dan Admin pertama yang memeriksa.
  Penanda berlaku bersama untuk semua Admin; pengulangan tidak mengubah peninjau
  atau waktu pertama. Membuka detail atau GET tidak otomatis menandai tinjauan.
- Sudah ditinjau tidak berarti refund selesai dan tidak mengubah waktu/status
  pembatalan. Refund penuh tetap ditangani Admin di luar aplikasi jika pembayaran
  benar-benar diterima. Aplikasi tidak memindahkan uang.
- Detail batal menyediakan menu, bukti, dan foto yang tersimpan secara read-only;
  aksi upload/validasi/penyelesaian tetap tidak tersedia pada status terminal ini.
- Tinjauan tidak menambah pemakaian kuota dan tidak mengembalikan pesanan ke rekap
  produksi. Informasi diperbarui ketika halaman dimuat ulang; belum push realtime.

## Tawaran jadwal pengganti

- Admin dapat menawarkan satu tanggal pengganti hanya untuk pesanan online yang
  tanggal awalnya ditutup, berstatus menunggu pembayaran, menunggu validasi, atau
  diproses. Pesanan offline tidak dapat ditawarkan melalui alur pelanggan.
- Untuk status menunggu validasi, bukti harus tersedia; mengirim tawaran berarti
  Admin menyatakan pembayaran valid. Pembayaran yang tidak valid tetap ditolak
  memakai alasan penolakan pembayaran tanpa tawaran jadwal.
- Tawaran menyimpan snapshot tanggal awal dan status pesanan. Status/pengiriman
  lama tetap berlaku sampai pelanggan menerima. Tidak ada reservasi kuota tujuan.
- Tanggal tujuan diperiksa saat ditawarkan dan diperiksa ulang secara atomik saat
  diterima. Jam pengiriman lama dipertahankan. Jika asal menunggu validasi,
  penerimaan menjadikan status diproses.
- Tawaran menunggu validasi/diproses berlaku 24 jam. Tawaran menunggu pembayaran
  berakhir pada tenggat pembayaran asli satu jam sejak pesanan dibuat. Jawaban
  untuk tawaran yang diganti memakai versi lama ditolak.
- Pelanggan dapat menerima atau menolak melalui detail riwayat. Penolakan
  membatalkan pesanan dan mencatat metadata pelanggan; refund penuh untuk
  pembayaran yang diterima tetap ditangani Admin di luar aplikasi.
- Aksi validasi, penolakan pembayaran, checklist, upload foto kiriman,
  penyelesaian, upload bukti, dan pembatalan umum diblokir selama tawaran aktif.
  Job CLI tetap membatalkan unpaid tepat waktu dan menutup tawarannya.
