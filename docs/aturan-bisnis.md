# Aturan Bisnis: Kuota Harian

## Plan 1 — aturan aktif

Kuota adalah konfigurasi `kuota_harian(produk_id,tanggal,kuota)`. Tidak ada fallback
ke master produk. Produk tanpa konfigurasi positif pada tanggal kirim tidak tersedia.
Batas dapur `floor(total kuota konfigurasi tanggal × 80%)`; pemakaian berasal dari
detail pesanan non-`ditolak` pada tanggal kirim Asia/Jakarta. Konfigurasi tidak dikurangi.
Penghapusan/penurunan konfigurasi tidak membatalkan pesanan lama; pemakaian tetap
dihitung, sisa minimal 0, checkout tambahan ditolak jika melewati kapasitas.
Minimal pesan 4 porsi dan kirim H+7 tetap berlaku. Owner hanya membaca.

Bagian berikut adalah catatan implementasi sebelum Plan 1.

Keputusan revisi: kuota mengikuti tanggal pengiriman dalam zona Asia/Jakarta,
dan produk aktif memiliki `produk.kuota_harian > 0`.

- Minimal pesan 4 porsi per varian; nilai `min_pesan` yang lebih tinggi tetap berlaku.
- Tanggal kirim minimal 7 hari dari tanggal pemesanan.
- Pemakaian produk adalah total porsi detail pesanan pada tanggal kirim yang dipilih,
  untuk seluruh status selain `ditolak`.
- Sisa produk adalah kuota master dikurangi pemakaian, minimal 0.
- Batas dapur adalah `floor(jumlah kuota master produk aktif × 0.8)`.
- Pesanan produk yang kemudian dinonaktifkan tetap dihitung dalam pemakaian dapur.
- Checkout tidak boleh melampaui sisa produk maupun batas dapur.
- Status `ditolak` memulihkan ketersediaan tanpa perubahan counter stok.
- Owner tetap read-only; pembayaran dan checklist dispatch memakai alur yang sudah ada.

Aturan 80% menggantikan batas kapasitas per jadwal pada checkout. Jadwal tetap
dipakai sebagai referensi pesanan; pemilihan jadwal aktif dan pembuatan jadwal
default yang sudah ada dipertahankan. Tidak ada batas maksimal tanggal pengiriman.
