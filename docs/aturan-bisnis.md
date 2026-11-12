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

- Satuan pesanan porsi; minimal 4 porsi per varian atau nilai min_pesan lebih tinggi.
- Tanggal kirim minimal H+7 dalam Asia/Jakarta.
- Pemakaian adalah jumlah detail pesanan pada tanggal kirim, semua status selain
  `ditolak` dan `dibatalkan`, termasuk belum dibayar dan selesai.
- Sisa produk `max(0, kuota efektif - pemakaian)`.
- Batas dapur `floor(jumlah kuota efektif seluruh produk × 80%)`; sisa dapur minimal 0.
- Penurunan default/override atau kuota 0 tidak membatalkan pesanan lama. Pemakaian
  tetap dihitung dan checkout baru ditolak jika sisa produk/dapur tidak mencukupi.
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
- Pesanan lama mengikuti tenggat asli setelah inventaris dan dry-run sebelum
  aktivasi. Unpaid dengan waktu NULL atau bukti tersimpan dilaporkan untuk
  pemeriksaan manual dan tidak dibatalkan job.
- Pendapatan hanya dari `selesai`. Total semua pesanan tetap mencakup pembatalan.
