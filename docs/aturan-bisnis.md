# Aturan Bisnis: Kuota Produk

## Aturan aktif: default dan override

- Admin menetapkan kuota default per hari pengiriman saat membuat/mengedit produk,
  disimpan pada `produk.kuota_harian`.
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
  `ditolak`, termasuk belum dibayar dan selesai.
- Sisa produk `max(0, kuota efektif - pemakaian)`.
- Batas dapur `floor(jumlah kuota efektif seluruh produk × 80%)`; sisa dapur minimal 0.
- Penurunan default/override atau kuota 0 tidak membatalkan pesanan lama. Pemakaian
  tetap dihitung dan checkout baru ditolak jika sisa produk/dapur tidak mencukupi.
- Penolakan pesanan memulihkan sisa melalui agregasi, tanpa update counter.
- Owner read-only; hanya Admin menulis default dan override.

## Cakupan

`jadwal_po` tetap sebagai referensi pesanan dan tidak diubah dalam revisi ini.
Penutupan tanggal pengiriman global dirancang terpisah. Status pesanan, pembayaran,
dispatch, dan batas maksimal tanggal kirim tidak berubah.
